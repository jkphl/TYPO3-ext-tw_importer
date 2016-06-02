<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

//$TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['tw_importer'] = array(
//    'title' => 'Test Import from tw_importer',
//    'mapping' => array(
//        'sku' => array(
//            'Tollwerk\TwBlog\Domain\Model\Content' => true,
//        ),
//        'facsimile_sku' => array(
//            'Tollwerk\TwBlog\Domain\Model\Content' => true,
//        ),
//        'starttime_day' => array(
//            'Tollwerk\TwBlog\Domain\Model\Content' => true,
//            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
//        ),
//        'starttime_hour' => array(
//            'Tollwerk\TwBlog\Domain\Model\Content' => true,
//            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
//        ),
//        'publish_facebook' => array(
//            'Tollwerk\TwBlog\Domain\Model\Content' => true,
//            'Tollwerk\TwBlog\Domain\Model\Socialpost' => true,
//        )
//    )
//);


//
//$TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['tw_importertest'] = array(
//    'title' => 'Company / Employee for tx_twimportertest',
//
//
//    'mapping' => array(
//
//        'tx_twimporter_id' => array(
//            'Tollwerk\TwImportertest\Domain\Model\Company' => true
//        ),
//        'company_id' => array(
//            'Tollwerk\TwImportertest\Domain\Model\Company' => true
//        ),
//        'name' => array(
//            'Tollwerk\TwImportertest\Domain\Model\Company' => true
//        ),
//
//
//        'firstname' => array(
//            'Tollwerk\TwImportertest\Domain\Model\Employee' => true
//        ),
//        'lastname' => array(
//            'Tollwerk\TwImportertest\Domain\Model\Employee' => true
//        )
//    ),
//
//
//    'hierarchy' => array(
//
//        'Tollwerk\\TwImportertest\\Domain\\Model\\Company' => array(
//            'repository' => 'Tollwerk\\TwImportertest\\Domain\\Repository\\CompanyRepository',
//            'pid' => 1016,
//            'conditions' => array(
//                'mustBeSet' => array('company_id'),
//                'mustBeEmpty' => array()
//            ),
//            'children' => array(
//                array(
//                    'class' => 'Tollwerk\\TwImportertest\\Domain\\Model\\Employee',
//                    'repository' => 'Tollwerk\\TwImportertest\\Domain\\Repository\\EmployeeRepository',
//                    'parentAddImportChild' => 'Employee',
//                    'pid' => 1016,
//                    'conditions' => array(
//                        'mustBeSet' => array('firstname', 'lastname'),
//                        'mustBeEmpty' => array()
//                    ),
//                    'children' => array()
//                )
//            )
//        )
//    )
//);
