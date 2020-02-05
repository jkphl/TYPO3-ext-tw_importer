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

/**
 * File interface
 */
interface FileInterface
{
    /**
     * Return the import file path
     *
     * @param string $directory       Import directory
     * @param string|null $importFile Optional: Import File
     *
     * @return string Import file path
     * @throws \ErrorException If the import file is invalid
     */
    public function getImportFile($directory, string $importFile = null): string;

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
    public function processFile($extensionKey, $filePath, $mapping, Database $database, &$skippedColumns = []);

    /**
     * Set the adapter configuration
     *
     * @param array $config Adapter configuration
     */
    public function setConfig(array $config);
}
