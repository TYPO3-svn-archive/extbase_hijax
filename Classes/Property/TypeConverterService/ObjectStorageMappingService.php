<?php
namespace EssentialDots\ExtbaseHijax\Property\TypeConverterService;
use TYPO3\CMS\Core\SingletonInterface;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
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

class ObjectStorageMappingService implements SingletonInterface {

	/**
	 * @var array
	 */
	protected $splObjectHashToSourceKeyMap = array();

	/**
	 * @var array
	 */
	protected $sourceKeyToSplObjectHashMap = array();

	/**
	 * @param string $splObjectHash
	 * @param string $sourceKey
	 */
	public function mapSplObjectHashToSourceKey($splObjectHash, $sourceKey) {
		$this->splObjectHashToSourceKeyMap[$splObjectHash] = $sourceKey;
		$this->sourceKeyToSplObjectHashMap[$sourceKey] = $splObjectHash;
	}

	/**
	 * @param string $splObjectHash
	 * @return string
	 */
	public function getSourceKeyForSplObjectHash($splObjectHash) {
		if (array_key_exists($splObjectHash, $this->splObjectHashToSourceKeyMap)) {
			return $this->splObjectHashToSourceKeyMap[$splObjectHash];
		} else {
			return $splObjectHash;
		}
	}

	/**
	 * @param string $sourceKey
	 * @return string
	 */
	public function getSplObjectHashForSourceKey($sourceKey) {
		if (array_key_exists($sourceKey, $this->sourceKeyToSplObjectHashMap)) {
			return $this->sourceKeyToSplObjectHashMap[$sourceKey];
		} else {
			return $sourceKey;
		}
	}
}
