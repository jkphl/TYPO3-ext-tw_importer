<?php
namespace Tollwerk\TwImporter\Domain\Repository;


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

/**
 * Abstract repository for translatable records
 */
abstract class AbstractTranslatableRepository extends AbstractEnhancedRepository {
	
	/**
	 * Table name
	 *
	 * @var \string
	 */
	protected $_tablename = null;

	/**
	 * Find an object by language and its translation parent
	 *
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $parent	Parent object
	 * @param \int $sysLanguage          								System language
	 * @return \TYPO3\CMS\Extbase\DomainObject\AbstractEntity			Translated object
	 */
	public function findOneByTranslationParent(\TYPO3\CMS\Extbase\DomainObject\AbstractEntity $parent, $sysLanguage = 0) {

		if ($this->_tablename === null) {
			return null;
		}

		/*
		echo PHP_EOL.' > pid: '.$parent->getPid().PHP_EOL;
		echo PHP_EOL.' > translationParent: '.$parent->getUid().PHP_EOL;
		echo PHP_EOL.' > translationLanguage: '.$sysLanguage.PHP_EOL;
		*/


		$languageField = $GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'];
		$GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = null;
		$query			= $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);
		$query->getQuerySettings()->setIncludeDeleted(TRUE);
		$query->matching(
			$query->logicalAnd(
				$query->equals('pid', $parent->getPid()),
				$query->equals('translationParent', $parent->getUid()),
				$query->equals('translationLanguage', $sysLanguage)
			)
		);
		$result			= $query->execute()->getFirst();

		$GLOBALS['TCA'][$this->_tablename]['ctrl']['languageField'] = $languageField;
		return $result;
	}
}