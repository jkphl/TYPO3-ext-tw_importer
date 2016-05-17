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

class ImportData
{
    public function createBundles($extensionKey)
    {
        return TRUE;

        $success = true;
        $records = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            '*',
            \Tollwerk\TwImporter\Utility\Database::getTableName($extensionKey),
            ''
        );

        if ($records) {
            $bundle = array();

            // Run through all records
            while ($record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($records)) {

                $sku = intval(trim($record['sku']));
                if ($sku) {
                    // If it's a primary facsimile
                    $isPage = (($sku >= 100) && ($sku % 100 == 0));
                    if ($isPage) {

                        // If there's a pending bundle
                        if (count($bundle)) {
                            $success = $this->_importPageAndContent($bundle) && $success;
                        }

                        $bundle = array();
                    }

                    $bundle[] = $record;
                }
            }

            // If there's a pending bundle
            if (count($bundle)) {
                $success = $this->_importPageAndContent($bundle) && $success;
            }

            $GLOBALS['TCA']['tx_twfacsimile_domain_model_price']['ctrl']['languageField'] = $languageField;
        }

        return $success;
    }
}