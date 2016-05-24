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

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract repository for importable records
 */
abstract class AbstractImportableRepository extends AbstractTranslatableRepository {

	
	
	
	/**
	 * @var string
	 */
	protected $field_sku = 'tx_twimporter_id';




	/**
	 * Find all  uids
	 *
	 * @param mixed $languageUid
	 *
	 * @return array
	 */
	public function findAllUids()
	{


		$query = $this->createQuery();
		$sysLanguageUid = $GLOBALS['TSFE']->sys_language_uid;

		if ($sysLanguageUid === null) {
			$sysLanguageUid = 0;
		}

		$statement = '
			SELECT 		f.uid
			FROM 		'.$this->_tablename.'
			WHERE		f.deleted = 0 AND f.hidden = 0 AND sys_language_uid = ' . $sysLanguageUid;

		$query->statement($statement);
		$result = $query->execute(true);

		$uids = array();
		foreach ($result as $r) {
			$uids[] = $r['uid'];
		}

		return $uids;
	}

	/**
	 * Find an object by SKU and PID
	 * 
	 * @param string $sku			SKU
	 * @param int $pid			PID
	 * @param bool	$debug
	 * @return \TYPO3\CMS\Extbase\DomainObject\AbstractEntity		Object
	 */
	public function findOneBySkuAndPid($sku, $pid = NULL, $debug = FALSE, $ignoreEnableFields = FALSE, $includeDeleted = FALSE) {



		$query			= $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setRespectSysLanguage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields($ignoreEnableFields);
		$query->getQuerySettings()->setIncludeDeleted($includeDeleted);




		if($debug){
			echo ' > findOneBySkuAndPid: sku = '.$sku.', pid = '.$pid.PHP_EOL;
		}

		$constraints = array(
			$query->equals($this->field_sku, $sku),
			$query->equals('translationLanguage', 0)
		);

		if($pid != NULL){
			$constraints[] = $query->equals('pid', $pid);
		}

		$query->matching($query->logicalAnd($constraints));



		$result = $query->execute()->getFirst();

		

		if($debug){
			echo " result: ".gettype($result).' - '.get_class($result);
			if($result instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractEntity){
				echo ', uid: '.$result->getUid();

				try {
					echo ' deleted: '.intval($result->getDeleted()).', hidden: '.intval($result->getHidden());
				}catch(\Exception $e){

				}

				echo PHP_EOL;

			}
		}

		return $query->execute()->getFirst();
	}
	
	/**
	 * Return all records not modified since at least a particular timestamp
	 * 
	 * @param \int $tstamp			Timestamp
	 * @return Ambigous <\TYPO3\CMS\Extbase\Persistence\QueryResultInterface, \TYPO3\CMS\Extbase\Persistence\array>
	 */
	public function findAllOlderThan($tstamp) {
		$query			= $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
// 		$query->getQuerySettings()->setRespectSysLanguage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);
		$query->getQuerySettings()->setIncludeDeleted(false);
		return $query->matching($query->lessThan('import', intval($tstamp)))->execute();
	}
	
	/**
	 * Remove orphaned translations
	 * 
	 * @return \boolean				Success
	 */
	public function removeOrphanedTranslations() {
		
		/* @var $DB \TYPO3\CMS\Core\Database\DatabaseConnection */
		$DB				=& $GLOBALS['TYPO3_DB'];
		$sql			= 'UPDATE `'.$this->_tablename.'` AS `t1` INNER JOIN `'.$this->_tablename.'` AS `t2` ON `t1`.`l10n_parent` = `t2`.`uid` AND `t2`.`deleted` = 1 SET `t1`.`deleted` = 1';
		return !!$DB->sql_query($sql);
	}


	/**
	 * Returns an array with ordering arrays for sorting by given values (e.g. uid=3, uid=6, uid=1, uid=2)
	 *
	 * @param string $field The field name / property for sorting
	 * @param unknown $values Arrays with values for this field name / property.
	 * @return array            Orderings for extbase query
	 */
	protected function _orderByField($field, $values)
	{
		$orderings = array();
		foreach ($values as $value) {
			$orderings[$field . '=' . $value] = \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING;
		}
		return $orderings;
	}

	/**
	 * Find by multiple uids.
	 *
	 * @param mixed $uids Array or comma separated list of uids
	 * @param integer $page Offset for the query
	 * @param integer $itemsPerPage Limit for the query
	 * @param string $sortBy Field to order by. Please note: It's possible to attach |DESC or |ASC to the $sortBy string :-)
	 * @param string $ascDesc ASC / DESC or see \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING etc.
	 * @return object
	 * @see https://forge.typo3.org/issues/14026
	 */
	public function findByUids($uids, $page = 0, $itemsPerPage = 0, $sortBy = false, $ascDesc = false)
	{

		// Normalize the UID list
		if (!is_array($uids)) {
			$uids = GeneralUtility::trimExplode(',', $uids);
		}
		array_filter($uids);

		$query = $this->createQuery();

		// If there's particular number of results to be fetched
		if ($itemsPerPage > 0) {
			$query->setLimit(intval($itemsPerPage));

			// If there's an offset
			if ($page > 0) {
				$query->setOffset(intval($page));
			}
		}

		// If a particular column is selected for sorting
		if ($sortBy) {

			// Check if there's a asc / desc String
			$sortByExplode = explode('|', $sortBy);
			$sortByField = $sortByExplode[0];
			$sortByAscDesc = (count($sortByExplode) > 1 ? $sortByExplode[1] : false);

			$query->setOrderings(array(
				$sortByField => ($sortByAscDesc ? $sortByAscDesc : \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING)
			));

			// Else sort by the given uids
		} else {
			// Not supported by TYPO3, needs ugly hack instead
// 			$query->setOrderings(array(
// 				'FIND_IN_SET('.$this->_tablename.'.uid, "'.implode(',', $uids).'")' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
// 			));

			$query->setOrderings($this->_orderByField('uid', $uids));
		}

		// Match the given UIDs only
		$query->matching($query->in('uid', $uids));

		// Disable language overlay, because the given object $uids
		// should be the correct ones already anyway. If this is not set,
		// the query won't return anything in others than the default sys_language
		$query->getQuerySettings()->setRespectSysLanguage(false);
		$result = $query->execute();



		return $result;
	}

}