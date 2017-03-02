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

namespace Tollwerk\TwImporter\Utility\FileAdapter;

use Tollwerk\TwImporter\Utility\Database;
use Tollwerk\TwImporter\Utility\File\FileInterface;
use Tollwerk\TwImporter\Utility\ImportData;
use Tollwerk\TwImporter\Utility\LoggerInterface;
use Tollwerk\TwImporter\Utility\Mapping;
use Tollwerk\TwImporter\Utility\Object;
use Tollwerk\TwImporter\Utility\SysLanguages;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Abstract file adapter
 */
abstract class AbstractFileAdapter implements FileAdapterInterface
{
    /**
     * File utilty
     *
     * @var FileInterface
     */
    protected $fileUtility;

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
     * Import data utility
     *
     * @var ImportData
     */
    protected $importDataUtility;

    /**
     * System language utility
     *
     * @var SysLanguages
     */
    protected $sysLanguageUtility;

    /**
     * Object manager
     *
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * Persistence manager
     *
     * @var PersistenceManager
     */
    protected $persistenceManager = null;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Adapter name
     *
     * @var string
     */
    const NAME = 'abstract';
    /**
     * Import base directory
     *
     * @var string
     */
    const BASE_DIRECTORY = 'fileadmin'.DIRECTORY_SEPARATOR.'user_upload'.DIRECTORY_SEPARATOR.'import'.DIRECTORY_SEPARATOR;

    /**
     * Open document format constructor
     *
     * @param LoggerInterface $logger Logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return the adapter name
     *
     * @return string Adapter name
     */
    public static function getName()
    {
        return static::NAME;
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
     * Inject the mapping utility
     *
     * @param Mapping $mappingUtility Mapping utility
     */
    public function injectMappingUtility(Mapping $mappingUtility)
    {
        $this->mappingUtility = $mappingUtility;
    }

    /**
     * Inject the import data utility
     *
     * @param ImportData $importDataUtility Import data utility
     */
    public function injectImportDataUtility(ImportData $importDataUtility)
    {
        $this->importDataUtility = $importDataUtility;
    }

    /**
     * Inject the system language utility
     *
     * @param SysLanguages $sysLanguageUtility System language utility
     */
    public function injectSysLanguageUtility(SysLanguages $sysLanguageUtility)
    {
        $this->sysLanguageUtility = $sysLanguageUtility;
    }

    /**
     * Inject the object manager
     *
     * @param ObjectManager $objectManager Object manager
     */
    public function injectObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Inject the persistence manager
     *
     * @param PersistenceManager $persistenceManager Persistence manager
     */
    public function injectPersistenceManager(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }
}
