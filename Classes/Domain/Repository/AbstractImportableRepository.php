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

use Tollwerk\TwImporter\Domain\Model\AbstractImportable;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnexpectedTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * Abstract repository for importable records
 */
abstract class AbstractImportableRepository extends AbstractEnhancedRepository
{
    /**
     * Default identifier column
     *
     * @var string
     */
    protected $identifierColumn = 'sku';

    /**
     * Return the import identifier column name
     *
     * @return string Import identifier column
     */
    public function getIdentifierColumn()
    {
        return $this->identifierColumn;
    }

    /**
     * Return all record UIDs
     *
     * @return array UIDs
     */
    public function findAllUids()
    {
        $query          = $this->createQuery();
        $sysLanguageUid = $GLOBALS['TSFE']->sys_language_uid ?: 0;

        $statement = '
			SELECT uid
			FROM '.$this->_tablename.'
			WHERE deleted = 0 AND hidden = 0 AND sys_language_uid = '.$sysLanguageUid;

        $query->statement($statement);
        $result = $query->execute(true);

        $uids = [];
        foreach ($result as $record) {
            $uids[] = $record['uid'];
        }

        return $uids;
    }

    /**
     * Find an object by identifier and PID
     *
     * @param mixed $identifier        Identifier
     * @param int $pid                 PID
     * @param bool $ignoreEnableFields Ignore the enable fields
     * @param bool $includeDeleted     Include deleted records
     *
     * @return AbstractImportable
     */
    public function findOneByIdentifierAndPid(
        $identifier,
        $pid = null,
        $ignoreEnableFields = false,
        $includeDeleted = false
    ): ?AbstractImportable {

        $query = $this->createQuery();

        $query->getQuerySettings()
              ->setRespectStoragePage(false)
              ->setRespectSysLanguage(false)
              ->setIgnoreEnableFields($ignoreEnableFields)
              ->setIncludeDeleted($includeDeleted);

        $constraints = array(
            $query->equals($this->identifierColumn, $identifier),
        );

        // If this repository's models are translatable: Fetch the default language only
        if ($this instanceof TranslatableRepositoryInterface) {
            $constraints[] = $query->equals('translationLanguage', 0);
        }

        if ($pid != null) {
            $constraints[] = $query->equals('pid', $pid);
        }

        $query->matching($query->logicalAnd($constraints));

        return $query->execute()->getFirst();
    }

    /**
     * Delete all records not modified since a particular timestamp
     *
     * @param int $timestamp    Timestamp
     * @param array $conditions Additional conditions
     *
     * @return int Number of deleted records
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function deleteOlderThan($timestamp, array $conditions = [])
    {
        $deleted = 0;

        /** @var AbstractImportable $object */
        foreach ($this->findOlderThan($timestamp, $conditions) as $object) {
            $this->remove($object);
            ++$deleted;
        }

        $this->persistenceManager->persistAll();

        return $deleted;
    }

    /**
     * Return all records not modified since a particular timestamp
     *
     * @param int $timestamp    Timestamp
     * @param array $conditions Additional conditions
     *
     * @return QueryResultInterface Records older than the timestamp
     * @throws InvalidQueryException
     */
    public function findOlderThan($timestamp, array $conditions = [])
    {
        $query = $this->createQuery();
        $query->getQuerySettings()
              ->setRespectStoragePage(false)
              ->setIgnoreEnableFields(true)
              ->setIncludeDeleted(false);

        $constraints = [$query->lessThan('import', intval($timestamp))];
        foreach ($conditions as $property => $value) {
            $constraints[] = $query->equals($property, $value);
        }

        return $query->matching($query->logicalAnd($constraints))->execute();
    }

    /**
     * Remove orphaned translations
     *
     * @return boolean                Success
     */
    public function removeOrphanedTranslations()
    {
        /* @var $DB DatabaseConnection */
        $DB  =& $GLOBALS['TYPO3_DB'];
        $sql = 'UPDATE `'.$this->_tablename.'` AS `t1` INNER JOIN `'.$this->_tablename.'` AS `t2` ON `t1`.`l10n_parent` = `t2`.`uid` AND `t2`.`deleted` = 1 SET `t1`.`deleted` = 1';

        return !!$DB->sql_query($sql);
    }

    /**
     * Find by multiple UIDs
     *
     * @param mixed $uids           Array or comma separated list of uids
     * @param integer $page         Offset for the query
     * @param integer $itemsPerPage Limit for the query
     * @param string $sortBy        Field to order by. Please note: It's possible to attach |DESC or |ASC to the
     *                              $sortBy string :-)
     * @param string $ascDesc       ASC / DESC or see \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
     *                              etc.
     *
     * @return object
     * @throws Exception
     * @throws UnexpectedTypeException
     * @see https://forge.typo3.org/issues/14026
     */
    public function findByUids($uids, $page = 0, $itemsPerPage = 0, $sortBy = null, $ascDesc = null)
    {
        // Normalize the UID list
        if (!is_array($uids)) {
            $uids = GeneralUtility::trimExplode(',', $uids);
        }
        $uids = array_filter(array_map('intval', array_filter($uids)));
        if (!count($uids)) {
            return [];
        }

        /** @var Query $query */
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
        $sortByField = false;
        if ($sortBy) {

            // Check if there's a asc / desc String
            $sortByExplode = explode('|', $sortBy);
            $sortByField   = $sortByExplode[0];
            $sortByAscDesc = (count($sortByExplode) > 1 ? $sortByExplode[1] : false);

            $query->setOrderings(array(
                $sortByField => ($sortByAscDesc ? $sortByAscDesc : QueryInterface::ORDER_ASCENDING)
            ));

            // Else sort by the given UIDs
        } else {
            $sortByField = true;
            // Not supported by TYPO3, needs ugly hack instead
// 			$query->setOrderings(array(
// 				'FIND_IN_SET('.$this->_tablename.'.uid, "'.implode(',', $uids).'")' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
// 			));

//            $query->setOrderings($this->orderByField('uid', $uids));
        }

        // Match the given UIDs only
        $query->matching($query->in('uid', $uids));

        // Disable language overlay, because the given object $uids
        // should be the correct ones already anyway. If this is not set,
        // the query won't return anything in others than the default sys_language
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $result = $query->execute();

        // If the records should be ordered by uid
        if ($sortByField === true) {
            $records = array_fill_keys($uids, null);
            foreach ($result as $record) {
                $records[$record->getUid()] = $record;
            }

            $result = array_values($records);
        }

        return $result;
    }

    /**
     * Returns an array with ordering arrays for sorting by given values (e.g. uid=3, uid=6, uid=1, uid=2)
     *
     * @param string $field   The field name / property for sorting
     * @param unknown $values Arrays with values for this field name / property.
     *
     * @return array            Orderings for extbase query
     */
    protected function orderByField($field, $values)
    {
        $orderings = array();
        foreach ($values as $value) {
            $orderings[$field.'='.$value] = QueryInterface::ORDER_DESCENDING;
        }

        return $orderings;
    }
}
