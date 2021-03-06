<?php
namespace EssentialDots\ExtbaseHijax\ViewHelpers\Widget;

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

class PaginateViewHelper extends \EssentialDots\ExtbaseHijax\Core\Widget\AbstractWidgetViewHelper {

	/**
	 * @var \EssentialDots\ExtbaseHijax\ViewHelpers\Widget\Controller\PaginateController
	 */
	protected $controller;

	/**
	 * @param \EssentialDots\ExtbaseHijax\ViewHelpers\Widget\Controller\PaginateController $controller
	 * @return void
	 */
	public function injectController(\EssentialDots\ExtbaseHijax\ViewHelpers\Widget\Controller\PaginateController $controller) {
		$this->controller = $controller;
	}

	/**
	 *
	 * @param mixed $objects
	 * @param string $as
	 * @param array $configuration
	 * @param array $variables
	 * @return string
	 */
	public function render($objects, $as, array $configuration = array('itemsPerPage' => 10, 'insertAbove' => FALSE, 'insertBelow' => TRUE, 'pagerTemplate' => FALSE, 'widgetIdentifier' => ''), $variables = array()) {
		if ($configuration['widgetIdentifier']) {
			$this->widgetContext->setWidgetIdentifier($configuration['widgetIdentifier']);
		}
		return $this->initiateSubRequest();
	}
}

?>