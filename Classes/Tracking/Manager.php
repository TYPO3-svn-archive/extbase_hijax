<?php
namespace EssentialDots\ExtbaseHijax\Tracking;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
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

class Manager implements \TYPO3\CMS\Core\SingletonInterface {

	const SIGNAL_PreTrackRepositoryOnPage = 'preTrackRepositoryOnPage';
	const SIGNAL_PreTrackObjectOnPage = 'preTrackObjectOnPage';

	/**
	 * @var \EssentialDots\ExtbaseHijax\Cache\PageCacheFacade
	 */
	protected $pageCacheFacade;
	
	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
	 */
	protected $trackingCache;
	
	/**
	 * @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	protected $fe;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager;
		
	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper
	 */
	protected $dataMapper;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 */
	protected $configurationManager;	
	
	/**
	 * @var \EssentialDots\ExtbaseHijax\Utility\Ajax\Dispatcher
	 */
	protected $ajaxDispatcher;
		
	/**
	 * Extension Configuration
	 *
	 * @var \EssentialDots\ExtbaseHijax\Configuration\ExtensionInterface
	 */
	protected $extensionConfiguration;

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected $signalSlotDispatcher;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->fe = $GLOBALS['TSFE'];
		$this->trackingCache = $GLOBALS['typo3CacheManager']->getCache('extbase_hijax_tracking');
		$this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->dataMapper = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Mapper\\DataMapper');
		$this->configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManagerInterface');
		$this->ajaxDispatcher = $this->objectManager->get('EssentialDots\\ExtbaseHijax\\Utility\\Ajax\\Dispatcher');
		$this->extensionConfiguration = $this->objectManager->get('EssentialDots\\ExtbaseHijax\\Configuration\\ExtensionInterface');
		$this->pageCacheFacade = GeneralUtility::makeInstance('EssentialDots\\ExtbaseHijax\\Cache\\PageCacheFacade');
		$this->signalSlotDispatcher = $this->objectManager->get('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher');
	}
	
	/**
	 * Clears cache of pages where objects are shown
	 * 
	 * @param array $objects
	 */
	public function clearPageCacheForObjects($objects) {
		if ($objects) {
			foreach ($objects as $object) {
					/* @var $object \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject */
				$objectIdentifier = $this->getObjectIdentifierForObject($object);
				$this->clearPageCacheForObjectIdentifier($objectIdentifier);
			}
		}
		
		return;
	}

	/**
	 * Clears cache of pages where single object is shown
	 *
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object
	 */
	public function clearPageCacheForObject($object) {
		$this->clearPageCacheForObjects(array($object));
	}
	
	/**
	 * Clears cache of pages where objects are shown
	 *
	 * @param array $objectIdentifiers
	 */
	public function clearPageCacheForObjectIdentifiers($objectIdentifiers) {
		if ($objectIdentifiers) {
			foreach ($objectIdentifiers as $objectIdentifier) {
				$this->clearPageCacheForObjectIdentifier($objectIdentifier);
			}
		}
	
		return;
	}	
	
	/**
	 * Clears cache of pages where an object with the given identifier is shown
	 * 
	 * @param string $objectIdentifier
	 */
	public function clearPageCacheForObjectIdentifier($objectIdentifier) {
			// TODO: Move this to different implementations of the Tracking Manager...
		
		switch ($this->extensionConfiguration->getCacheInvalidationLevel()) {
			case 'consistent':
				$sharedLock = null;
				$sharedLockAcquired = $this->acquireLock($sharedLock, $objectIdentifier, FALSE);
		
				if ($sharedLockAcquired) {
					if ($this->trackingCache->has($objectIdentifier)) {
						$exclusiveLock = null;
						$exclusiveLockAcquired = $this->acquireLock($exclusiveLock, $objectIdentifier.'-e', TRUE);
		
						if ($exclusiveLockAcquired) {
							$pageHashs = $this->trackingCache->get($objectIdentifier);
							$this->pageCacheFacade->flushCacheByHashIdentifiers($pageHashs);
							$this->trackingCache->set($objectIdentifier, array());
							$this->releaseLock($exclusiveLock);
						} else {
							$pageHashs = $this->trackingCache->get($objectIdentifier);
							$this->pageCacheFacade->flushCacheByHashIdentifiers($pageHashs);
						}
					}
						
					$this->releaseLock($sharedLock);
				} else {
					// Failed locking
					// should probably throw an exception here
				}
				break;
			default:
			case 'noinvalidation':
				break;
		}
	
		return;
	}	
	/**
	 * Tracks display of an object on a page
	 *
	 * @param mixed $object Repository/Object/table name
	 * @param string $type 'hash' (for only one hash) or 'id' (for complete page cache of a page, for all hash combinations)
	 * @param mixed $hash Hash or page id (depending on the type) for which the object display will be associated
	 * @return void
	 */
	public function trackRepositoryOnPage($object = NULL, $type = 'hash', $hash = false) {
		if ($object && !$this->ajaxDispatcher->getIsActive()) {
			$this->signalSlotDispatcher->dispatch(__CLASS__, self::SIGNAL_PreTrackRepositoryOnPage, array('object' => $object, 'type' => $type, 'hash' => $hash));

			if ($type) {
				switch ($type) {
					case 'id':
						if (!$hash) {
							$hash = intval($this->fe->id);
						}
						$pageHash = 'id-'.$hash;
						break;
					case 'hash':
					default:
						if (!$hash) {
							$hash = $this->fe->getHash();
						}
						$pageHash = 'hash-'.$hash;
						break;
				}		

				if ($object instanceof \TYPO3\CMS\Extbase\Persistence\RepositoryInterface) {
					$objectType = preg_replace(array('/_Repository_(?!.*_Repository_)/', '/Repository$/'), array('_Model_', ''), get_class($object));
					$tableName = $this->dataMapper->getDataMap($objectType)->getTableName();
				} elseif ($object instanceof \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject) {
					$objectType = get_class($object);
					$tableName = $this->dataMapper->getDataMap($objectType)->getTableName();
				} else {
					$tableName = (string) $object;
				}

				if ($tableName) {
					$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);

					if ($frameworkConfiguration['persistence']['storagePid']) {
						$storagePids = GeneralUtility::intExplode(',', $frameworkConfiguration['persistence']['storagePid'], true);
					} else {
						$storagePids = array(-1);
					}

					foreach ($storagePids as $storagePid) {
						$objectIdentifier = $this->getObjectIdentifierForRepository($tableName, $storagePid);
						
						$sharedLock = null;
						$sharedLockAcquired = $this->acquireLock($sharedLock, $objectIdentifier, FALSE);
						
						if ($sharedLockAcquired) {
							if ($this->trackingCache->has($objectIdentifier)) {
								$pageHashs = $this->trackingCache->get($objectIdentifier);
								if (!in_array($pageHash, $pageHashs)) {
									$exclusiveLock = null;
									$exclusiveLockAcquired = $this->acquireLock($exclusiveLock, $objectIdentifier.'-e', TRUE);
									
									if ($exclusiveLockAcquired) {
										$pageHashs = $this->trackingCache->get($objectIdentifier);
										if (!in_array($pageHash, $pageHashs)) {
											$pageHashs[] = $pageHash;
											$this->trackingCache->set($objectIdentifier, array_unique($pageHashs));
										}
										
										$this->releaseLock($exclusiveLock);
									}
								}
							} else {
								$this->trackingCache->set($objectIdentifier, array($pageHash));
							}
							
							$this->releaseLock($sharedLock);
						}	
					}		
				}
		
			}
		}
	}
	
	/**
	 * Tracks display of an object on a page
	 * 
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object Object to use
	 * @param mixed $hash Hash or page id (depending on the type) for which the object display will be associated
	 * @param string $type 'hash' (for only one hash) or 'id' (for complete page cache of a page, for all hash combinations)
	 * @return void
	 */
	public function trackObjectOnPage(\TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object = NULL, $type = 'hash', $hash = false) {

		if ($object && !$this->ajaxDispatcher->getIsActive()) {
			$this->signalSlotDispatcher->dispatch(__CLASS__, self::SIGNAL_PreTrackObjectOnPage, array('object' => $object, 'type' => $type, 'hash' => $hash));

			if ($type) {
				switch ($type) {
					case 'id':
						if (!$hash) {
							$hash = intval($this->fe->id);
						}
						$pageHash = 'id-'.$hash;
						break;
					case 'hash':
					default:
						if (!$hash) {
							$hash = $this->fe->getHash();
						}
						$pageHash = 'hash-'.$hash;
						break;
				}

				$objectIdentifier = $this->getObjectIdentifierForObject($object);
				
				$sharedLock = null;
				$sharedLockAcquired = $this->acquireLock($sharedLock, $objectIdentifier, FALSE);
				
				if ($sharedLockAcquired) {
					if ($this->trackingCache->has($objectIdentifier)) {
						$pageHashs = $this->trackingCache->get($objectIdentifier);
						if (!in_array($pageHash, $pageHashs)) {
							$exclusiveLock = null;
							$exclusiveLockAcquired = $this->acquireLock($exclusiveLock, $objectIdentifier.'-e', TRUE);
							
							if ($exclusiveLockAcquired) {
								$pageHashs = $this->trackingCache->get($objectIdentifier);
								if (!in_array($pageHash, $pageHashs)) {
									$pageHashs[] = $pageHash;
									$this->trackingCache->set($objectIdentifier, array_unique($pageHashs));
								}
								
								$this->releaseLock($exclusiveLock);
							}
						}
					} else {
						$this->trackingCache->set($objectIdentifier, array($pageHash));
					}
					
					$this->releaseLock($sharedLock);
				}
			}
		}
	
		return;
	}	
	
	/**
	 * Returns the identifier for an object
	 * 
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object
	 * @return string
	 */
	public function getObjectIdentifierForObject(\TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject $object = NULL) {
		$objectIdentifier = false;
		
		if ($object) {
			$dataMap = $this->dataMapper->getDataMap(get_class($object));
			$tableName = $dataMap->getTableName();
			$objectIdentifier = 'r-'.$tableName.'_'.$object->getUid();
		}
		
		return $objectIdentifier;
	}
	
	/**
	 * Returns the identifier for a record
	 *
	 * @param string $table
	 * @param int $id
	 * @return string
	 */
	public function getObjectIdentifierForRecord($table, $id) {
		$objectIdentifier = false;
	
		if ($id) {
			$objectIdentifier = 'r-'.$table.'_'.$id;
		}
	
		return $objectIdentifier;
	}	
	
	/**
	 * Returns the identifier for a record
	 *
	 * @param string $table
	 * @param int $pid
	 * @return string
	 */
	public function getObjectIdentifierForRepository($table, $pid) {
		$objectIdentifier = false;
	
		if ($pid) {
			$objectIdentifier = 's-'.$table.'-'.$pid;
		}
	
		return $objectIdentifier;
	}	
	
	/**
	 * Flush the complete tracking info 
	 */
	public function flushTrackingInfo() {
		$this->trackingCache->flush();		
	}
	
	/**
	 * Lock the process
	 *
	 * @param \EssentialDots\ExtbaseHijax\Lock\Lock $lockObj
	 * @param $key                  String to identify the lock in the system
	 * @param bool $exclusive       Exclusive lock (shared if FALSE)
	 * @return bool                 Returns TRUE if the lock could be obtained, FALSE otherwise
	 */
	protected function acquireLock(&$lockObj, $key, $exclusive = TRUE)	{
		try {
			if (!is_object($lockObj)) {
					/* @var $lockObj \EssentialDots\ExtbaseHijax\Lock\Lock */
				$lockObj = GeneralUtility::makeInstance('EssentialDots\\ExtbaseHijax\\Lock\\Lock', $key);
			}
	
			$success = FALSE;
			if (strlen($key)) {
				$success = $lockObj->acquire($exclusive);
				if ($success) {
					$lockObj->sysLog('Acquired lock');
				}
			}
		} catch (\Exception $e) {
			GeneralUtility::sysLog('Locking: Failed to acquire lock: '.$e->getMessage(), 'cms', GeneralUtility::SYSLOG_SEVERITY_ERROR);
			$success = FALSE;	// If locking fails, return with FALSE and continue without locking
		}
	
		return $success;
	}
	
	/**
	 * Release the lock
	 *
	 * @param	\EssentialDots\ExtbaseHijax\Lock\Lock	Reference to a locking object
	 * @return	boolean		Returns TRUE on success, FALSE otherwise
	 * @see acquireLock()
	 */
	protected function releaseLock(&$lockObj) {
		$success = FALSE;
			// If lock object is set and was acquired, release it:
		if (is_object($lockObj) && $lockObj instanceof \EssentialDots\ExtbaseHijax\Lock\Lock && $lockObj->getLockStatus()) {
			$success = $lockObj->release();
			$lockObj->sysLog('Released lock');
			$lockObj = NULL;
		}
		return $success;
	}	
}