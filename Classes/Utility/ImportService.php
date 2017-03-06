<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Tollwerk\TwImporter\Utility;

use Tollwerk\TwImporter\Domain\Model\AbstractImportable;
use Tollwerk\TwImporter\Domain\Model\TranslatableInterface;
use Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository;
use Tollwerk\TwImporter\Utility\FileAdapter\FileAdapterInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Import service
 */
class ImportService
{
    /**
     * Settings
     *
     * @var array
     */
    protected $settings;

    /**
     * Object manager
     *
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Database utility
     *
     * @var Database
     */
    protected $dbUtility;

    /**
     * Mapping utility
     *
     * @var Mapping
     */
    protected $mappingUtility;

    /**
     * Object utility
     *
     * @var Object
     */
    protected $objectUtility;

    /**
     * Language suffices
     *
     * @var array
     */
    protected $languageSuffices = null;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Extension mappings
     *
     * @var array[]
     */
    protected static $mappings = [];

    /**
     * Import service constructor
     *
     * @param array $settings Settings
     * @param array $languageSuffices Language suffices
     */
    public function __construct(array $settings, array $languageSuffices, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->languageSuffices = $languageSuffices;
        $this->logger = $logger;
    }

    /**
     * Run an extension import
     *
     * @param string $extensionKey Extension key
     * @throws InvalidActionNameException If there's no mapping for the given extension
     * @throws InvalidActionNameException If there's no file adapter configured
     */
    public function run($extensionKey)
    {
        ini_set('max_execution_time', 0);
        $this->logger->log('Starting import for extension "'.$extensionKey.'"', FlashMessage::INFO);
        $importStart = time();

        // If there's no mapping for the given extension
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey])) {
            throw new \InvalidArgumentException(
                sprintf('No import configuration for "%s"', $extensionKey),
                1488382570
            );
        }

        $fileAdapterNameConfig = $this->mappingUtility->getAdapter($extensionKey);
        /** @var FileAdapterInterface $fileAdapter */
        $fileAdapter = $this->objectManager->get(FileAdapterFactory::class)->getAdapterByName(
            $fileAdapterNameConfig,
            $this->logger
        );
        $records = $fileAdapter->import($extensionKey);
        $this->logger->log('Extracted '.$records.' records from import file', FlashMessage::OK);

