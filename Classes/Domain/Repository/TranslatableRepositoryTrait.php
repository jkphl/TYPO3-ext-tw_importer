<?php

/**
 * Fischer Automobile
 *
 * @category Jkphl
 * @package Jkphl\Rdfalite
 * @subpackage Tollwerk\TwImporter\Domain\Repository
 * @author Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @copyright Copyright © 2017 Joschi Kuphal <joschi@tollwerk.de> / @jkphl
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 */

/***********************************************************************************
 *  The MIT License (MIT)
 *
 *  Copyright © 2017 Joschi Kuphal <joschi@kuphal.net> / @jkphl
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of
 *  this software and associated documentation files (the "Software"), to deal in
 *  the Software without restriction, including without limitation the rights to
 *  use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 *  the Software, and to permit persons to whom the Software is furnished to do so,
 *  subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *  COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 *  IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 *  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 ***********************************************************************************/

namespace Tollwerk\TwImporter\Domain\Repository;

use Tollwerk\TwImporter\Domain\Model\TranslatableInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Translatable repository trait
 */
trait TranslatableRepositoryTrait
{
    /**
     * Table name
     *
     * @var \string
     */
    protected $_tablename = null;

    /**
     * Find an object by language and its translation parent
     *
     * @param TranslatableInterface $parent Parent object
     * @param \int $sysLanguage System language
     * @return TranslatableInterface Translated object
     * @throws \ErrorException if the table name is empty
     */
    public function findOneByTranslationParent(TranslatableInterface $parent, $sysLanguage = 0)
    {
        if ($this->_tablename === null) {
            throw new \ErrorException('$this->_tablename is NULL! Please set "protected $_tablename = \'tx_yourextensionkey_domain_model_yourmodelclassname\';" in '.get_class($this));
        }

        $languageField = $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'];
        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = null;
        /** @var Repository $this */
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->getQuerySettings()->setIncludeDeleted(true);
        $query->matching(
            $query->logicalAnd(
                $query->equals('pid', $parent->getPid()),
                $query->equals('translationParent', $parent->getUid()),
                $query->equals('translationLanguage', $sysLanguage)
            )
        );
        $result = $query->execute()->getFirst();

        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = $languageField;
        return $result;
    }

    /**
     * Adds an object to this repository
     *
     * @param TranslatableInterface $object
     * @return void
     */
    public function add($object)
    {
        $languageField = $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'];
        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = null;
        parent::add($object);
        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = $languageField;
    }

    /**
     * Replaces an existing object with the same identifier by the given object
     *
     * @param TranslatableInterface $modifiedObject The modified object
     * @return void
     */
    public function update($modifiedObject)
    {
        $languageField = $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'];
        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = null;
        parent::update($modifiedObject);
        $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = $languageField;
    }
}
