<?php
namespace EssentialDots\ExtbaseHijax\Persistence\Storage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/***************************************************************
*  Copyright notice
*
*  (c) 2012-2013 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class Typo3DbBackend extends \TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbBackend {

	/**
	 * Returns the number of tuples matching the query.
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\QueryInterface $query
	 * @return int
	 */
	public function getObjectCountByQuery(\TYPO3\CMS\Extbase\Persistence\QueryInterface $query) {
		if (version_compare(TYPO3_version,'6.2','<')) {
			return $this->getObjectCountByQueryTYPO361($query);
		} else {
			return $this->getObjectCountByQueryTYPO362($query);
		}
	}

	/**
	 * @param \TYPO3\CMS\Extbase\Persistence\QueryInterface $query
	 * @return int
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Storage\Exception\BadConstraintException
	 */
	protected function getObjectCountByQueryTYPO362(\TYPO3\CMS\Extbase\Persistence\QueryInterface $query) {
		if ($query->getConstraint() instanceof \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Statement) {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Storage\Exception\BadConstraintException('Could not execute count on queries with a constraint of type TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Qom\\StatementInterface', 1256661045);
		}

		$statement = $query->getStatement();
		if($statement instanceof \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Statement) {
			/*
			 * Overriding default extbase logic for manually passed SQL
			 */
			$sql = $statement->getStatement();
			$parameters = $statement->getBoundVariables();
			$this->replacePlaceholders($sql, $parameters);

			$matches = array();
			$tableNames = array();
			if (preg_match('/^\s*#\s*@tables_used\s*=\s*(.*)\s*;/msU', $sql, $matches)) {
				$tableNames = GeneralUtility::trimExplode(',', $matches[1]);
				$sql = preg_replace('/^\s*#\s*@tables_used\s*=\s*(.*)\s*;/msU', '', $sql);
			}
			$sqlParser = \EssentialDots\ExtbaseHijax\Persistence\Parser\SQL::ParseString($sql);

			$countQuery = $sqlParser->getCountQuery();
			if (count($tableNames)) {
				$countQuery = "# @tables_used=".implode(',', $tableNames)."; \n".$countQuery;
			}

			$res = $this->databaseHandle->sql_query($countQuery);
			$this->checkSqlErrors($countQuery);
			$count = 0;
			while ($row = $this->databaseHandle->sql_fetch_assoc($res)) {
				$count = $row['count'];
				break;
			}
			$this->databaseHandle->sql_free_result($res);
		} else {
			/*
			 * Default Extbase logic
			 */
			$count = parent::getObjectCountByQuery($query);
		}

		return (int)$count;
	}

	/**
	 * @param \TYPO3\CMS\Extbase\Persistence\QueryInterface $query
	 * @return int
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Storage\Exception\BadConstraintException
	 */
	protected function getObjectCountByQueryTYPO361(\TYPO3\CMS\Extbase\Persistence\QueryInterface $query) {
		$statement = $query->getStatement();
		if($statement instanceof \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Statement) {
			/*
			 * Overriding default extbase logic for manually passed SQL
			 */
			$sql = $statement->getStatement();
			$parameters = $statement->getBoundVariables();
			$this->replacePlaceholders($sql, $parameters);

			$sqlParser = \EssentialDots\ExtbaseHijax\Persistence\Parser\SQL::ParseString($sql);

			$countQuery = $sqlParser->getCountQuery();
			$result = $this->databaseHandle->sql_query($countQuery);
			$this->checkSqlErrors($countQuery);
			if (version_compare(TYPO3_version,'6.1.0','<')) {
				$rows = $this->getRowsFromResult($query->getSource(), $result);
			} else {
				$rows = $this->getRowsFromResult($result);
			}
			$count = current(current($rows));
			$this->databaseHandle->sql_free_result($result);
		} else {
			/*
			 * Default Extbase logic
			 */
			$count = parent::getObjectCountByQuery($query);
		}

		return (int)$count;
	}

	/**
	 * Parses the query and returns the SQL statement parts.
	 *
	 * @param QueryInterface $query The query
	 * @return array The SQL statement parts
	 */
	public function parseQuery(QueryInterface $query, array &$parameters) {
		// backward compatibility for some extensions like news v2.3.0 for TYPO3 6.2.0
		if ($this->queryParser != NULL) {
			// note: this does not use the new query cache in TYPO3 6.2!
			list($queryHash, $parameters) = $this->queryParser->preparseQuery($query);
			return $this->queryParser->parseQuery($query);
		} else {
			return parent::parseQuery($query, $parameters);
		}
	}

	/**
	 * Returns the statement, ready to be executed.
	 *
	 * @param array $sql The SQL statement parts
	 * @return string The SQL statement
	 */
	public function buildQuery(array $sql) {
		if ($this->queryParser != NULL) {
			$statement = 'SELECT ' . implode(' ', $sql['keywords']) . ' ' . implode(',', $sql['fields']) . ' FROM ' . implode(' ', $sql['tables']) . ' ' . implode(' ', $sql['unions']);
			if (!empty($sql['where'])) {
				$statement .= ' WHERE ' . implode('', $sql['where']);
				if (!empty($sql['additionalWhereClause'])) {
					$statement .= ' AND ' . implode(' AND ', $sql['additionalWhereClause']);
				}
			} elseif (!empty($sql['additionalWhereClause'])) {
				$statement .= ' WHERE ' . implode(' AND ', $sql['additionalWhereClause']);
			}
			if (!empty($sql['orderings'])) {
				$statement .= ' ORDER BY ' . implode(', ', $sql['orderings']);
			}
			if (!empty($sql['limit'])) {
				$statement .= ' LIMIT ' . $sql['limit'];
			}
			return $statement;
		} else {
			return parent::buildQuery($sql);
		}
	}
}

?>