//      // TODO: implement filterRecords() (Flag for updating / not updating in import file etc.)

        $this->importTemporaryRecords($extensionKey);

        // TODO: move import file to archive before further processing (if(settings->archive))

        foreach ($this->objectUtility->getUpdatedObjectsCounter() as $counterClass => $counterSum) {
            $this->logger->log(
                'Created / updated '.$counterSum.' objects of class '.$counterClass,
                FlashMessage::NOTICE
            );
        }

        // Purge repositories
        foreach ($this->mappingUtility->getPurgeRepositories($extensionKey) as $repositoryClass) {
            $repository = $this->objectManager->get($repositoryClass);
            if ($repository instanceof AbstractImportableRepository) {
                $purged = $repository->deleteOlderThan($importStart);
                $this->logger->log(
                    'Purged '.$purged.' objects from repository '.$repositoryClass,
                    FlashMessage::NOTICE
                );
            }
        }

        $this->logger->log('Done with import for extension key: '.$extensionKey, FlashMessage::OK);
    }

    /**
     * Import the temporary records into the production database
     *
     * @param string $extensionKey Extension key
     */
    protected function importTemporaryRecords($extensionKey)
    {
        $hierarchy = $this->mappingUtility->getHierarchy($extensionKey);

        // Iterate over all temporary records
        foreach ($this->dbUtility->getTemporaryRecords($extensionKey) as $temporaryRecord) {
            $this->importTemporaryRecord($extensionKey, $temporaryRecord, key($hierarchy), current($hierarchy), 0);
        }
    }

    /**
     * Inject the object manager
     *
     * @param ObjectManager $objectManager
     */
    public function injectObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Inject the mapping utility
     *
     * @param Mapping $mappingUtility Mapping utility
     */
    public function injectMappingUtility(Mapping $mappingUtility)
    {
        $this->mappingUtility = $mappingUtility;
    }

    /**
     * Import a temporary record
     *
     * @param string $extensionKey Extension key
     * @param array $record Temporary record
     * @param string $modelClass Model class
     * @param array $modelConfig Model configuration
     * @param int $level Indentation level
     * @param AbstractImportable $parentObject Parent object
     */
    protected function importTemporaryRecord(
        $extensionKey,
        array $record,
        $modelClass,
        array $modelConfig,
        $level = 0,
        AbstractImportable $parentObject = null
    ) {
        $flashMessageIndent = str_repeat('---', $level);
        $importIdField = array_key_exists('importIdField', $modelConfig) ?
            $modelConfig['importIdField'] : 'tx_twimporter_id';
        $importId = $record[$importIdField];

        // If the pre-conditions are met
        if ($this->mappingUtility->checkHierarchyConditions($record, $modelConfig)) {
            $sysLanguage = -1;
            $langSuffix = null;

            // If the model supports translations
            $reflectionClass = new \ReflectionClass($modelClass);
            if ($reflectionClass->implementsInterface(TranslatableInterface::class)) {
                // TODO: Run with translatable records
//                $this->importTranslatableTemporaryRecord($extensionKey, $record, $modelClass, $modelConfig, $level);
//                $sysLanguage = -1;
//                $langSuffix = null;

                // Else: Import as simple record
            } else {
                $object = $this->importSimpleTemporaryRecord(
                    $extensionKey,
                    $record,
                    $modelClass,
                    $modelConfig,
                    $level,
                    $importId,
                    $sysLanguage,
                    $langSuffix,
                    $parentObject
                );
            }

            // Recursively call for child models
            if (!empty($modelConfig['children']) && is_array($modelConfig['children'])) {
                foreach ($modelConfig['children'] as $childModelClass => $childModelConfig) {
                    $this->importTemporaryRecord(
                        $extensionKey,
                        $record,
                        $childModelClass,
                        $childModelConfig,
                        $level + 1,
                        $object
                    );
                }
            }

            // Else: Log a skip message
        } else {
            $this->logger->log(
                sprintf('Pre-conditions not met for record "%s", skipping ...', $importId),
                FlashMessage::WARNING
            );
        }
    }

    /**
     * Import a translatable temporary record
     *
     * @param string $extensionKey Extension key
     * @param array $record Translatable temporary record
     * @param string $modelClass Model class
     * @param array $modelConfig Model configuration
     * @param int $level Indentation level
     * @internal param array $hierarchy Record hierarchy
     */
    protected function importTranslatableTemporaryRecord(
        $extensionKey,
        array $record,
        $modelClass,
        array $modelConfig,
        $level = 0
    ) {
        // Run through all languages
        foreach ($this->languageSuffices as $sysLanguage => $langSuffix) {
            // TODO: Call importSimpleTemporaryRecord() for each language
        }
    }

    /**
     * Import a simple temporary record
     *
     * @param string $extensionKey Extension key
     * @param array $record Translatable temporary record
     * @param string $modelClass Model class
     * @param array $modelConfig Model configuration
     * @param int $level Indentation level
     * @param string $importId Unique import identifier
     * @param int $sysLanguage System language (-1 for all languages / non-translatable)
     * @param string $langSuffix Language suffix
     * @param AbstractImportable $parentObject Parent object
     * @return AbstractImportable Imported object
     */
    protected function importSimpleTemporaryRecord(
        $extensionKey,
        array $record,
        $modelClass,
        array $modelConfig,
        $level,
        $importId,
        $sysLanguage,
        $langSuffix,
        $parentObject = null
    ) {

        // Try to find the object by parent relation
        /** @var AbstractImportable $object */
        /** @var string $objectStatus */
        list($object, $objectStatus) = $this->objectUtility->findOrCreateImportableObject(
            $modelClass,
            $modelConfig,
            $importId,
            $sysLanguage
        );

        // If the object properties can successfully be updated
        if ($object->import(
            $record,
            $this->getMapping($extensionKey),
            $langSuffix,
            $this->languageSuffices,
            [],
            $level)
        ) {
            // Persist the updated object
            $this->objectUtility->update($modelClass, $modelConfig, $object);
        }

        // Optionally register with the parent object
        if ($parentObject instanceof AbstractImportable) {
            $this->objectUtility->registerWithParentObject($object, $modelConfig, $parentObject);
        }

        return $object;
    }

    /**
     * Inject the object utility
     *
     * @param Object $objectUtility Object utility
     */
    public function injectObjectUtility(Object $objectUtility)
    {
        $this->objectUtility = $objectUtility;
    }

    /**
     * Inject the database utility
     *
     * @param Database $dbUtility Database utility
     */
    public function injectDbUtility(Database $dbUtility)
    {
        $this->dbUtility = $dbUtility;
    }

    /**
     * Get an extension mapping
     *
     * @param string $extensionKey Extension key
     * @return array Extension mapping
     */
    protected function getMapping($extensionKey)
    {
        if (!array_key_exists($extensionKey, self::$mappings)) {
            self::$mappings[$extensionKey] = $this->mappingUtility->getMapping($extensionKey);
        }
        return self::$mappings[$extensionKey];
    }
}
