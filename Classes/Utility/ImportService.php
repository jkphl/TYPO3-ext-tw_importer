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

use ErrorException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Tollwerk\TwImporter\Domain\Model\AbstractImportable;
use Tollwerk\TwImporter\Domain\Model\TranslatableInterface;
use Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository;
use Tollwerk\TwImporter\Utility\FileAdapter\FileAdapterInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Import service
 */
class ImportService
{
    /**
     * Extension mappings
     *
     * @var array[]
     */
    protected static $mappings = [];
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
     * Current master object
     *
     * @var AbstractImportable
     */
    protected $currentMasterObject = null;
    /**
     * Current record cache
     *
     * @var array
     */
    protected $currentRecordCache = null;

    /**
     * Import service constructor
     *
     * @param array $settings         Settings
     * @param array $languageSuffices Language suffices
     */
    public function __construct(array $settings, array $languageSuffices, LoggerInterface $logger)
    {
        $this->settings         = $settings;
        $this->languageSuffices = $languageSuffices;
        $this->logger           = $logger;
    }

    /**
     * Run an extension import
     *
     * @param string $extensionKey    Extension key
     * @param string|null $importFile Optional: Import file
     *
     * @throws ErrorException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws ReflectionException
     * @throws UnknownObjectException
     */
    public function run(string $extensionKey, string $importFile = null)
    {
        ini_set('max_execution_time', 0);
        $this->logger->log('Starting import for extension "'.$extensionKey.'"', FlashMessage::INFO);
        $this->logger->stage(LoggerInterface::STAGE_PREPARATION);

        $importStart = time();

        // If there's no mapping for the given extension
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey])) {
            throw new InvalidArgumentException(
                sprintf('No import configuration for "%s"', $extensionKey),
                1488382570
            );
        }

        /** @var FileAdapterInterface $fileAdapter */
        $fileAdapterNameConfig = $this->mappingUtility->getAdapter($extensionKey);
        $fileAdapter           = $this->objectManager->get(FileAdapterFactory::class)
                                                     ->getAdapterByName($fileAdapterNameConfig, $this->logger);
        $records               = $fileAdapter->import($extensionKey, $importFile);

        $this->logger->log('Extracted '.$records.' records from import file', FlashMessage::OK);
        $this->logger->stage(LoggerInterface::STAGE_IMPORTING);

        $this->importTemporaryRecords($extensionKey);

        $this->logger->stage(LoggerInterface::STAGE_FINALIZING);

        foreach (
            $this->objectManager->get(ItemUtility::class)->getUpdatedObjectsCounter() as $counterClass => $counterSum
        ) {
            $this->logger->log(
                'Created / updated '.$counterSum.' objects of class '.$counterClass,
                FlashMessage::NOTICE
            );
        }

        // Purge repositories
        foreach ($this->mappingUtility->getPurgeRepositories($extensionKey) as $repositoryClass => $purgeConditions) {
            $repository = $this->objectManager->get($repositoryClass);
            if ($repository instanceof AbstractImportableRepository) {
                $purgeConditions = ($purgeConditions === true) ? [] : (array)$purgeConditions;
                $purged          = $repository->deleteOlderThan($importStart, $purgeConditions);
                $this->logger->log(
                    'Purged '.$purged.' objects from repository '.$repositoryClass,
                    FlashMessage::NOTICE
                );
            }
        }

        // Call finalizers
        foreach ($this->mappingUtility->getFinalizers($extensionKey) as $finalizerClass => $finalizeParameters) {
            if ((new ReflectionClass($finalizerClass))->implementsInterface(ImportFinalizerInterface::class)) {
                call_user_func_array(
                    [$finalizerClass, 'finalizeImport'],
                    [$this->settings, $this->languageSuffices, $this->logger, $importStart, (array)$finalizeParameters]
                );
            }
        }

        $this->logger->log('Done with import for extension key: '.$extensionKey, FlashMessage::OK);
        $this->logger->stage(LoggerInterface::STAGE_FINISHED);
    }

    /**
     * Import the temporary records into the production database
     *
     * @param string $extensionKey Extension key
     *
     * @throws ErrorException
     * @throws ReflectionException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function importTemporaryRecords($extensionKey)
    {
        $this->currentMasterObject = null;
        $this->currentRecordCache  = null;
        $hierarchy                 = $this->mappingUtility->getHierarchy($extensionKey);
        $temporaryRecords          = $this->dbUtility->getTemporaryRecords($extensionKey);

        $this->logger->stage(LoggerInterface::STAGE_IMPORTING);
        $this->logger->count(count($temporaryRecords));

        // Iterate over all temporary records
        foreach ($temporaryRecords as $step => $temporaryRecord) {
            $recordCache = $this->mappingUtility->buildRecordCache($extensionKey, $temporaryRecord);
            $importId    = $this->importTemporaryRecord(
                $extensionKey,
                $temporaryRecord,
                key($hierarchy),
                current($hierarchy),
                $recordCache,
                0
            );
            $this->logger->step($step, $importId);
        }

        if ($this->currentMasterObject) {
            $this->currentMasterObject->finalizeImport($this->currentRecordCache);
            $this->currentMasterObject = null;
            $this->currentRecordCache  = null;
        }

        // Persist all unpersisted objects
        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();
    }

    /**
     * Import a temporary record
     *
     * @param string $extensionKey             Extension key
     * @param array $record                    Temporary record
     * @param string $modelClass               Model class
     * @param array $modelConfig               Model configuration
     * @param array $recordCache               Internal record cache
     * @param int $level                       Indentation level
     * @param AbstractImportable $parentObject Parent object
     *
     * @return string Import ID
     * @throws ErrorException
     * @throws IllegalObjectTypeException
     * @throws ReflectionException
     * @throws UnknownObjectException
     */
    protected function importTemporaryRecord(
        string $extensionKey,
        array $record,
        string $modelClass,
        array $modelConfig,
        array $recordCache,
        int $level = 0,
        AbstractImportable $parentObject = null
    ): string {
        $importIdField = $modelConfig['importIdField'] ?? 'tx_twimporter_id';

        // If the import id is a compound key
        if (is_array($importIdField)) {
            $importId = md5(serialize(array_map(function($importIdFieldFacet) use ($record) {
                return empty($record[$importIdFieldFacet]) ? null : $record[$importIdFieldFacet];
            }, $importIdField)));
        } else {
            $importId = empty($record[$importIdField]) ? null : $record[$importIdField];
        }

        // If the pre-conditions for creating a new master object are all met
        $conditionsStatus = $this->mappingUtility->checkHierarchyConditions($record, $modelConfig);
        if ($conditionsStatus == Mapping::STATUS_OK) {
            // If this is going to be a top-level object and there's a previous master object: Finalize & close
            if (!$parentObject && ($this->currentMasterObject instanceof AbstractImportable)) {
                $this->currentMasterObject->finalizeImport($this->currentRecordCache);
                $this->currentMasterObject = null;
                $this->currentRecordCache  = null;
            }

            $this->logger->log(
                sprintf('Condition ok (%s) for record "%s", importing ...', $conditionsStatus, $importId),
                FlashMessage::INFO
            );


            $sysLanguage     = -1;
            $langSuffix      = null;
            $reflectionClass = new ReflectionClass($modelClass);

            // If the model supports translations
            if ($reflectionClass->implementsInterface(TranslatableInterface::class)) {
                // TODO: Run with translatable records

                // Else: Non-translateable record
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

            // If this is a top-level object: Register as master object
            if (!$parentObject) {
                $this->currentMasterObject = $object;
                $this->currentRecordCache  = $recordCache;
            }

            // Recursively call for child models
            $this->processPendentObjects($extensionKey, $record, $modelConfig, $recordCache, $level + 1, $object);

            // If this is not a top-level object: Finalize
            if ($parentObject) {
                $object->finalizeImport($recordCache);
            }

            // Else: If the row is disabled and it's a top-level object and there's a current master object:
            // Finalize the master object and skip the row
        } elseif (!$parentObject && ($conditionsStatus == Mapping::STATUS_ENABLE) && ($this->currentMasterObject instanceof AbstractImportable)) {
            $this->currentMasterObject->finalizeImport($this->currentRecordCache);
            $this->currentMasterObject = null;
            $this->currentRecordCache  = null;

            $this->logger->log(
                sprintf('Pre-conditions not met (%s) for record "%s", skipping ...', $conditionsStatus, $importId),
                FlashMessage::WARNING
            );

            // Else: If there's no parent but a current master object
        } elseif (!$parentObject && ($this->currentMasterObject instanceof AbstractImportable)) {

            // Recursively call for child models
            $this->processPendentObjects(
                $extensionKey,
                $record,
                $modelConfig,
                $recordCache,
                $level + 1,
                $this->currentMasterObject
            );

            // Else: Log a skip message
        } else {
            $this->logger->log(
                sprintf('Pre-conditions not met (%s) for record "%s", skipping ...', $conditionsStatus, $importId),
                FlashMessage::WARNING
            );
        }

        return strval($importId);
    }

    /**
     * Process pendent objects
     *
     * @param string $extensionKey             Extension key
     * @param array $record                    Temporary record
     * @param array $modelConfig               Model configuration
     * @param array $recordCache               Internal record cache
     * @param int $level                       Indentation level
     * @param AbstractImportable $parentObject Parent object
     *
     * @throws ErrorException
     * @throws IllegalObjectTypeException
     * @throws ReflectionException
     * @throws UnknownObjectException
     */
    protected function processPendentObjects(
        string $extensionKey,
        array $record,
        array $modelConfig,
        array $recordCache,
        int $level,
        AbstractImportable $parentObject
    ) {
        if (!empty($modelConfig['children']) && is_array($modelConfig['children'])) {
            foreach ($modelConfig['children'] as $childModelClass => $childModelConfig) {
                $this->importTemporaryRecord(
                    $extensionKey,
                    $record,
                    $childModelClass,
                    $childModelConfig,
                    $recordCache,
                    $level + 1,
                    $parentObject
                );
            }
        }
    }

    /**
     * Import a simple temporary record
     *
     * @param string $extensionKey             Extension key
     * @param array $record                    Translatable temporary record
     * @param string $modelClass               Model class
     * @param array $modelConfig               Model configuration
     * @param int $level                       Indentation level
     * @param string $importId                 Unique import identifier
     * @param int $sysLanguage                 System language (-1 for all languages / non-translatable)
     * @param string $langSuffix               Language suffix
     * @param AbstractImportable $parentObject Parent object
     *
     * @return AbstractImportable Imported object
     * @throws ErrorException
     * @throws ReflectionException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
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
        list($object, $objectStatus) = $this->objectManager->get(ItemUtility::class)->findOrCreateImportableObject(
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
            $level)) {
            // Persist the updated object
            $this->objectManager->get(ItemUtility::class)->update($modelClass, $modelConfig, $object);
        }

        // Optionally register with the parent object
        if ($parentObject instanceof AbstractImportable) {
            $this->objectManager->get(ItemUtility::class)
                                ->registerWithParentObject($object, $modelConfig, $parentObject);
        }

        return $object;
    }

    /**
     * Get an extension mapping
     *
     * @param string $extensionKey Extension key
     *
     * @return array Extension mapping
     * @throws ErrorException
     */
    protected function getMapping($extensionKey)
    {
        if (!array_key_exists($extensionKey, self::$mappings)) {
            self::$mappings[$extensionKey] = $this->mappingUtility->getMapping($extensionKey);
        }

        return self::$mappings[$extensionKey];
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
     * Inject the database utility
     *
     * @param Database $dbUtility Database utility
     */
    public function injectDbUtility(Database $dbUtility)
    {
        $this->dbUtility = $dbUtility;
    }

    /**
     * Import a translatable temporary record
     *
     * @param string $extensionKey Extension key
     * @param array $record        Translatable temporary record
     * @param string $modelClass   Model class
     * @param array $modelConfig   Model configuration
     * @param int $level           Indentation level
     *
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
}
