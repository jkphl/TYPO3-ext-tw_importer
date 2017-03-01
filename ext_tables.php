<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

// Add typoscript files
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'tollwerk® Importer');

// Register backend modules
if (TYPO3_MODE === 'BE') {
    /**
     * Registers a Backend Module
     */
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Tollwerk.' . $_EXTKEY,
        'web',	 // Make module a submodule of 'web'
        'import',	// Submodule key
        '',						// Position
        array(
            'Import' => 'status,import'
        ),
        array(
            'access' => 'user,group',
            'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.png',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_import.xlf',
        )
    );

}