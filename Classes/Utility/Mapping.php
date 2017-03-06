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

/**
 * Mapping utility
 *
 * @todo Invalid checks: accessing non-existing array keys doesn't throw an exception
 */
class Mapping
{
    /**
     * Return the file adapter for a particular extension
     *
     * @param string $extensionKey Extension key
     * @return string File adapter
     * @throws \ErrorException If the mapping doesn't exist
     * @throws \ErrorException If the mapping is empty
     */
    public function getAdapter($extensionKey)
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['adapter'])) {
            throw new \ErrorException("Could not get file adapter. Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['adapter'] in your ext_localconf.php");
        }

        $adapter = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['adapter'];

        if (!strlen($adapter)) {
            throw new \ErrorException("The file adapter is empty! Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['adapter'] in your ext_localconf.php");
        }

        return [
            'name' => $adapter,
            'config' => isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['config']) ?
                (array)$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['config'] : [],
        ];
    }

    /**
     * Return the mapping for a particular extension
     *
     * @param string $extensionKey Extension key
     * @return array Mapping
     * @throws \ErrorException If the mapping doesn't exist
     * @throws \ErrorException If the mapping is empty
     */
    public function getMapping($extensionKey)
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['mapping'])) {
            throw new \ErrorException("Could not get mapping. Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['mapping'] in your ext_localconf.php");
        }

        $mapping = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['mapping'];

        if (!count($mapping)) {
            throw new \ErrorException("The mapping is empty! Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['mapping'] in your ext_localconf.php");
        }

        return $mapping;
    }

    /**
     * Return the mapping hierarchy
     *
     * @param string $extensionKey Extension key
     * @return array Mapping hierarchy
     * @throws \ErrorException If the mapping hierarchy doesn't exist
     * @throws \ErrorException If the mapping hierarchy is empty
     */
    public function getHierarchy($extensionKey)
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['hierarchy'])) {
            throw new \ErrorException("Could not get hierarchy. Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['hierarchy'] in your ext_localconf.php");
        }

        $hierarchy = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['hierarchy'];

        if (!count($hierarchy)) {
            throw new \ErrorException("The hierarchy is empty! Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['hierarchy'] in your ext_localconf.php");
        }

        return $hierarchy;
    }

    /**
     * Return the list of repositories to purge after an import
     *
     * @param string $extensionKey Extension key
     * @return array Repositories to purge
     */
    public function getPurgeRepositories($extensionKey)
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['purge']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['purge'] : [];
    }

    /**
     * Check the hierarchy pre-conditions
     *
     * @param array $record Record
     * @param array $objectConf Object configuration
     * @return boolean Hierarchy pre-conditions match
     */
    public function checkHierarchyConditions($record, $objectConf)
    {
        // Check all fields that must be set
        $mustBeSet = isset($objectConf['conditions']['mustBeSet']) ? $objectConf['conditions']['mustBeSet'] : null;
        if (is_array($mustBeSet) && count($mustBeSet)) {
            foreach ($mustBeSet as $fieldname) {
                if (empty($record[$fieldname])) {
                    return false;
                }
            }
        }

        // Check all fields that must be empty
        $mustBeEmpty = isset($objectConf['conditions']['mustBeEmpty']) ? $objectConf['conditions']['mustBeEmpty'] : null;
        if (is_array($mustBeEmpty) && count($mustBeEmpty)) {
            foreach ($mustBeEmpty as $fieldname) {
                if (!empty($record[$fieldname])) {
                    return false;
                }
            }
        }

        return true;
    }
}
