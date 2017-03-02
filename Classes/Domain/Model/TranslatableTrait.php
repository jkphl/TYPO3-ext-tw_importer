<?php

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Joschi <joschi@tollwerk.de>, tollwerk GmbH
 *           Klaus Fiedler <klaus@tollwerk.de>, tollwerk GmbH
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

namespace Tollwerk\TwImporter\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Translateable trait
 */
trait TranslatableTrait
{
    /**
     * Parent object (if translated)
     *
     * @var AbstractEntity
     */
    protected $translationParent;

    /**
     * Language
     *
     * @var \int
     */
    protected $translationLanguage;

    /**
     * Returns the parent document
     *
     * @return AbstractEntity Parent object
     */
    public function getTranslationParent()
    {
        return $this->translationParent;
    }

    /**
     * Sets the parent document
     *
     * @param AbstractEntity $translationParent Parent object
     */
    public function setTranslationParent($translationParent)
    {
        $this->translationParent = $translationParent;
    }

    /**
     * Returns the translation language
     *
     * @return \int Translation language
     */
    public function getTranslationLanguage()
    {
        return $this->translationLanguage;
    }

    /**
     * Sets the translation language
     *
     * @param \int $translationLanguage Translation language
     */
    public function setTranslationLanguage($translationLanguage)
    {
        $this->translationLanguage = $translationLanguage;
    }

    /**
     * Get the localized record UID
     *
     * @return int Localized record UID
     */
    public function getLocalizedUid()
    {
        return $this->_localizedUid;
    }
}
