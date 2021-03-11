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
require_once 'Customweb/Form/IButton.php';
require_once 'Customweb/Form/Renderer.php';



class UnzerCw_BackendFormRenderer extends Customweb_Form_Renderer
{

	public function getElementCssClass() {
		return 'form-group clearfix';
	}
	
	public function getElementLabelCssClass() {
		return 'control-label col-sm-4';
	}
	
	public function getControlCssClass() {
		return 'controls col-sm-8';
	}
	
	public function getControlCss(Customweb_Form_Control_IControl $control) {
		return 'form-control ' . $control->getCssClass();
	}
	
	public function getDescriptionCssClass() {
		return 'help-block col-sm-8 col-sm-offset-4';
	}
	
	public function renderControl(Customweb_Form_Control_IControl $control) {
		if (!($control instanceof Customweb_Form_Control_MultiControl)) {
			$control->setCssClass($this->getControlCss($control));
		}
		return $control->render($this);
	}
		
	/**
	 * @param Customweb_Form_IButton $button
	 * @return string
	 */
	protected function getButtonClasses(Customweb_Form_IButton $button)
	{
		$classes = array(
			'btn ',
		);
		
		switch ($button->getType()) {
			case Customweb_Form_IButton::TYPE_CANCEL:
				$classes[] = 'btn-danger';
				break;
			case Customweb_Form_IButton::TYPE_DEFAULT:
				$classes[] = 'btn-default';
				break;
			case Customweb_Form_IButton::TYPE_INFO:
				$classes[] = 'btn-primary';
				break;
			case Customweb_Form_IButton::TYPE_SUCCESS:
				$classes[] = 'btn-success';
				break;
		}
		
		return implode(' ', $classes);
	}

}