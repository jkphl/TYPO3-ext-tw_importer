<?php

/**
 * Fischer Automobile
 *
 * @category Jkphl
 * @package Jkphl\Rdfalite
 * @subpackage Tollwerk\TwImporter\Utility\FileAdapter
 * @author Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @copyright Copyright © 2017 Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
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
     * @return int Number of imported records
     */
    public function import($extensionKey)
    {
        // Find import directory
        $importDirectory = $this->fileUtility->validateDirectory(self::BASE_DIRECTORY.$extensionKey);
        $this->logger->log('Found import directory: '.$importDirectory);

        // Get the path to the XML of the import .ods file
        $importFile = $this->fileUtility->getImportFile($importDirectory);
        $this->logger->log('Found valid import file: '.$importFile);

        // Get Mapping
        $mapping = $this->mappingUtility->getMapping($extensionKey);
        $this->logger->log('Found valid mapping');

        // Prepare temporary import table
        $importTableName = $this->dbUtility->prepareTemporaryImportTable($extensionKey, $mapping);
        $this->logger->log('Created temporary import table: '.$importTableName);

        // Parse the import file into the prepared temporary import table
        $this->logger->log('Reading import file according to mapping and insert records', FlashMessage::NOTICE);
        $skippedColumns = [];
        $rowCount = $this->fileUtility->processFile(
            $extensionKey,
            $importFile,
            $mapping,
            $this->dbUtility,
            $skippedColumns
        );

        if (count($skippedColumns)) {
            foreach ($skippedColumns as $skippedColumn) {
                $this->logger->log('Skipped column: '.$skippedColumn, FlashMessage::WARNING);
            }
        }

        return $rowCount;
    }

    /**
     * @param string $extensionKey
     * @param array $record
     * @param array $hierarchy
     */
    protected function _importRecords($extensionKey, $record, $hierarchy, $registryLevel = 0)
    {
        $flashMessageSpacer = '';
        for ($i = 0; $i < $registryLevel; $i++) {
            $flashMessageSpacer .= '--- ';
        }


        $objectClass = key($hierarchy);
        $objectConf = $hierarchy[$objectClass];
        $importIdField = array_key_exists('importIdField',
            $objectConf) ? $objectConf['importIdField'] : 'tx_twimporter_id';

        $importId = $record[$importIdField];

        // Check the field conditions for this hierarchy, skip if not ok
        if (!$this->mappingUtility->checkHierarchyConditions($record, $objectConf)) {
            $this->logger->log(
                $importIdField.': '.$record[$importIdField].' | Field conditions do not fit for current class '.$objectClass.', moving on.. ',
                '',
                FlashMessage::WARNING
            );
        } else {
            foreach ($this->languageSuffices as $sysLanguage => $languageSuffice) {

                // Or createOrGet by parent or by importId
                if (array_key_exists('parentFindChild', $objectConf)) {
                    $objectFoundByParent = $this->objectUtility->getByParent($record, $objectConf, $registryLevel,
                        $sysLanguage);
                }

                if ($objectFoundByParent instanceof \Tollwerk\TwImporter\Domain\Model\AbstractImportable) {
                    $object = $objectFoundByParent;
                    $objectStatus = 'found by parent: uid: '.$object->getUid().' '.get_class($object);
                } else {
                    $objectFoundOrCreated = $this->objectUtility->createOrGet($hierarchy, $importId, $sysLanguage,
                        $registryLevel, $record);
                    /**
                     * @var \Tollwerk\TwImporter\Domain\Model\AbstractImportable $object
                     */
                    $object = $objectFoundOrCreated['object'];
                    $objectStatus = $objectFoundOrCreated['status'];
                }


                // Call set or update properties of object
                // ---------------------------------------
                if ($registryLevel == 0) {
                    $object->prepareImport(
                        $record,
                        $this->mappingUtility->getMapping($extensionKey),
                        $suffix
                    );
                }

                $object->import(
                    $record,
                    $this->mappingUtility->getMapping($extensionKey),
                    $languageSuffice,
                    $this->languageSuffices
                );

                $this->objectUtility->update($hierarchy, $object);
                $this->persistenceManager->persistAll();
                $this->logger->log($flashMessageSpacer.'importId: '.$importId.' | language: '.$languageSuffice.' | object: '.$objectClass.' | uid: '.$object->getUid().' | status: '.$objectStatus);

                // Add this object to the $parents array
                // and try to add current object to a parent, if available
                // ---------------------------------------------------
                $this->objectUtility->addParentToRegistry($object, $registryLevel, $sysLanguage);
                $this->objectUtility->addChildToParent($object, $objectConf, $registryLevel, $sysLanguage);
            }
        }

        // Call _importRecords recursively for all children
        // ------------------------------------------------
        foreach ($objectConf['children'] as $key => $childObjectConf) {
            $childObjectClass = $childObjectConf['class'];
            $this->_importRecords($extensionKey, $record, array($childObjectClass => $childObjectConf),
                ($registryLevel + 1));
        }
    }

    /**
     * Inject the Open Document Format file utility
     *
     * @param OpenDocumentFormatFile $fileUtility Open Document Format file utility
     */
    public function injectFileUtility(OpenDocumentFormatFile $fileUtility)
    {
        $this->fileUtility = $fileUtility;
    }
}
