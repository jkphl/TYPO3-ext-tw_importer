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

use Tollwerk\TwImporter\Domain\Model\AbstractImportable;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class for getting bits of information via ajax
 */
class Object
{
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
     * Array for storing current parent objects when
     * ImportController->_importRecords() get's called recursively
     *
     * @var array
     */
    protected $parentRegistry = array();

    protected $updatedObjectsCounter = array();


    /**
     * @param string $objectClass
     * @param \Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository $repository
     * @param int $pid
     * @param int $importId
     * @return \Tollwerk\TwImporter\Domain\Model\AbstractImportable
     */
    protected function createNew($objectClass, $repository, $pid, $importId, $translationParent = NULL, $sysLanguage = NULL){

        /**
         * @var \Tollwerk\TwImporter\Domain\Model\AbstractImportable $object
         */
        $object = $this->objectManager->get($objectClass);
        $object->setPid($pid);
        $object->setTxTwimporterId($importId);

        if($translationParent && $sysLanguage){
            $object->_setProperty('_languageUid', $sysLanguage);
            $object->setTranslationLanguage($sysLanguage);
            $object->setTranslationParent($translationParent);
        }

        $repository->add($object);
        $this->persistenceManager->persistAll();

        return $object;
    }

    /**
     * @param array $record
     * @param array $objectConf
     * @param int $registryLevel
     * @param int $sysLanguage
     */
    public function getByParent($record,$objectConf,$registryLevel,$sysLanguage){



        // First, try to find by parentFindChild-method if defined in hierarchy
        if(array_key_exists('parentFindChild',$objectConf)){

            $parentObject = $this->getParent($registryLevel, $sysLanguage);
            $method = $objectConf['parentFindChild'];
            if(method_exists($parentObject,$method)){
                $parentChildObject = $parentObject->{$method}($record,$objectConf);
                if($parentChildObject instanceof \Tollwerk\TwImporter\Domain\Model\AbstractImportable){
                    return $parentChildObject;
                }

            }
        }

        return NULL;
    }
    

    /**
     * @param array $hierarchy
     * @param int $importId
     * @param int $sysLanguage
     * @param int $registryLevel
     * @return \Tollwerk\TwImporter\Domain\Model\AbstractImportable
     */
    public function createOrGet($hierarchy, $importId, $sysLanguage,$registryLevel)
    {
        $objectClass = key($hierarchy);
        $objectConf = $hierarchy[$objectClass];

        /**
         * @var \Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository $repository
         */
        $repository = $this->objectManager->get($objectConf['repository']);

        /**
         * @var \Tollwerk\TwImporter\Domain\Model\AbstractImportable $emptyObject
         */
        $emptyObject = $this->objectManager->get($objectClass);



        // 1: Always try to find a existing object for
        // the default language first, we need it anyway
        // ---------------------------------------------
        $object = $repository->findOneBySkuAndPid($importId, NULL);



        // 2: If $sysLanguage is default language, return the found
        // object or create and return a new one on the fly
        // --------------------------------------------------------
        if($sysLanguage == 0){
            $status = 'update';

            // If no existing object found, create a new one out of $emptyObject
            if(!($object instanceof $emptyObject)){
                $object = $this->createNew($objectClass, $repository, $objectConf['pid'], $importId);
                $status = 'create';
            }

            return array(
                'object' => $object,
                'status' => $status
            );
        }



        // 3: Finally, if $sysLanguage is NOT the default language,
        // try to return the found translated object or
        // create and return a new translated object on the fly
        // -------------------------------------------------------
        if(!($object instanceof $emptyObject) && $sysLanguage > 0){
            throw new \ErrorException('Tried to create translated object when there was no object for the default language');
        }




        $status = 'update';
        $translatedObject = $repository->findOneByTranslationParent($object,$sysLanguage);
        if(!($translatedObject instanceof $object)){
            $status = 'create';
            $translatedObject = $this->createNew($objectClass, $repository, $objectConf['pid'], $importId, $object, $sysLanguage);
            $this->persistenceManager->persistAll();
        }

        return array(
            'object' => $translatedObject,
            'status' => $status
        );

    }

    /**
     * @param array $hierarchy
     * @param \Tollwerk\TwImporter\Domain\Model\AbstractImportable $object
     */
    public function update($hierarchy, $object){
        $objectClass = key($hierarchy);
        $objectConf = $hierarchy[$objectClass];

        /**
         * @var \Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository $repository
         */
        $repository = $this->objectManager->get($objectConf['repository']);
        $repository->update($object);
        $this->persistenceManager->persistAll();

        if(!array_key_exists($objectClass,$this->updatedObjectsCounter)){
            $this->updatedObjectsCounter[$objectClass] = 0;
        }
        ++$this->updatedObjectsCounter[$objectClass];
    }

    /**
     * @param \Tollwerk\TwImporter\Domain\Model\AbstractImportable $object
     * @param int $level
     * @param int $sysLanguage
     * @return bool
     */
    public function addParentToRegistry($object,$level,$sysLanguage){
        if($level < 0){
            return FALSE;
        }

        if(!is_array($this->parentRegistry[$sysLanguage])){
            $this->parentRegistry[$sysLanguage] = array();
        }

        $this->parentRegistry[$sysLanguage][$level] = $object;
        return TRUE;
    }

    /**
     * @param int $childRegistryLevel
     * @param int $sysLanguage
     * @return null|AbstractImportable
     */
    public function getParent($childRegistryLevel, $sysLanguage){

        // TODO: Add option to exclude children (mm relations etc.) from translation / use l10n_mode etc. from TCA

        // First, try to get the parent for the $child.
        // If there is no parent, then we don't need to proceed
        // ----------------------------------------------------
        $parentRegistryLevel = $childRegistryLevel-1;
        if($parentRegistryLevel < 0 || $parentRegistryLevel >= count($this->parentRegistry[$sysLanguage])){
            return NULL;
        }

        /**
         * @var \Tollwerk\TwImporter\Domain\Model\AbstractImportable $parentObject
         */
        $parentObject = $this->parentRegistry[$sysLanguage][$parentRegistryLevel];
        if(!($parentObject instanceof \Tollwerk\TwImporter\Domain\Model\AbstractImportable)){
            return NULL;
        }

        return $parentObject;
    }

    /**
     * @param \Tollwerk\TwImporter\Domain\Model\AbstractImportable $child
     * @param array $childConf
     * @param int $childRegistryLevel
     * @param int $sysLanguage
     * @return bool
     */
    public function addChildToParent($child, $childConf, $childRegistryLevel, $sysLanguage){

        // TODO: Add option to exclude children (mm relations etc.) from translation / use l10n_mode etc. from TCA
        $parentObject = $this->getParent($childRegistryLevel, $sysLanguage);
        if(!$parentObject){
            return FALSE;
        }


        // Get classnames for $parentObject and $child without their namespaces
        // --------------------------------------------------------------------
        $reflectionClass = new \ReflectionClass(get_class($child));
        $childClassname = $reflectionClass->getShortName();
        $reflectionClass = new \ReflectionClass(get_class($parentObject));
        $parentClassname = $reflectionClass->getShortName();

        // Finally, add child to parent according to $addToParentMode defined in hierarchy
        // -------------------------------------------------------------------------------

        if($childConf['parentAddImportChild']){
            $parentObject->addImportChild($child,$childConf,$sysLanguage);
        }else{
            $methodName = 'add'.$childClassname;
            $parentObject->{$methodName}($child);
            $this->persistenceManager->persistAll();
        }


        return TRUE;
    }

    public function getUpdatedObjectsCounter(){
        return $this->updatedObjectsCounter;
    }

}