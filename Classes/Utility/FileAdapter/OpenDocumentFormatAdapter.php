<?php

/**
 * Fischer Automobile
 *
 * @category   Jkphl
 * @package    Jkphl\Rdfalite
 * @subpackage Tollwerk\TwImporter\Utility\FileAdapter
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

namespace Tollwerk\TwImporter\Utility\FileAdapter;

use ErrorException;
use Tollwerk\TwImporter\Utility\File\OpenDocumentFormatFile;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Open Document Format (ODF) adapter
 */
class OpenDocumentFormatAdapter extends AbstractFileAdapter
{
    /**
     * File utility
     *
     * @var OpenDocumentFormatFile
     */
    protected $fileUtility;

    /**
     * Adapter name
     *
     * @var string
     */
    const NAME = 'ods';

    /**
     * Import a file
     *
     * @param string $extensionKey Extension key
     * @param string $importFile   Optional: Import file
     *
     * @return int Number of imported records
     * @throws ErrorException
     */
    public function import(string $extensionKey, string $importFile = null): int
    {
        // Find import directory
        $importDirectory = $this->fileUtility->validateDirectory(self::BASE_DIRECTORY.$extensionKey);
        $this->logger->log('Found import directory: '.$importDirectory, FlashMessage::NOTICE);

        // Get the path to the XML of the import .ods file
        $importFile = $this->fileUtility->getImportFile($importDirectory);
        $this->logger->log('Found valid import file: '.$importFile, FlashMessage::NOTICE);

        // Get Mapping
        $mapping = $this->mappingUtility->getMapping($extensionKey);
        $this->logger->log('Found valid mapping', FlashMessage::NOTICE);

        // Prepare temporary import table
        $importTableName = $this->dbUtility->prepareTemporaryImportTable($extensionKey, $mapping);
        $this->logger->log('Created temporary import table "'.$importTableName.'"', FlashMessage::NOTICE);

        // Parse the import file into the prepared temporary import table
        $this->logger->log('Reading import file ...', FlashMessage::OK);
        $skippedColumns = [];
        $rowCount       = $this->fileUtility->processFile(
            $extensionKey,
            $importFile,
            $mapping,
            $this->dbUtility,
            $skippedColumns
        );

        if (count($skippedColumns)) {
            foreach ($skippedColumns as $skippedColumn) {
                $this->logger->log('Skipped column: '.$skippedColumn, FlashMessage::INFO);
            }
        }

        return $rowCount;
    }

    /**
     * Inject the Open Document Format file utility
     *
     * @param OpenDocumentFormatFile $fileUtility Open Document Format file utility
     */
    public function injectFileUtility(OpenDocumentFormatFile $fileUtility)
    {
        $this->fileUtility = $fileUtility;
        $this->fileUtility->setConfig($this->config);
    }
}
