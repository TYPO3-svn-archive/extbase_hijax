<?php
namespace EssentialDots\ExtbaseHijax\Core\Widget;

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

class WidgetContext extends \TYPO3\CMS\Fluid\Core\Widget\WidgetContext {
	/**
	 * Controller Context to use
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
	 */
	protected $parentControllerContext;
	
	/**
	 * @return \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
	 */
	public function getParentControllerContext() {
		return $this->parentControllerContext;
	}

	/**
	 * @param \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $parentControllerContext
	 */
	public function setParentControllerContext($parentControllerContext) {
		$this->parentControllerContext = $parentControllerContext;
	}
}
?>