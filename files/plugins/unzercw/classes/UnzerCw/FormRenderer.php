<?php 
/**
  * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 */

require_once 'Customweb/Form/Control/MultiControl.php';
require_once 'Customweb/Form/Renderer.php';



class UnzerCw_FormRenderer extends Customweb_Form_Renderer {

	public function getElementCssClass() {
		return 'form-group form-group clearfix';
	}
	
	public function getElementLabelCssClass() {
		return 'control-label control-label';
	}
	
	public function getControlCssClass() {
		return 'controls controls';
	}
	
	public function getControlCss(Customweb_Form_Control_IControl $control) {
		return 'form-control form-control ' . $control->getCssClass();
	}
	
	public function getDescriptionCssClass() {
		return 'help-block help-block';
	}
	
	public function renderControl(Customweb_Form_Control_IControl $control) {
		if (!($control instanceof Customweb_Form_Control_MultiControl)) {
			$control->setCssClass($this->getControlCss($control));
		}
		return $control->render($this);
	}
	
	public function renderControlPrefix(Customweb_Form_Control_IControl $control, $controlTypeClass)
	{
		$cssClasses = '';
		if ($control instanceof Customweb_Form_Control_MultiControl) {
			$cssClasses = ' ' . $this->getCssClassPrefix() . $controlTypeClass;
		}
		else {
			$cssClasses = $this->getCssClassPrefix() . $this->getControlCssClass() . ' ' . $this->getCssClassPrefix() . $controlTypeClass;
		}
		return '<div class="' . $cssClasses . '" id="' . $control->getControlId() . '-wrapper">';
	}
	
	
}