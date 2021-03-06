<?php
namespace EssentialDots\ExtbaseHijax\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
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
 * @author Nikola Stojiljkovic <nikola.stojiljkovic@essentialdots.com>
 */
class ContentElementController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * @var \EssentialDots\ExtbaseHijax\Event\Dispatcher
	 */
	protected $hijaxEventDispatcher;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
	 */
	protected $typoScriptService;
	
	/**
	 * Injects the event dispatcher
	 *
	 * @param \EssentialDots\ExtbaseHijax\Event\Dispatcher $eventDispatcher
	 * @return void
	 */
	public function injectEventDispatcher(\EssentialDots\ExtbaseHijax\Event\Dispatcher $eventDispatcher) {
		$this->hijaxEventDispatcher = $eventDispatcher;
	}	
	
	/**
	 * Injects the TS service
	 *
	 * @param \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
	 * @return void
	 */
	public function injectTypoScriptService(\TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService) {
		$this->typoScriptService = $typoScriptService;
	}	
	
	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * Override this method to solve tasks which all actions have in
	 * common.
	 *
	 * @return void
	 * @api
	 */
	protected function initializeAction() {
		if ($this->settings['listenOnEvents']) {
			$eventNames = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->settings['listenOnEvents']);
			foreach ($eventNames as $eventName) {
				$this->hijaxEventDispatcher->connect($eventName);
			}
		}
	}	
	
	/**
	 * Renders content element (cacheable)
	 */
	public function userAction() {

	}

	/**
	 * Renders content element (non-cacheable)
	 */
	public function userIntAction() {

	}
}
