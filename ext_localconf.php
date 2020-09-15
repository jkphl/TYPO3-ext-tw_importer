<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Tollwerk.TwImporter',
    'ImportForm',
    [
        'Import' => 'processing',
    ],
    [
        'Import' => 'processing',
    ]
);
