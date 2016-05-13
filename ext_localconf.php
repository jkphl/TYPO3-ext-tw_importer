<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

$TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['tw_importer'] = array(
    'title' => 'Test Import from tw_importer',
    'mapping' => array(
        'sku' => array(
            'Tollwerk\TwBlog\Domain\Model\Content' => true,
        ),
        'facsimile_sku' => array(
            'Tollwerk\TwBlog\Domain\Model\Content' => true,
        ),
        'starttime_day' => array(
            'Tollwerk\TwBlog\Domain\Model\Content' => true,
            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
        ),
        'starttime_hour' => array(
            'Tollwerk\TwBlog\Domain\Model\Content' => true,
            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
        ),
        'publish_facebook' => array(
            'Tollwerk\TwBlog\Domain\Model\Content' => true,
            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
        )
    )
);
