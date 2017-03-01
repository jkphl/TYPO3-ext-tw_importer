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
     * Import service constructor
     *
     * @param array $settings Settings
     * @param array $languageSuffices Language suffices
     */
    public function __construct(Array $settings, array $languageSuffices, LoggerInterface $logger)
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
        // Step 1: Prepare everything
        // --------------------------
        $this->logger->log('Starting import for extension key: '.$extensionKey, FlashMessage::NOTICE);

        // If there's no mapping for the given extension
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey])) {
            throw new \InvalidArgumentException(
                sprintf('No import configuration for "%s"', $extensionKey),
                1488382570
            );
        }

        $fileAdapterName = $this->mappingUtility->getAdapter($extensionKey);
        /** @var FileAdapterInterface $fileAdapter */
        $fileAdapter = $this->objectManager->get(FileAdapterFactory::class)->getAdapterByName(
            $fileAdapterName,
            $this->logger
        );
        $records = $fileAdapter->import($extensionKey);
        $this->logger->log('Inserted '.$records.' rows into temporary table');

        // Step 2: Import data into real table, create objects etc.
        // --------------------------------------------------------
//        $this->logger->log('Importing data from temporary table into extension', FlashMessage::NOTICE);
//        // TODO: implement filterRecords() (Flag for updating / not updating in import file etc.)
//        // TODO: move import file to archive before further processing (if(settings->archive))

//        $hierarchy = $this->mappingUtility->getHierarchy($extensionKey);
//        foreach ($records as $record) {
//            $this->_importRecords($extensionKey, $record, $hierarchy);
//        }

//        $this->logger->log('Gathering import results', FlashMessage::NOTICE);
//        foreach ($this->objectUtility->getUpdatedObjectsCounter() as $counterClass => $counterSum) {
//            $this->logger->log('Created / updated '.$counterSum.' objects of class '.$counterClass);
//        }
//        $this->logger->log('Done with import for extension key: '.$extensionKey);
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
}
