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

use Tollwerk\TwImporter\Controller\ImportController;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

class Mapping
{
    /**
     * @param string $extensionKey
     * @return array
     * @throws \ErrorException
     */
    public function getMapping($extensionKey){
        try{
            $mapping = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['mapping'];
        }catch(\Exception $ex){
            throw new \ErrorException("Could not get mapping. Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['mapping'] in your ext_localconf.php");
        }

        if(!count($mapping)){
            throw new \ErrorException("The mapping is empty! Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['mapping'] in your ext_localconf.php");
        }

        return $mapping;
    }

    /**
     * @param string $extensionKey
     * @return array
     * @throws \ErrorException
     */
    public function getHierarchy($extensionKey){
        try{
            $hierarchy = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'][$extensionKey]['hierarchy'];
        }catch(\Exception $ex){
            throw new \ErrorException("Could not get hierarchy. Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['hierarchy'] in your ext_localconf.php");
        }

        if(!count($hierarchy)){
            throw new \ErrorException("The hierarchy is empty! Check \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports']['".$extensionKey."']['hierarchy'] in your ext_localconf.php");
        }

        return $hierarchy;
    }

    /**
     * @param array $record
     * @param array $objectConf
     * @return bool
     */
    public function checkHierarchyConditions($record,$objectConf){

        $mustBeSet = $objectConf['conditions']['mustBeSet'];
        $mustBeEmpty = $objectConf['conditions']['mustBeEmpty'];

        // Check all fields that must be set
        if(is_array($mustBeSet) && count($mustBeSet)){
            foreach($mustBeSet as $key => $fieldname){
                if(empty($record[$fieldname])){
                    return FALSE;
                }
            }
        }
        
        // Check all fields that must be empty
        if(is_array($mustBeEmpty) && count($mustBeEmpty)){
            foreach($mustBeEmpty as $key => $fieldname){
                if(!empty($record[$fieldname])){
                    return FALSE;
                }
            }
        }
        
        return TRUE;
    }
}