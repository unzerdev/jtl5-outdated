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


require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/FormRenderer.php';
require_once 'UnzerCw/Adapter/AbstractAdapter.php';


/**
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_AjaxAdapter extends UnzerCw_Adapter_AbstractAdapter {
	
	private $visibleFormFields = array();
	private $ajaxScriptUrl = null;
	private $javaScriptCallbackFunction = null;
	
	public function getPaymentAdapterInterfaceName() {
		return 'Customweb_Payment_Authorization_Ajax_IAdapter';
	}
	
	/**
	 * @return Customweb_Payment_Authorization_Ajax_IAdapter
	 */
	public function getInterfaceAdapter() {
		return parent::getInterfaceAdapter();
	}
	
	protected function preparePaymentFormPane() {
		$this->visibleFormFields = $this->getInterfaceAdapter()->getVisibleFormFields(
			$this->getOrderContext(),
			$this->getAliasTransactionObject(),
			$this->getFailedTransactionObject(),
			$this->getTransaction()->getTransactionObject()->getPaymentCustomerContext()
		);
		$this->ajaxScriptUrl = $this->getInterfaceAdapter()->getAjaxFileUrl($this->getTransaction()->getTransactionObject());
		$this->javaScriptCallbackFunction = $this->getInterfaceAdapter()->getJavaScriptCallbackFunction($this->getTransaction()->getTransactionObject());
		UnzerCw_Util::getEntityManager()->persist($this->getTransaction());
	}
	
	protected function getPaymentFormPane() {
	
		$this->preparePaymentFormPane();
	
		$output = '<div id="unzercw-checkout-form-pane">';
		
		$output .= '<script src="' . $this->ajaxScriptUrl . '"></script>';
		
		$output .= '<script type="text/javascript">';
		$output .= "\n var unzercw_ajax_submit_callback = " . $this->javaScriptCallbackFunction . ";\n";
		$output .= '</script>';
		
		$output .= '<form id="unzercw-confirmation-ajax-authorization-form" class="unzercw-confirmation-form" accept-charset="UTF-8">';
	
		$visibleFormFields = $this->getVisibleFormFields();
		if ($visibleFormFields !== null && count($visibleFormFields) > 0) {
			$renderer = new UnzerCw_FormRenderer();
			$renderer->setCssClassPrefix('unzercw-');
			$output .= $renderer->renderElements($visibleFormFields);
		}
	
		$output .= '</form>';
		
		$output .= $this->getOrderConfirmationButton();
	
		$output .= '</div>';
	
		return $output;
	}
	
	protected function getVisibleFormFields() {
		return $this->visibleFormFields;
	}
		
}