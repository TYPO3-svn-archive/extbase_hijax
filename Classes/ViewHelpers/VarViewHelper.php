<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
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

class Tx_ExtbaseHijax_ViewHelpers_VarViewHelper extends Tx_Fluid_ViewHelpers_ImageViewHelper {


	public function initializeArguments() {
		$this->registerArgument('expression', 'string', 'Expression to evaluate - can also be set as tag content');
		$this->registerArgument('calculate', 'boolean', 'Should the expression be evaluated or just copied');
		$this->registerArgument('as', 'string', 'Variable name to insert result into, suppresses output');
	}

	public function render() {
		if ($this->arguments['expression']) {
			$expression = $this->arguments['expression'];
		} else {
			$expression = $this->renderChildren();
		}
		if ($this->arguments['calculate']) {
			$evalString = "\$value = floatval($expression);";
			@eval($evalString);
		} else {
			$value = $expression;
		}
		
		if ($this->arguments['as']) {
			$variableNameArr = t3lib_div::trimExplode('.', $this->arguments['as'], TRUE, 2);
			
			$variableName = $variableNameArr[0];
			$attributePath = $variableNameArr[1];
			
			if ($this->templateVariableContainer->exists($variableName)) {
				$oldValue = $this->templateVariableContainer->get($variableName);
				$this->templateVariableContainer->remove($variableName);
			}
			if ($attributePath) {
				if ($oldValue && is_array($oldValue)) {
					$templateValue = $oldValue;
					$templateValue[$attributePath] = $value;
				} else {
					$templateValue = array(
						$attributePath => $value
					);
				}
			} else {
				$templateValue = $value;
			}
			$this->templateVariableContainer->add($variableName, $templateValue);
		} else {
			return $value;
		}
	}

}

?>