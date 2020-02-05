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

use FluidTYPO3\Vhs\ViewHelpers\DebugViewHelper;
use Tollwerk\TwImporter\Utility\FileAdapter\CsvAdapter;
use Tollwerk\TwImporter\Utility\FileAdapter\ExcelAdapter;
use Tollwerk\TwImporter\Utility\FileAdapter\FileAdapterInterface;
use Tollwerk\TwImporter\Utility\FileAdapter\OpenDocumentFormatAdapter;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * File adapter factory
 */
class FileAdapterFactory
{
    /**
     * Object manager
     *
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * Registered file adapters
     *
     * @var array
     */
    protected static $fileAdapters = [
        OpenDocumentFormatAdapter::NAME => OpenDocumentFormatAdapter::class,
        CsvAdapter::NAME => CsvAdapter::class,
        ExcelAdapter::NAME => ExcelAdapter::class,
    ];

    /**
     * Registered file adapters
     *
     * @var array
     */
    protected static $fileExcelAdapters = [
        ExcelAdapter::NAME => ExcelAdapter::class,
    ];

    /**
     * Instantiate a file adapter by name
     *
     * @param array $nameConfig File adapter name and configuration
     * @param LoggerInterface $logger Logger
     * @return FileAdapterInterface File adapter
     * @throws If the file adapter name is unknown
     */
    public function getAdapterByName(array $nameConfig, LoggerInterface $logger)
    {
        //$nameConfig array mapping.php
        $name = trim($nameConfig['name']);

        // If the file adapter name is unknown
        if (!strlen($name) || !isset(self::$fileAdapters[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown file adapter "%s"', $name), 1488382399);
        }

        return $this->objectManager->get(self::$fileAdapters[$name], $nameConfig['config'], $logger);
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
}
