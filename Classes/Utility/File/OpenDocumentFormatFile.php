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

namespace Tollwerk\TwImporter\Utility\File;

use Tollwerk\TwImporter\Utility\Database;
use Tollwerk\TwImporter\Utility\File\AbstractFile;

/**
 * Open Document Format file
 */
class OpenDocumentFormatFile extends AbstractFile
{
    /**
     * Return the import file path
     *
     * Reads the first usable .ods file inside the import $directory,
     * creates a temporary XML file and returns the corresponding path
     *
     * @param string $directory Import directory
     * @return string Import file path
     * @throws \ErrorException If there's no suitable file in the directory
     * @throws \ErrorException If the import file is invalid
     */
    public function getImportFile($directory)
    {
        // Get all available .ods files in the $directory
        $importFiles = glob($directory.DIRECTORY_SEPARATOR.'*.ods');

        // If there's no suitable file in the directory
        if (!count($importFiles)) {
            throw new \ErrorException('No import file available. Quitting');
        }
        $importFile = $importFiles[0];

        // Get xml of the first found .ods file
        // TODO: Include loop for skipping invalid files, see \TwBlog\Command\ImportCommandController, line 345 (foreach($impoartFiles as $importFile)
        $dataXMLFile = $this->_processODSFile($importFile);

        // If the import file is invalid
        if (!$dataXMLFile) {
            throw new \ErrorException('The found import file '.$importFile.' could not be parsed to XML.');
        }

        return $dataXMLFile;
    }

    /**
     * Process the import file
     *
     * @param string $extensionKey Extension key
     * @param string $filePath File path
     * @param array $mapping Column mapping
     * @param Database $database Database utility
     * @param array $skippedColumns Skipped columns (set by reference)
     * @return int Number of imported records
     */
    public function processFile($extensionKey, $filePath, $mapping, Database $database, &$skippedColumns = [])
    {
        $columnHeaders = null;
        $document = new \DOMDocument;
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $reader = new \XMLReader();
        $reader->open($filePath);
        $rowCount = 0;

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
                    $columnHeaders = [];
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
                    if (strlen(trim(implode('', $record))) && $database->insertRow($extensionKey, $record)) {
                        ++$rowCount;
                    }
                }
            }

            $reader->next('table-row');
        }

        return $rowCount;
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
            $this->_tmpFiles[] = $tmpfile = tempnam(sys_get_temp_dir(), 'blog_');
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
        $cells = [];
        $columnIndex = 0;

        // Run through all cells
        foreach ($xpath->query('table:table-cell', $row) as $cell) {
            $cellValue = [];
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
