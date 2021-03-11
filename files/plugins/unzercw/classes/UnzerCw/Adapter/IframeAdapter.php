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

require_once 'Customweb/Util/Html.php';

require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Adapter/AbstractAdapter.php';


/**
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_IframeAdapter extends UnzerCw_Adapter_AbstractAdapter {
	
	private $visibleFormFields = array();
	private $formActionUrl = null;
	private $iframeHeight = 500;
	private $iframeUrl = null;
	private $errorMessage = '';
	
	public function getPaymentAdapterInterfaceName() {
		return 'Customweb_Payment_Authorization_Iframe_IAdapter';
	}
	
	/**
	 * @return Customweb_Payment_Authorization_Iframe_IAdapter
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
		
		if ($this->visibleFormFields !== null && count($this->visibleFormFields) > 0) {
			$this->formActionUrl = UnzerCw_Util::getUrl(
				'process',
				'iframe',
				array('cw_transaction_id' => $this->getTransaction()->getTransactionId())
			);
		}
		else {
			$this->prepareWithFormData(array(), $this->getTransaction());
		}
		UnzerCw_Util::getEntityManager()->persist($this->getTransaction());
	}
	
	public function prepareWithFormData(array $formData, UnzerCw_Entity_Transaction $transaction) {
		$this->iframeUrl = $this->getInterfaceAdapter()->getIframeUrl($transaction->getTransactionObject(), $formData);
		$this->iframeHeight = $this->getInterfaceAdapter()->getIframeHeight($transaction->getTransactionObject(), $formData);
		if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
			$this->iframeUrl = null;
			$errorMessage = current($transaction->getTransactionObject()->getErrorMessages());
			/* @var $errorMessage Customweb_Payment_Authorization_IErrorMessage */
			if (is_object($errorMessage)) {
				$this->errorMessage =  Customweb_Util_Html::convertSpecialCharacterToEntities($errorMessage->getUserMessage());
			}
			else {
				$this->errorMessage = UnzerCw_Language::_("Failed to initialize transaction with an unknown error");
			}
			
		}
	}
	
	public function getIframe() {
		if ($this->iframeUrl !== null) {
			return '<iframe class="unzercw-iframe" src="' . $this->iframeUrl . '" style="height: ' . $this->iframeHeight . 'px;"></iframe>';
		}
		else {
			return '<p class="box_error">' .  $this->errorMessage . '</p>';
		}
	}
	
	protected function getOrderConfirmationButton() {
		if ($this->formActionUrl === null) {
			return '';
		}
		else {
			return parent::getOrderConfirmationButton();
		}
	}
	
	protected function getAdditionalFormHtml() {
		if ($this->formActionUrl === null) {
			return $this->getIframe();
		}
		else {
			return parent::getAdditionalFormHtml();
		}
	}
	
	protected function getVisibleFormFields() {
		return $this->visibleFormFields;
	}
	
	protected function getFormActionUrl() {
		return $this->formActionUrl;
	}
	
}