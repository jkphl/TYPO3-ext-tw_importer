<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Klaus Fiedler <klaus@tollwerk.de>,
 *          Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
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

namespace Tollwerk\TwImporter\Controller;

use ErrorException;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use Tollwerk\TwImporter\Utility\ImportService;
use Tollwerk\TwImporter\Utility\Mapping;
use Tollwerk\TwImporter\Utility\SysLanguages;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

/**
 * Import controller (backend module)
 */
class ImportController extends ActionController
{
    /**
     * Module settings
     *
     * @var array
     */
    protected $settings;

    /**
     * Language suffices
     *
     * @var array
     */
    protected $languageSuffices = null;

    /**
     * Get module settings and stuff at controller initialization
     */
    public function initializeAction()
    {
        // Get typoscript settings for this module
        /** @var BackendConfigurationManager $configurationManager */
        $configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager');
        $fullConfiguration    = $configurationManager->getConfiguration(
            $this->request->getControllerExtensionName(),
            $this->request->getPluginName()
        );
        $this->settings       = $fullConfiguration['settings'];

        // Get defined languages
        $this->languageSuffices = SysLanguages::suffices($this->settings['languages']);
    }

    /**
     * Status action
     */
    public function statusAction()
    {
        $this->view->assignMultiple(array(
            'languages'         => $this->languageSuffices,
            'registeredImports' => Mapping::getAllExtensionImports()
        ));
    }

    /**
     * Import action
     *
     * @param string $extensionKey Extension key
     *
     * @throws ErrorException
     * @throws ReflectionException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     */
    public function importAction($extensionKey)
    {
        /** @var ImportService $importService */
        $importService = $this->objectManager->get(
            ImportService::class,
            $this->settings,
            $this->languageSuffices,
            $this
        );

        $importService->run($extensionKey);

        try {
            /** @var ImportService $importService */
            $importService = $this->objectManager->get(
                ImportService::class,
                $this->settings,
                $this->languageSuffices,
                $this
            );

            $importService->run($extensionKey);
            // If an error occurs
        } catch (Exception $e) {
            $this->log($e->getMessage().' thrown in : '.$e->getFile().' on line '.$e->getLine(), FlashMessage::ERROR);
        }
    }

    /**
     * Log a message
     *
     * @param string $message Message
     * @param int $severity   Message severity
     */
    public function log($message, $severity = FlashMessage::OK)
    {
        $this->addFlashMessage($message, '', $severity);
    }

    /**
     * Just like the regular $this->addFlashMessage, but will only show the message
     * if module.tx_twimporter.settings.verboseFlashMessages is set to 1
     *
     * @param string $messageBody  The message
     * @param string $messageTitle Optional message title
     * @param int $severity        Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
     * @param bool $storeInSession Optional, defines whether the message should be stored in the session (default) or
     *                             not
     *
     * @return void
     * @throws InvalidArgumentException if the message body is no string
     * @see \TYPO3\CMS\Core\Messaging\FlashMessage
     * @api
     */
    public function addFlashMessage(
        $messageBody,
        $messageTitle = '',
        $severity = AbstractMessage::OK,
        $storeInSession = false
    ) {
        if (intval($this->settings['verboseFlashMessages']) || ($severity >= AbstractMessage::OK)) {
            parent::addFlashMessage($messageBody, $messageTitle, $severity, $storeInSession);
        }
    }
}
