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

require_once 'Customweb/Util/Url.php';
require_once 'Customweb/Util/Html.php';

require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/FormRenderer.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Adapter/IAdapter.php';


abstract class UnzerCw_Adapter_AbstractAdapter implements UnzerCw_Adapter_IAdapter {
	
	/**
	 * @var Customweb_Payment_Authorization_IAdapter
	 */
	private $interfaceAdapter;
	
	/**
	 * @var Customweb_Payment_Authorization_IOrderContext
	 */
	private $orderContext;
	
	/**
	 * @var UnzerCw_PaymentMethod
	 */
	protected $paymentMethod;
	
	/**
	 * @var UnzerCw_Entity_Transaction
	 */
	protected $failedTransaction = null;
	
	/**
	 * @var UnzerCw_Entity_Transaction
	 */
	protected $aliasTransaction = null;
	
	/**
	 * @var int	 
	 */
	private $aliasTransactionId = null;
	
	/**
	 * @var UnzerCw_Entity_Transaction
	 */
	private $transaction = null;
	
	/**
	 * @var string
	 */
	private $redirectUrl = null;
	
	public function setInterfaceAdapter(Customweb_Payment_Authorization_IAdapter $interface) {
		$this->interfaceAdapter = $interface;
	}
	
	public function getInterfaceAdapter() {
		return $this->interfaceAdapter;
	}
	
	
	public function isHeaderRedirectionSupported() {
		if (false) {
			return false;
		}
		
		if ($this->getRedirectionUrl() === null) {
			return false;
		}
		else {
			return true;
		}
	}
	
	
	protected function setRedirectUrl($redirectUrl) {
		$this->redirectUrl = $redirectUrl;
		return $this;
	}
	
