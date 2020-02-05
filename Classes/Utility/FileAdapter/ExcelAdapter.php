<?php
/**
 * Ziereis Relaunch
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwImporter
 * @subpackage Tollwerk\TwImporter\Utility\FileAdapter
 * @author     Jolanta Dworczyk <jolanta@tollwerk.de>
 * @copyright  Copyright © 2019 Jolanta Dworczyk <jolanta@tollwerk.de>
 * @license    https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/***********************************************************************************
 *  GNU GENERAL PUBLIC LICENSE (GPLv2, Version 2, June 1991)
 *
 *  Copyright © 2019 Jolanta Dworczyk <jolanta@tollwerk.de>
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; either version 2
 *  of the License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************************************/

namespace Tollwerk\TwImporter\Utility\FileAdapter;

use ErrorException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Tollwerk\TwImporter\Utility\File\ExcelFile;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Excel Adapter
 *
 */
class ExcelAdapter extends AbstractFileAdapter
{
    /**
     * Adapter name
     *
     * @var string
     */
    const NAME = 'excel';
    /**
     * File utility
     *
     * @var ExcelFile
     */
    protected $fileUtility;

    /**
     * Inject the CSV file utility
     *
     * @param ExcelFile $fileUtility CSV file utility
     */
    public function injectFileUtility(ExcelFile $fileUtility)
    {
        $this->fileUtility = $fileUtility;
        $this->fileUtility->setConfig($this->config);
    }

    /**
     * Import a file
     *
     * @param string $extensionKey Extension key
     * @param string $importFile   Optional: Import file
     *
     * @return int Number of imported records
     * @throws ErrorException
     * @throws SpreadsheetException
     * @throws Exception
     */
    public function import(string $extensionKey, string $importFile = null): int
    {
        // Find import directory
        $importDirectory = $this->fileUtility->validateDirectory(self::BASE_DIRECTORY.$extensionKey);
        $this->logger->log('Found import directory: '.$importDirectory, FlashMessage::NOTICE);

        $importFile = $this->fileUtility->getImportFile($importDirectory, $importFile);
        $this->logger->log('Found valid import file: '.$importFile, FlashMessage::NOTICE);

        // Get Mapping
        $mapping = $this->mappingUtility->getMapping($extensionKey);
        $this->logger->log('Found valid mapping', FlashMessage::NOTICE);

        // Prepare temporary import table
        $importTableName = $this->dbUtility->prepareTemporaryImportTable($extensionKey, $mapping);
        $this->logger->log('Created temporary import table "'.$importTableName.'"', FlashMessage::NOTICE);

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
}
