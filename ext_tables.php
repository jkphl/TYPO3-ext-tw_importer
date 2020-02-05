<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

// Add TypoScript files
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('tw_importer', 'Configuration/TypoScript',
    'tollwerk Importer');

// If running in backend mode
if (TYPO3_MODE === 'BE') {
    // Register importer backend module
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Tollwerk.tw_importer',
        'web',
        'import',
        '',
        array(
            'Import' => 'status,import'
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:tw_importer/ext_icon.png',
            'labels' => 'LLL:EXT:tw_importer/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab',
        )
    );
}
