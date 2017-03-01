<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

// Add TypoScript files
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript',
    'tollwerk Importer');

// If running in backend mode
if (TYPO3_MODE === 'BE') {
    // Register importer backend module
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Tollwerk.'.$_EXTKEY,
        'web',
        'import',
        '',
        array(
            'Import' => 'status,import'
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:'.$_EXTKEY.'/ext_icon.png',
            'labels' => 'LLL:EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab',
        )
    );

}
