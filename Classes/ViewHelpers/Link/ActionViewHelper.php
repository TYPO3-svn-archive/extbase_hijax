<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
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

class Tx_ExtbaseHijax_ViewHelpers_Link_ActionViewHelper extends Tx_Fluid_ViewHelpers_Link_ActionViewHelper {

	/**
	 * @var Tx_ExtbaseHijax_MVC_Dispatcher
	 */
	protected $mvcDispatcher;
	
	/**
	 * @var Tx_Extbase_Service_ExtensionService
	 */
	protected $extensionService;

	/**
	 * @var Tx_ExtbaseHijax_Event_Dispatcher
	 */
	protected $hijaxEventDispatcher;

	/**
	 * Injects the event dispatcher
	 *
	 * @param Tx_ExtbaseHijax_Event_Dispatcher $eventDispatcher
	 * @return void
	 */
	public function injectEventDispatcher(Tx_ExtbaseHijax_Event_Dispatcher $eventDispatcher) {
		$this->hijaxEventDispatcher = $eventDispatcher;
	}

	/**
	 * Injects the MVC dispatcher
	 *
	 * @param Tx_ExtbaseHijax_MVC_Dispatcher $mvcDispatcher
	 * @return void
	 */
	public function injectMVCDispatcher(Tx_ExtbaseHijax_MVC_Dispatcher $mvcDispatcher) {
		$this->mvcDispatcher = $mvcDispatcher;
	}
	
	/**
	 * @param Tx_Extbase_Service_ExtensionService $extensionService
	 * @return void
	 */
	public function injectExtensionService(Tx_Extbase_Service_ExtensionService $extensionService) {
		$this->extensionService = $extensionService;
	}
			
	/**
	 * @param string $action
	 * @param array $arguments
	 * @param string $controller
	 * @param string $extensionName
	 * @param string $pluginName
	 * @param string $format
	 * @param int $pageUid
	 * 
	 * @return string
	 */
	public function render($action = NULL, array $arguments = array(), $controller = NULL, $extensionName = NULL, $pluginName = NULL, $format = '', $pageUid = 0) {
		$request = $this->mvcDispatcher->getCurrentRequest();

		/* @var $listener Tx_ExtbaseHijax_Event_Listener */
		$listener = $this->mvcDispatcher->getCurrentListener();

		if ($request) {
			if ($action === NULL) {
				$action = $request->getControllerActionName();
			}

			if ($controller === NULL) {
				$controller = $request->getControllerName();
			}

			if ($extensionName === NULL) {
				$extensionName = $request->getControllerExtensionName();
			}

			if ($pluginName === NULL && TYPO3_MODE === 'FE') {
				$pluginName = $this->extensionService->getPluginNameByAction($extensionName, $controller, $action);
			}
			if ($pluginName === NULL) {
				$pluginName = $request->getPluginName();
			}
		}

		$additionalArguments = array();
		$this->hA('r[0][arguments]', $arguments, $additionalArguments);

		$language = intval($GLOBALS['TSFE'] ? $GLOBALS['TSFE']->sys_language_content : 0);
		$additionalParams = "&r[0][extension]={$extensionName}&r[0][plugin]={$pluginName}&r[0][controller]={$controller}&r[0][action]={$action}&r[0][format]={$format}&r[0][settingsHash]={$listener->getId()}&eID=extbase_hijax_dispatcher&L={$language}";

		if ($additionalArguments) {
			$additionalParams .= '&'.implode('&', $additionalArguments);
		}

		/* @var $cObj tslib_cObj */
		$cObj = t3lib_div::makeInstance('tslib_cObj');

		$uri = $cObj->typoLink('', array(
			'returnLast' => 'url',
			'additionalParams' => $additionalParams,
			'parameter' => $pageUid ? $pageUid : ($GLOBALS['TSFE'] ? $GLOBALS['TSFE']->id : 0)
		));

		$this->tag->addAttribute('href', $uri);
		$this->tag->setContent($this->renderChildren());
		$this->tag->forceClosingTag(TRUE);

		return $this->tag->render();
	}
	
	/**
	 * @param string $namespace
	 * @param array $arguments
	 * @param array $additionalArguments
	 */
	protected function hA($namespace, $arguments, &$additionalArguments) {
		if ($arguments) {
			foreach ($arguments as $i => $v) {
				if (is_array($v)) {
					$this->hA($namespace."[$i]", $v, $additionalArguments);
				} else {
					$additionalArguments[] = $namespace."[$i]=".rawurlencode($v);
				}
			}
		}
	}
}

?>