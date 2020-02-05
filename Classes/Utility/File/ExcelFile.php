<?php
/**
 * Ziereis Relaunch
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwImporter
 * @subpackage Tollwerk\TwImporter\Utility\File
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

namespace Tollwerk\TwImporter\Utility\File;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tollwerk\TwImporter\Utility\Database;

/**
 * ExcelFile
 *
 * @package    Tollwerk\TwImporter
 * @subpackage Tollwerk\TwImporter\Utility\File
 */
class ExcelFile extends AbstractFile
{
    /**
     * Return the import file path
     *
     * Returns the first usable .xslx file inside the import $directory
     *
     * @param string $directory       Import directory
     * @param string|null $importFile Optional: Import File
     *
     * @return string Import file path
     * @throws \ErrorException If the import file is invalid
     */
    public function getImportFile($directory, string $importFile = null): string
    {
        if (strlen($importFile) && is_file($importFile)
            && (strtolower(pathinfo($importFile, PATHINFO_EXTENSION)) == 'xlsx')) {
            return $importFile;
        }

        // Get all available .ods files in the $directory
        $importFiles = glob($directory.DIRECTORY_SEPARATOR.'*.xlsx');

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
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function processFile($extensionKey, $filePath, $mapping, Database $database, &$skippedColumns = [])
    {
        $spreadsheet     = IOFactory::load($filePath);
        $affectedRows    = 0;
        $importSheetName = $this->config['importSheet'] ?: 'Import';
        $skipRows        = intval($this->config['skipRows'] ?: 0);
        $limitRows       = intval($this->config['limitRows'] ?: 0);
        $columnNameRow   = max(intval($this->config['columnNameRow'] ?: 1), 1);

        // If the import sheet exists in the file
        if ($spreadsheet->sheetNameExists($importSheetName)) {
            $importSheet        = $spreadsheet->getSheetByName($importSheetName);
            $highestColumnIndex = Coordinate::columnIndexFromString($importSheet->getHighestDataColumn());
            $highestRow         = $importSheet->getHighestDataRow();
            $firstRow           = $skipRows + 1;
            $lastRow            = $limitRows ? ($firstRow + $limitRows) : $highestRow;
            $columnNames        = [];

            // Collect the column names
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $columnName = $importSheet->getCellByColumnAndRow($col, $columnNameRow)->getValue();
                if (array_key_exists($columnName, $mapping)) {
                    $columnNames[$columnName] = $col;
                }
            }

            // Start from the first relevant row
            for ($row = $firstRow; $row <= $lastRow; ++$row) {
                $insertValues = [];
                foreach ($columnNames as $columnName => $col) {
                    $insertValues[$columnName] = $importSheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                }

                $affectedRows += $database->insertRow($extensionKey, $insertValues);
            }
        }

        return $affectedRows;
    }
}
