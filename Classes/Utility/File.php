<?php

namespace Tollwerk\TwImporter\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Klaus Fiedler <klaus@tollwerk.de>, tollwerk GmbH
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

use \TYPO3\CMS\Core\Utility\GeneralUtility;

class File
{
    /**
     * Expand and validate a directory path
     *
     * @param \string $directory Directory path
     * @throws \InvalidArgumentException    When the given path is not a valid directory
     * @return \string                        Expanded and validated (absolute) directory path
     */
    public function validateDirectory($directory)
    {
        $directory = trim(trim($directory, DIRECTORY_SEPARATOR));
        if (!strlen($directory)) {
            throw new \InvalidArgumentException('Empty directory name is not allowed');
        }
        $directory = GeneralUtility::getFileAbsFileName($directory);
        if (!@is_dir($directory)) {
            throw new \InvalidArgumentException(sprintf('Invalid directory "%s" (does not exist)', $directory));
        }
        if (!@is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf('Invalid directory "%s" (is not writable)', $directory));
        }
        return $directory;
    }

    /**
     * Reads the first usable .ods file inside the import $directory,
     * creates a temporary XML file and returns the corresponding path
     *
     * @param $directory
     * @return bool|null|string
     * @throws \ErrorException
     */
    public function getImportFile($directory)
    {
        // Get all available .ods files in the $directory
        $importFiles = glob($directory.DIRECTORY_SEPARATOR.'*.ods');
        if (!count($importFiles)) {
            throw new \ErrorException('No import file available. Quitting');
        }
        $importFile = $importFiles[0];

        // Get xml of the first found .ods file
        // TODO: Include loop for skipping invalid files, see \TwBlog\Command\ImportCommandController, line 345 (foreach($impoartFiles as $importFile)
        $dataXMLFile = $this->_processODSFile($importFile);

        if (!$dataXMLFile) {
            throw new \ErrorException('The found import file '.$importFile.' could not be parsed to XML.');
        }

        return $dataXMLFile;
    }

    /**
     * @param string $filePath
     * @param array $mapping
     * @param array $skippedColumns If you want to know which columns where skipped because they are not mapped, use this array (will be called by reference!)
     *
     * return $array
     */
    public function processXMLFile($filePath, $mapping, &$skippedColumns = array())
    {
        $columnHeaders = null;
        $document = new \DOMDocument;
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $reader = new \XMLReader();
        $reader->open($filePath);
        $rowsToImport = array();

        // Skip all non-rows
        while ($reader->read() && ($reader->name !== 'table:table-row')) {
            ;
        }

        // Run through all rows
        while ($reader->name == 'table:table-row') {
            $columns = $this->_importXMLRow($document->importNode($reader->expand(), true), $xpath);
            if (count($columns)) {

                // If this is the header row
                if ($columnHeaders === null) {
                    $columnHeaders = array();
                    foreach ($columns as $columnIndex => $columnName) {
                        if (array_key_exists($columnName, $mapping)) {
                            $columnHeaders[$columnIndex] = $columnName;
                        } else {
                            $skippedColumns[] = $columnName.' (Not part of the mapping)';
                        }
                    }

                    // Else: data row
                } else {

                    $mappingKeys = array_keys($mapping);
                    $record = array_fill_keys($mappingKeys, '');

                    // Run through all columns
                    foreach ($columns as $columnIndex => $columnValue) {

                        // If this column can be mapped
                        if (array_key_exists($columnIndex, $columnHeaders)) {
                            $record[$columnHeaders[$columnIndex]] = $columnValue;
                        }
                    }

                    // Exclude empty rows
                    if (strlen(trim(implode('', $record)))) {
                        $rowsToImport[] = $record;
                        // $insertedRows += (!!$GLOBALS['TYPO3_DB']->exec_INSERTquery('temp_import_blog', $record) * 1);
                    }
                }
            }

            $reader->next('table-row');
        }

        return $rowsToImport;

    }

    /**
     * Returns the xml of a .ods file
     *
     * @param $file
     * @return bool|null|string
     */
    protected function _processODSFile($file)
    {
        if (@filesize($file) && ($zip = new \ZipArchive()) && ($zip->open($file) === true) && strlen($data = $zip->getFromName('content.xml'))) {
            $this->_tmpFiles[] =
            $tmpfile = tempnam(sys_get_temp_dir(), 'blog_');
            return file_put_contents($tmpfile, $data) ? $tmpfile : false;
        } else {
            return null;
        }
    }

    /**
     * Import an XML row and translate it to columns
     *
     * @param \DOMElement $row Row element
     * @param \DOMXPath $xpath XPath processor
     * @return \array                    Array
     */
    protected function _importXMLRow(\DOMElement $row, \DOMXPath $xpath)
    {
        $cells = array();
        $columnIndex = 0;

        // Run through all cells
        foreach ($xpath->query('table:table-cell', $row) as $cell) {
            $cellValue = array();
            foreach ($xpath->query('text:p', $cell) as $paragraph) {
                $cellValue[] = $paragraph->textContent;
            }
            $cellValue = trim(implode(PHP_EOL, $cellValue));

            if (strlen($cellValue)) {
                $cells[$columnIndex] = $cellValue;
            }
            if ($cell->hasAttribute('table:number-columns-repeated')) {
                $repetitions = intval($cell->getAttribute('table:number-columns-repeated'));

                if ($repetitions && strlen($cellValue)) {
                    for ($repetition = 1; $repetition < $repetitions; ++$repetition) {
                        $cells[$columnIndex + $repetition] = $cellValue;
                    }
                }

                $columnIndex += max(1, $repetitions);
            } else {
                ++$columnIndex;
            }
        }

        return $cells;
    }

}
