<?php

/**
 * Fischer Automobile
 *
 * @category   Jkphl
 * @package    Jkphl\Rdfalite
 * @subpackage Tollwerk\TwImporter\Utility\File
 * @author     Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @copyright  Copyright © 2017 Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @license    http://opensource.org/licenses/MIT The MIT License (MIT)
 */

/***********************************************************************************
 *  The MIT License (MIT)
 *
 *  Copyright © 2017 Joschi Kuphal <joschi@kuphal.net> / @jkphl
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of
 *  this software and associated documentation files (the "Software"), to deal in
 *  the Software without restriction, including without limitation the rights to
 *  use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 *  the Software, and to permit persons to whom the Software is furnished to do so,
 *  subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *  COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 *  IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 *  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 ***********************************************************************************/

namespace Tollwerk\TwImporter\Utility\File;

use Tollwerk\TwImporter\Utility\Database;
use Tollwerk\TwImporter\Utility\EncodingFilter;

/**
 * CSV file
 */
class CsvFile extends AbstractFile
{
    /**
     * Return the import file path
     *
     * Returns the first usable .csv file inside the import $directory
     *
     * @param string $directory       Import directory
     * @param string|null $importFile Optional: Import File
     *
     * @return string Import file path
     * @throws \ErrorException If there's no suitable file in the directory
     * @throws \ErrorException If the import file is invalid
     */
    public function getImportFile($directory, string $importFile = null): string
    {
        // Get all available .ods files in the $directory
        $importFiles = glob($directory.DIRECTORY_SEPARATOR.'*.csv');

        // If there's no suitable file in the directory
        if (!count($importFiles)) {
            throw new \ErrorException('No import file available. Quitting');
        }

        return $importFiles[0];
    }

    /**
     * Process the import file
     *
     * @param string $extensionKey  Extension key
     * @param string $filePath      File path
     * @param array $mapping        Column mapping
     * @param Database $database    Database utility
     * @param array $skippedColumns Skipped columns (set by reference)
     *
     * @return int Number of imported records
     */
    public function processFile($extensionKey, $filePath, $mapping, Database $database, &$skippedColumns = [])
    {
        $rowCount  = 0;
        $columns   = isset($this->config['columns']) ? (array)$this->config['columns'] : null;
        $delimiter = isset($this->config['delimiter']) ? $this->config['delimiter'] : ',';
        $enclosure = isset($this->config['enclosure']) ? $this->config['enclosure'] : '"';
        $escape    = isset($this->config['escape']) ? $this->config['escape'] : '\\';
        $headers   = !empty($this->config['headers']);
        $csvHandle = fopen($filePath, 'r');

        // If the file can be opened
        if ($csvHandle !== false) {
            // Install an encoding stream filter if necessary
            if (isset($this->config['encoding']) && (strtolower($this->config['encoding']) != 'utf-8')) {
                $encodingClassName       = 'EncodingFilter'.preg_replace('/[^a-z0-9]/i', '', $this->config['encoding']);
                $encodingClassDefinition = 'class '.$encodingClassName.' extends '.EncodingFilter::class.'{protected $encoding = \''.$this->config['encoding'].'\';}';
                eval($encodingClassDefinition);

                stream_filter_register($encodingClassName, $encodingClassName);
                stream_filter_prepend($csvHandle, $encodingClassName);
            }

            // Run through all rows
            while (($row = fgetcsv($csvHandle, 1048576, $delimiter, $enclosure, $escape)) !== false) {

                // If there are no columns defined: Consume the first row for them
                if ($columns === null) {
                    $columns = $row;
                    continue;

                    // Else if the first row should be treated as column headers (and thus be skipped)
                } elseif ($headers) {
                    $headers = false;
                    continue;
                }

                $record = [];
                foreach ($columns as $columnIndex => $columnName) {
                    if (strlen($columnName)) {
                        $record[$columnName] = $row[$columnIndex];
                    }
                }

                // Exclude empty rows
                if (strlen(trim(implode('', $record))) && $database->insertRow($extensionKey, $record)) {
                    ++$rowCount;
                }
            }
            fclose($csvHandle);
        }

        return $rowCount;
    }
}
