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

namespace Tollwerk\TwImporter\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Abstract repository for translatable records
 */
abstract class AbstractEnhancedRepository extends Repository
{
    /**
     * Find multiple records by identifier
     *
     * @param \array $uids List of identifiers
     * @return \array               Matching records
     */
    public function findByUids(array $uids)
    {
        $uids = array_map('intval', array_filter($uids));
        if (!count($uids)) {
            return array();
        }

        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->matching($query->in('uid', $uids));

        return $query->execute();
    }
}
