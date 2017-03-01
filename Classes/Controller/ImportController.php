<?php namespace Tollwerk\TwImporter\Controller;

use Tollwerk\TwImporter\Utility\SysLanguages;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
class ImportController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    const BASE_DIRECTORY = 'fileadmin' . DIRECTORY_SEPARATOR . 'user_upload' . DIRECTORY_SEPARATOR . 'tw_importer';

    /**
     * @var Array
     */
    protected $settings;

    /**
     * @var \Tollwerk\TwImporter\Utility\File
     * @inject
     */
    protected $fileUtility;

    /**
     * @var \Tollwerk\TwImporter\Utility\Database
     * @inject
     */
    protected $dbUtility;

    /**
     * @var \Tollwerk\TwImporter\Utility\Mapping
     * @inject
     */
    protected $mappingUtility;

    /**
     * @var \Tollwerk\TwImporter\Utility\ImportData
     * @inject
     */
    protected $importDataUtility;

    /**
     * @var \Tollwerk\TwImporter\Utility\SysLanguages
     * @inject
     */
    protected $sysLanguageUtility;

    /**
     * @var \Tollwerk\TwImporter\Utility\Object
     * @inject
     */
    protected $objectUtility;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     * @inject
     */
    protected $objectManager = NULL;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @inject
     */
    protected $persistenceManager = NULL;

    /**
     * @var array
     */
    protected $languageSuffices = NULL;


    /************************************************************************************************
     * PROTECTED METHODS
     ***********************************************************************************************/

    /**
     * @var string $extensionKey
     * @return int The number of rows inserted into the temporary import table
     */
    protected function _createAndFillImportTable($extensionKey)
    {
        // Find import directory
        $importDirectory = $this->fileUtility->validateDirectory(self::BASE_DIRECTORY . DIRECTORY_SEPARATOR . $extensionKey);
        $this->flashMessage('Found import directory: ' . $importDirectory);

        // Get the path to the XML of the import .ods file
        $importFileAsXMLPath = $this->fileUtility->getImportFile($importDirectory);
        $this->flashMessage('Found a valid import file. Parsed it to XML.');

        // Get Mapping
        $mapping = $this->mappingUtility->getMapping($extensionKey);
        $this->flashMessage('Found valid mapping');

        // Prepare temporary import table
        $importTableName = $this->dbUtility->prepareTempImportTable($extensionKey, $mapping);
        $this->flashMessage('Created temporary import table, name: ' . $importTableName);

        // Parse the import file into the prepared temporary import table
        $this->flashMessage('Reading import file according to mapping and insert records', '', FlashMessage::NOTICE);
        $skippedColumns = array();
        $rowsToImport = $this->fileUtility->processXMLFile($importFileAsXMLPath, $mapping, $skippedColumns);

        if (count($skippedColumns)) {
            foreach ($skippedColumns as $skippedColumn) {
                $this->addFlashMessage('Skipped column: ' . $skippedColumn, '', FlashMessage::WARNING);
            }
        }


        // Insert rows into temporary table
        $rowsInserted = array();
        foreach ($rowsToImport as $row) {
            if ($this->dbUtility->insertRow($extensionKey, $row)) {
                $rowsInserted[] = $row;
            }
        }

        return $rowsInserted;
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
        $importIdField = array_key_exists('importIdField', $objectConf) ? $objectConf['importIdField'] : 'tx_twimporter_id';

        $importId = $record[$importIdField];

        // Check the field conditions for this hierarchy, skip if not ok
        if (!$this->mappingUtility->checkHierarchyConditions($record, $objectConf)) {
            $this->flashMessage(
                $importIdField . ': ' . $record[$importIdField] . ' | Field conditions do not fit for current class ' . $objectClass . ', moving on.. ',
                '',
                FlashMessage::WARNING
            );
        } else {

            foreach ($this->languageSuffices as $sysLanguage => $languageSuffice) {

 
               // Or createOrGet by parent or by importId
                if(array_key_exists('parentFindChild',$objectConf)){
                    $objectFoundByParent = $this->objectUtility->getByParent($record, $objectConf, $registryLevel,$sysLanguage);
                }

                if($objectFoundByParent instanceof  \Tollwerk\TwImporter\Domain\Model\AbstractImportable){
                    $object = $objectFoundByParent;
                    $objectStatus = 'found by parent: uid: '.$object->getUid().' '.get_class($object);
                }else{
                    $objectFoundOrCreated = $this->objectUtility->createOrGet($hierarchy, $importId, $sysLanguage,$registryLevel,$record);
                    /**
                     * @var \Tollwerk\TwImporter\Domain\Model\AbstractImportable $object
                     */
                    $object = $objectFoundOrCreated['object'];
                    $objectStatus = $objectFoundOrCreated['status'];
                }


                // Call set or update properties of object
                // ---------------------------------------
                if($registryLevel == 0){
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
                $this->flashMessage($flashMessageSpacer . 'importId: ' . $importId . ' | language: ' . $languageSuffice . ' | object: ' . $objectClass . ' | uid: ' . $object->getUid() . ' | status: ' . $objectStatus);

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
            $this->_importRecords($extensionKey, $record, array($childObjectClass => $childObjectConf), ($registryLevel + 1));
        }


    }


    /************************************************************************************************
     * PUBLIC METHODS
     ***********************************************************************************************/

    /**
     * Get module settings and stuff at controller initialization
     */
    public function initializeAction()
    {
        // Get typoscript settings for this module
        /**
         * @var \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager $configurationManager
         */
        $configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager');
        $fullConfiguration = $configurationManager->getConfiguration(
            $this->request->getControllerExtensionName(),
            $this->request->getPluginName()
        );
        $this->settings = $fullConfiguration['settings'];

        // Get defined languages
        $this->languageSuffices = SysLanguages::suffices($this->settings['languages']);
    }

    /**
     * Just like the regular $this->addFlashMessage, but will only show the message
     * if module.tx_twimporter.settings.verboseFlashMessages is set to 1
     *
     * @param string $messageBody The message
     * @param string $messageTitle Optional message title
     * @param int $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
     * @param bool $storeInSession Optional, defines whether the message should be stored in the session (default) or not
     * @return void
     * @throws \InvalidArgumentException if the message body is no string
     * @see \TYPO3\CMS\Core\Messaging\FlashMessage
     * @api
     */
    public function flashMessage($messageBody, $messageTitle = '', $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, $storeInSession = TRUE)
    {
        if ($this->settings['verboseFlashMessages']) {
            parent::addFlashMessage($messageBody, $messageTitle, $severity, $storeInSession);
        }
    }

    /**
     * @param array $extImportConfig
     */
    public function statusAction()
    {
        // Hook for registering additional imports from other extensions
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tw_importer']['registeredImports'] as $additionalImportExtensionKey => $additionalImport) {
                $registeredImports[$additionalImportExtensionKey] = $additionalImport;
            }
        }

        $this->view->assignMultiple(array(
            'languages' => $this->languageSuffices,
            'registeredImports' => $registeredImports
        ));
    }

    /**
     * @param string $extensionKey
     * @param array $mapping
     * @return bool
     */
    public function importAction($extensionKey)
    {
        try {

            // Step 1: Prepare everything
            // --------------------------
            $this->addFlashMessage('Starting import for extension key: ' . $extensionKey, '', FlashMessage::NOTICE);
            $records = $this->_createAndFillImportTable($extensionKey);
            $this->flashMessage('Inserted ' . count($records) . ' rows into temporary table');


            // Step 2: Import data into real table, create objects etc.
            // --------------------------------------------------------
            $this->flashMessage('Importing data from temporary table into extension', '', FlashMessage::NOTICE);
            // TODO: implement filterRecords() (Flag for updating / not updating in import file etc.)
            // TODO: move import file to archive before further processing (if(settings->archive))

            $hierarchy = $this->mappingUtility->getHierarchy($extensionKey);
            foreach ($records as $record) {
                $this->_importRecords($extensionKey, $record, $hierarchy);
            }


            $this->flashMessage('Gathering import results', '', FlashMessage::NOTICE);
            foreach ($this->objectUtility->getUpdatedObjectsCounter() as $counterClass => $counterSum) {
                $this->addFlashMessage('Created / updated ' . $counterSum . ' objects of class ' . $counterClass);
            }
            $this->addFlashMessage('Done with import for extension key: ' . $extensionKey);

        } catch (\Exception $e) {
            $this->addFlashMessage($e->getMessage() . ' thrown in : ' . $e->getFile() . ' on line ' . $e->getLine(), '', FlashMessage::ERROR);
        }
    }

}