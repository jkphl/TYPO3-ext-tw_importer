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

use ErrorException;
use ReflectionClass;
use ReflectionException;
use Tollwerk\TwImporter\Domain\Model\AbstractImportable;
use Tollwerk\TwImporter\Domain\Model\TranslatableInterface;
use Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Utility class for getting bits of information via AJAX
 */
class ItemUtility
{
    /**
     * Creation status
     *
     * @var string
     */
    const STATUS_CREATE = 'create';
    /**
     * Update status
     *
     * @var string
     */
    const STATUS_UPDATE = 'update';
    /**
     * Object manager
     *
     * @var ObjectManager
     */
    protected $objectManager = null;
    /**
     * Persistence Manager
     *
     * @var PersistenceManager
     */
    protected $persistenceManager = null;
    /**
     * Array for storing current parent objects when
     * ImportController->_importRecords() get's called recursively
     *
     * @var array
     */
    protected $parentRegistry = [];

    /**
     * Update statistics counter
     *
     * @var array
     */
    protected $updatedObjectsCounter = [];

    /**
     * Get or create a new importable object
     *
     * @param string $modelClass Model class
     * @param array $modelConfig Model configuration
     * @param mixed $importId    Unique identifier
     * @param int $sysLanguage   System language
     *
     * @return array Importable object
     * @throws ErrorException If the model doesn't support localization but localization was requested
     * @throws ReflectionException
     * @throws IllegalObjectTypeException
     */
    public function findOrCreateImportableObject($modelClass, $modelConfig, $importId, $sysLanguage)
    {
        // If the model doesn't support localization but localization was requested
        $reflectionClass = new ReflectionClass($modelClass);

        if (($sysLanguage > 0) && !$reflectionClass->implementsInterface(TranslatableInterface::class)) {
            throw new ErrorException('Object doesn\'t support localization');
        }

        /** @var AbstractImportableRepository $repository */
        $repository = $this->objectManager->get($modelConfig['repository']);

        // 1: Always try to find an existing object for the default language first, we need it anyway
        $object = $repository->findOneByIdentifierAndPid($importId, null, true, true);
        $status = self::STATUS_UPDATE;

        // 2: If the requested language is the default language (or the table doesn't support localization), (create and) return the object
        if ($sysLanguage <= 0) {
            // If no existing object found, create a new one
            if (!($object instanceof $modelClass)) {
                $object = $this->createObject($modelClass, $repository, $modelConfig['pid'], $importId);
                $status = self::STATUS_CREATE;
            }

            return [$object, $status];
        }

        // For non-default languages we need an orig translation, so error if unavailable
        if (!$object instanceof $modelClass) {
            throw new ErrorException('Couldn\'t find default translation model');
        }

        // For non-default languages we need an original translation, so fail if unavailable
        if (!$object instanceof $modelClass) {
            throw new ErrorException('Couldn\'t find default translation model');
        }

        // Find or create translation record
        $translatedObject = $repository->findOneByTranslationParent($object, $sysLanguage);
        if (!($translatedObject instanceof $modelClass)) {
            $translatedObject = $this->createObject(
                $modelClass,
                $repository,
                $modelConfig['pid'],
                $importId,
                $object,
                $sysLanguage
            );
            $status           = self::STATUS_CREATE;
            $this->persistenceManager->persistAll();
        } else {
            $translatedObject->setDeleted(false);
        }

        return [$translatedObject, $status];
    }

    /**
     * @param string $modelClass
     * @param AbstractImportableRepository $repository
     * @param int $pid
     * @param mixed $importId
     * @param null $translationParent
     * @param null $sysLanguage
     *
     * @return AbstractImportable
     * @throws IllegalObjectTypeException
     */
    protected function createObject(
        $modelClass,
        $repository,
        $pid,
        $importId,
        $translationParent = null,
        $sysLanguage = null
    ) {
        /**
         * @var AbstractImportable $object
         */
        $object = $this->objectManager->get($modelClass);
        $object->setPid($pid);
        $object->_setProperty(
            GeneralUtility::underscoredToLowerCamelCase($repository->getIdentifierColumn()),
            $importId
        );

        if ($translationParent && $sysLanguage) {
            $object->_setProperty('_languageUid', $sysLanguage);
            $object->setTranslationLanguage($sysLanguage);
            $object->setTranslationParent($translationParent);
        }

        $repository->add($object);
        $this->persistenceManager->persistAll();

        return $object;
    }

    /**
     * Persist an updated object
     *
     * @param string $modelClass         Model class
     * @param array $modelConfig         Model configuration
     * @param AbstractImportable $object Updated object
     *
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function update($modelClass, array $modelConfig, AbstractImportable $object)
    {
        /** @var AbstractImportableRepository $repository */
        $repository = $this->objectManager->get($modelConfig['repository']);
        $repository->update($object);
        $this->persistenceManager->persistAll();

        // Statistically register the updated object
        if (!array_key_exists($modelClass, $this->updatedObjectsCounter)) {
            $this->updatedObjectsCounter[$modelClass] = 0;
        }
        ++$this->updatedObjectsCounter[$modelClass];
    }

    /**
     * Register an object with its hierarchical parent object
     *
     * @param AbstractImportable $object       Child object
     * @param array $modelConf                 Child model configuration
     * @param AbstractImportable $parentObject Parent object
     *
     * @return boolean Success
     * @throws ErrorException If the registration method is not available
     * @throws ReflectionException
     * @throws UnknownObjectException
     */
    public function registerWithParentObject(
        AbstractImportable $object,
        array $modelConf,
        AbstractImportable $parentObject
    ) {
        // TODO: Add option to exclude children (mm relations etc.) from translation / use l10n_mode etc. from TCA
        // Determine the registration method (config or convention)
        $registrationMethod = 'add'.(new ReflectionClass(get_class($object)))->getShortName();

        if (!empty($modelConf['registerWithParentMethod'])) {
            $registrationMethod = $modelConf['registerWithParentMethod'];
        }

        // If the registration method is not available
        if (!is_callable([$parentObject, $registrationMethod])) {
            throw new ErrorException('Registration method '.get_class($parentObject).'->'.$registrationMethod.' is not callable');
        }

        // Register the child
        $parentObject->$registrationMethod($object);
        $this->persistenceManager->update($parentObject);
        $this->persistenceManager->persistAll();

        return true;
    }

    /**
     * Return the update statistics counter
     *
     * @return array Update statistics counter
     */
    public function getUpdatedObjectsCounter()
    {
        return $this->updatedObjectsCounter;
    }

    /**
     * Inject the object manager
     *
     * @param ObjectManager $objectManager Object manager
     */
    public function injectObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Inject the persistence manager
     *
     * @param PersistenceManager $persistenceManager Persistence manager
     */
    public function injectPersistenceManager(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }
}
