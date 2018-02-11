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

use TYPO3\CMS\Core\Database\DatabaseConnection;

/**
 * Database utility
 */
class Database
{
    /**
     * Database connection
     *
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * Database utility constructor
     */
    public function __construct()
    {
        $this->database =& $GLOBALS['TYPO3_DB'];
    }

    /**
     * Create and return the temporary database table name for import
     *
     * @param string $extensionKey Extension key
     * @return string Temporary table name
     */
    public static function getTableName($extensionKey)
    {
        return 'temp_import_'.$extensionKey;
    }

    /**
     * Create a temporary import table
     *
     * @param string $extensionKey Extension key
     * @param array $mapping Column mapping
     * @return string The name of the temporary table
     * @throws \ErrorException If the table cannot be created
     */
    public function prepareTemporaryImportTable($extensionKey, $mapping)
    {
        // Preparing temporary database table
        if (!$this->database->sql_query('DROP TABLE IF EXISTS `'.self::getTableName($extensionKey).'`')) {
            throw new \ErrorException('Couldn\'t prepare database (already exists)');
        }

        // If the table cannot be created
        if (!$this->database->sql_query(
            'CREATE TABLE `'.self::getTableName($extensionKey).'` ( `'.implode('` TEXT NULL DEFAULT NULL, `',
                array_keys($mapping)).'` TEXT NULL DEFAULT NULL) ENGINE = MyISAM')
        ) {
            throw new \ErrorException('Couldn\'t prepare database (cannot create table)');
        }

        return self::getTableName($extensionKey);
    }

    /**
     * Insert a row into the temporary table
     *
     * @param string $extensionKey Extension key
     * @param array $row Row data
     * @return mixed
     */
    public function insertRow($extensionKey, array $row)
    {
        $result = $this->database->exec_INSERTquery(self::getTableName($extensionKey), $row);
        return $result;
    }

    /**
     * Iterate over all temporary records
     *
     * @param string $extensionKey Extension key
     * @return \Generator Temporary records
     */
    public function getTemporaryRecords($extensionKey)
    {
        $temporaryRecordRes = $this->database->exec_SELECTquery('*', self::getTableName($extensionKey), '');
        if ($temporaryRecordRes) {
            while ($temporaryRecord = $this->database->sql_fetch_assoc($temporaryRecordRes)) {
                yield $temporaryRecord;
            }
        }
    }
}
