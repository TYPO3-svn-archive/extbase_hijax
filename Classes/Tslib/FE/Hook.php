<?php
namespace EssentialDots\ExtbaseHijax\Tslib\FE;

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

class Hook implements \TYPO3\CMS\Core\SingletonInterface {
	/**
	 * @var int
	 */
	protected static $loopCount = 0;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 */
	protected $objectManager;
		
	/**
	 * Extension Configuration
	 *
	 * @var \EssentialDots\ExtbaseHijax\Configuration\ExtensionInterface
	 */
	protected $extensionConfiguration;
	
	/**
	 * @var \EssentialDots\ExtbaseHijax\Event\Dispatcher
	 */
	protected $hijaxEventDispatcher;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->initializeObjectManager();
	}
	
	/**
	 * Initializes the Object framework.
	 *
	 * @return void
	 */
	protected function initializeObjectManager() {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->extensionConfiguration = $this->objectManager->get('EssentialDots\\ExtbaseHijax\\Configuration\\ExtensionInterface');
		$this->hijaxEventDispatcher = $this->objectManager->get('EssentialDots\\ExtbaseHijax\\Event\\Dispatcher');
	}

	/**
	 * @param array $params
	 * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
	 */
	public function contentPostProcAll($params, $pObj) {
		$this->contentPostProc($params, $pObj, 'all');
	}

	/**
	 * @param array $params
	 * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
	 */
	public function contentPostProcOutput($params, $pObj) {
		$this->contentPostProc($params, $pObj, 'output');
	}
	
	/**
	 * @param array $params
	 * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
	 * @param string $hookType
	 */
	protected function contentPostProc($params, $pObj, $hookType) {		
		if ($this->extensionConfiguration->shouldIncludeEofe() && !$this->extensionConfiguration->hasIncludedEofe()) {
			$this->extensionConfiguration->setIncludedEofe(true);
			
			$eofe = $pObj->cObj->cObjGetSingle(
				$pObj->tmpl->setup['config.']['extbase_hijax.']['eofe'], 
				$pObj->tmpl->setup['config.']['extbase_hijax.']['eofe.']
			);
			
			$pObj->content = str_ireplace('</body>',  $eofe . '</body>', $pObj->content);
		}
		
		if ($this->extensionConfiguration->shouldIncludeSofe() && !$this->extensionConfiguration->hasIncludedSofe()) {
			$this->extensionConfiguration->setIncludedSofe(true);

			$sofe = $pObj->cObj->cObjGetSingle(
				$pObj->tmpl->setup['config.']['extbase_hijax.']['sofe'], 
				$pObj->tmpl->setup['config.']['extbase_hijax.']['sofe.']
			);
			
			$pObj->content = preg_replace('/<body([^>]*)>/msU',  '<body$1>'.$sofe, $pObj->content);
		}
		
		$bodyClass = $pObj->tmpl->setup['config.']['extbase_hijax.']['bodyClass'];
		if ($bodyClass && !$this->extensionConfiguration->hasAddedBodyClass()) {
			
			$matches = array();
			preg_match('/<body([^>]*)class="([^>]*)">/msU', $pObj->content, $matches);
			$count = 0;
			if (count($matches)) {
				$classes = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(" ", $matches[2], true);
				if (!in_array($bodyClass, $classes)) {
					$pObj->content = preg_replace('/<body([^>]*)class="([^>]*)">/msU',  '<body$1class="$2 '.$bodyClass.'">', $pObj->content, -1, $count);
				}
			} else {
				$pObj->content = preg_replace('/<body([^>]*)>/msU',  '<body$1 class="'.$bodyClass.'">', $pObj->content, -1, $count);
			}
			if ($count) {
				$this->extensionConfiguration->setAddedBodyClass(true);
			}
		}

		if ($hookType=='output') {
			while ($this->hijaxEventDispatcher->hasPendingNextPhaseEvents()) {
					// trick to force double rendering of some content elements
				$GLOBALS['TSFE']->recordRegister = array();
					// trick to force loading of full TS template
				if (!$pObj->tmpl->loaded) {
					$pObj->forceTemplateParsing = TRUE;
					$pObj->getConfigArray();
				}
				$this->hijaxEventDispatcher->promoteNextPhaseEvents();
				$this->hijaxEventDispatcher->parseAndRunEventListeners($pObj->content);
				if (!$pObj->config['INTincScript']) {
					$pObj->config['INTincScript'] = array();
				}
				$pObj->INTincScript();

				if (self::$loopCount++>99) {
						// preventing dead loops
					break;
				}
			}
		}

		if ($hookType=='output' || $pObj->isStaticCacheble()) {
			$this->hijaxEventDispatcher->replaceXMLCommentsWithDivs($pObj->content);
		}

		if ($hookType=='output') {
			if (count($this->nonCacheableFooterCode)) {
				ksort($this->nonCacheableFooterCode);

				$pObj->content = $this->str_lreplace('</body>',  '<!-- x123456 -->'.implode('', $this->nonCacheableFooterCode).'</body>', $pObj->content);
			}
			if (count($this->nonCacheableHeaderCode)) {
				ksort($this->nonCacheableHeaderCode);

				$pObj->content = $this->str_lreplace('</head>',  '<!-- x123456 -->'.implode('', $this->nonCacheableHeaderCode).'</head>', $pObj->content);
			}
		}
	}

	/**
	 * @param array $params
	 * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
	 */	
	public function initFEuser($params, $pObj) {
			/* @var $fe_user \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
		$fe_user = $pObj->fe_user;
		
		if ($fe_user->user && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($fe_user->formfield_status)=='login') {
			$event = new \EssentialDots\ExtbaseHijax\Event\Event('user-loggedIn', array('user'=>$fe_user->user));
			$this->hijaxEventDispatcher->notify($event);
		} elseif (!$fe_user->user && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($fe_user->formfield_status)=='logout') {
			$event = new \EssentialDots\ExtbaseHijax\Event\Event('user-loggedOut');
			$this->hijaxEventDispatcher->notify($event);
		} elseif (!$fe_user->user && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($fe_user->formfield_status)=='login') {
			$event = new \EssentialDots\ExtbaseHijax\Event\Event('user-loginFailure');
			$this->hijaxEventDispatcher->notify($event);
		}
	}

	/**
	 * @var array
	 */
	protected $nonCacheableHeaderCode = array();

	/**
	 * @var array
	 */
	protected $nonCacheableFooterCode = array();

	/**
	 * @param $name
	 * @param $source
	 */
	public function addNonCacheableHeaderCode($name, $source) {
		$this->nonCacheableHeaderCode[$name] = $source;
	}

	/**
	 * @param $name
	 * @param $source
	 */
	public function addNonCacheableFooterCode($name, $source) {
		$this->nonCacheableFooterCode[$name] = $source;
	}

	/**
	 * @param string $search
	 * @param string $replace
	 * @param string $subject
	 * @return string
	 */
	protected function str_lreplace($search, $replace, $subject) {
		$pos = strrpos($subject, $search);

		if($pos !== false) {
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}

		return $subject;
	}
}

?>