	public function getRedirectionUrl() {
		return $this->redirectUrl;
	}
	
	
	public function prepareCheckout(UnzerCw_PaymentMethod $paymentMethod, Customweb_Payment_Authorization_IOrderContext $orderContext, $failedTransaction) {
		
		$this->paymentMethod = $paymentMethod;
		$this->failedTransaction = $failedTransaction;
		$this->orderContext = $orderContext;

		$this->aliasTransaction = null;
		$this->aliasTransactionId = null;
		
		if (isset($_REQUEST['unzercw_alias'])) {
			if ($_REQUEST['unzercw_alias'] != 'new') {
				$this->aliasTransaction = UnzerCw_Entity_Transaction::loadById((int)$_REQUEST['unzercw_alias']);
			}
			if ($this->aliasTransaction !== null) {
				$this->aliasTransactionId = $this->aliasTransaction->getTransactionId();
			}
			
			if ($_REQUEST['unzercw_alias'] == 'new' || ($this->aliasTransactionId === null && UnzerCw_Util::isAliasManagerActive($orderContext))) {
				$this->aliasTransactionId = 'new';
			}
		}
				
		$transaction = $this->getTransaction();
		$this->preparePaymentFormPane();
		if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
			$this->setRedirectUrl(Customweb_Util_Url::appendParameters(
				$transaction->getTransactionObject()->getTransactionContext()->getFailedUrl(),
				$transaction->getTransactionObject()->getTransactionContext()->getCustomParameters()
			));
		}
	}
	
	
	public function getCheckoutPageHtml() {
		
		if (false) {
			return'<div style="border: 1px solid #ff0000; background: #ffcccc; font-weight: bold; padding: 5px;">' 
				. UnzerCw_Language::_('We experienced a problem with your sellxed payment extension. For more information, please visit the information page of the plugin.') . 
			'</div>';
		}
		
		$output = '<div id="unzercw-checkout-page">';
		
		$output .= $this->getAliasDropDown();
		$output .= $this->getPaymentFormPane();
		
		$output .= '</div>';
		
		return $output;
	}
	
	
	protected function getAliasDropDown() {
		$orderContext = $this->getOrderContext();
		
		if (!UnzerCw_Util::isAliasManagerActive($orderContext)) {
			return '';
		}
		$aliasTransactions =  UnzerCw_Util::getAliasHandler()->getAliasTransactions($orderContext);
		if (count($aliasTransactions) <= 0) {
			return '';
		}
		
		$output = '<div class="unzercw-alias-pane"><label for="unzercw_alias">' . UnzerCw_Language::_("Use Stored Card") . '</label>';
		
		$output .= '<select name="unzercw_alias" id="unzercw_alias" class="unzercw-alias-dropdown">';
		$output .= '<option value="new">' . UnzerCw_Language::_("Use a new Card") . '</option>';
		
		$aliasOptions = array();
		$found = false;
		foreach ($aliasTransactions as $transaction) {
			$selected = $this->aliasTransactionId == $transaction->getTransactionId();
			$aliasOptions[] = array(
				'value' => $transaction->getTransactionId(),
				'display' => $transaction->getAliasForDisplay(),
				'selected' => $selected
			);
			$found = $found || $selected;
		}
		if(!$found && !empty($aliasOptions)) {
			$output = str_replace('option value="new"', 'option value="new" selected', $output);
		}
		foreach($aliasOptions as $aliasOption) {
			$output .= '<option value="' . $aliasOption['value'] . '"';
			if($aliasOption['selected']) {
				$output .= ' selected="selected"';
			}
			$output .= '>' . $aliasOption['display'] . '</option>';
		}
		
		$output .= '</select></div>';
		
		return $output;
	}
	
	protected function getOrderContext() {
		return $this->orderContext;
	}
	
	/**
	 * @return UnzerCw_Entity_Transaction
	 */
	private function createNewTransaction() {
		$orderContext = $this->getOrderContext();
		return $this->paymentMethod->newTransaction($this->getOrderContext(), $this->aliasTransactionId, $this->getFailedTransactionObject());
	}
	
	/**
	 * @return UnzerCw_Entity_Transaction
	 */
	public function getTransaction() {
		if ($this->transaction === null) {
			$this->transaction = $this->createNewTransaction();
		}
		return $this->transaction;
	}
	
	protected function getAliasTransactionObject() {
		$aliasTransactionObject = null;
		$orderContext = $this->getOrderContext();
		if ($this->aliasTransactionId === 'new') {
			$aliasTransactionObject = 'new';
		}
		if ($this->aliasTransaction !== null && $this->aliasTransaction->getCustomerId() !== null && $this->aliasTransaction->getCustomerId() == $orderContext->getCustomerId()) {
			$aliasTransactionObject = $this->aliasTransaction->getTransactionObject();
		}
		
		return $aliasTransactionObject;
	}
	
	protected function getFailedTransactionObject() {
		$failedTransactionObject = null;
		$orderContext = $this->getOrderContext();
		if ($this->failedTransaction !== null && $this->failedTransaction->getCustomerId() !== null && $this->failedTransaction->getCustomerId() == $orderContext->getCustomerId()) {
			$failedTransactionObject = $this->failedTransaction->getTransactionObject();
		}
		return $failedTransactionObject;
	}
	
	protected function getPaymentFormPane() {
		$output = '<div id="unzercw-checkout-form-pane">';
		
		$actionUrl = $this->getFormActionUrl();
		
		if ($actionUrl !== null && !empty($actionUrl)){
			$output .= '<form action="' . $actionUrl . '" method="POST" class="unzercw-confirmation-form form"  accept-charset="UTF-8"><fieldset class="outer">';
		}
		
		$visibleFormFields = $this->getVisibleFormFields();
		if ($visibleFormFields !== null && count($visibleFormFields) > 0) {
			$renderer = new UnzerCw_FormRenderer();
			$renderer->setCssClassPrefix('unzercw-');
			$displayName = $this->getTransaction()->getTransactionObject()->getTransactionContext()->getOrderContext()->getPaymentMethod()->getPaymentMethodDisplayName();
			$output .= '<fieldset><legend>' . $displayName . '</legend>' . $renderer->renderElements($visibleFormFields) . '</fieldset>';
			$output .= '<p class="box_info tright"><em>*</em> ' . UnzerCw_Language::_('Mandatory fields') .'</p>';
		}
		
		$hiddenFormFields = $this->getHiddenFormFields();
		if ($hiddenFormFields !== null && count($hiddenFormFields) > 0) {
				$output .= Customweb_Util_Html::buildHiddenInputFields($hiddenFormFields);
		}
		
		$output .= $this->getAdditionalFormHtml();
		
		$output .= $this->getOrderConfirmationButton();
		
		if ($actionUrl !== null && !empty($actionUrl)){
			$output .= '</fieldset></form>';
		}
		
		$output .= '</div>';
		
		return $output;
	}
	
	protected function getAdditionalFormHtml() {
		return '';
	}
	
	/**
	 * Method to load some data before the payment pane is rendered.
	 */
	protected function preparePaymentFormPane() {
		
	}
	
	protected function getVisibleFormFields() {
		return array();
	}
	
	protected function getFormActionUrl() {
		return null;
	}
	
	protected function getHiddenFormFields() {
		return array();
	}
	
	protected function getOrderConfirmationButton() {
		$confirmText = UnzerCw_Language::_('Pay');
		
		// We can only go back into the order process, when no order
		// exists.
		$output = '<div class="buttons unzercw-confirmation-buttons tright">';
		$orderId = $this->getTransaction()->getOrderInternalId();
		
		if ($orderId === null ){
			$output .= '<a href="' . UnzerCw_Util::getStoreBaseUrl() . '/bestellvorgang.php?editZahlungsart=1" class="submit btn btn-default btn-lg back-button">' . UnzerCw_Language::_('Change payment method') . '</a> ';
		}
		
		$output .= '<input type="submit" value="' . $confirmText . '" class="submit btn btn-primary btn-lg pull-right" /></div>';
		
		return $output;
	}
}