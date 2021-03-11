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

require_once 'Customweb/Payment/Authorization/Hidden/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/Server/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/Ajax/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/PaymentPage/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/Iframe/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/Widget/ITransactionContext.php';
require_once 'Customweb/Payment/Authorization/IUpdateTransactionContext.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/PaymentCustomerContext.php';
require_once 'UnzerCw/Entity/Transaction.php';


class UnzerCw_TransactionContext implements Customweb_Payment_Authorization_PaymentPage_ITransactionContext,
Customweb_Payment_Authorization_Hidden_ITransactionContext, Customweb_Payment_Authorization_Server_ITransactionContext,
Customweb_Payment_Authorization_Iframe_ITransactionContext, Customweb_Payment_Authorization_Ajax_ITransactionContext,
Customweb_Payment_Authorization_IUpdateTransactionContext, Customweb_Payment_Authorization_Widget_ITransactionContext
{
	protected $aliasTransactionId = NULL;
	protected $paymentCustomerContext = null;
	protected $orderContext;
	protected $databaseTransactionId = NULL;
	private $databaseTransaction = NULL;

	protected $customParameters = array();

	public function __construct(UnzerCw_Entity_Transaction $transaction, UnzerCw_OrderContext $orderContext, $aliasTransactionId = NULL) {

		$aliasTransactionIdCleaned = NULL;
		if (UnzerCw_Util::isAliasManagerActive($orderContext)) {
			if ($aliasTransactionId === NULL || $aliasTransactionId === 'new') {
				$aliasTransactionIdCleaned = 'new';
			}
			else {
				$aliasTransactionIdCleaned = intval($aliasTransactionId);
			}
		}
		$this->aliasTransactionId = $aliasTransactionIdCleaned;
		$this->paymentCustomerContext = UnzerCw_Entity_PaymentCustomerContext::getPaymentCustomerContext($orderContext->getCustomerId());
		$this->orderContext = $orderContext;
		$this->databaseTransaction = $transaction;
		$this->databaseTransactionId = $transaction->getTransactionId();
		$this->customParameters['cw_transaction_id'] = $transaction->getTransactionId();
	}

	/**
	 * @return UnzerCw_Entity_Transaction
	 */
	public function getDatabaseTransaction() {
		if ($this->databaseTransaction === NULL) {
			$this->databaseTransaction = UnzerCw_Entity_Transaction::loadById($this->databaseTransactionId);
		}

		return $this->databaseTransaction;
	}

	public function getCapturingMode() {
		return null;
	}

	public function getJavaScriptSuccessCallbackFunction() {
		return '
		function (redirectUrl) {
			window.location = redirectUrl
		}';
	}

	public function getJavaScriptFailedCallbackFunction() {
		return '
		function (redirectUrl) {
			window.location = redirectUrl
		}';
	}

	public function __sleep() {
		return array('aliasTransactionId', 'paymentCustomerContext', 'orderContext', 'databaseTransactionId', 'customParameters');
	}

	public function getOrderContext() {
		return $this->orderContext;
	}

	public function getTransactionId() {
		return $this->getDatabaseTransaction()->getTransactionId();
	}
	

	public function getOrderId() {
		return $this->getDatabaseTransaction()->getOrderId();
	}
	
	public function isOrderIdUnique() {
		return false;
	}

	public function createRecurringAlias() {
		return false;
	}

	public function getAlias() {
		if ($this->aliasTransactionId === 'new') {
			return 'new';
		}

		if ($this->aliasTransactionId !== null) {
			$transcation = UnzerCw_Entity_Transaction::loadById($this->aliasTransactionId);
			$customerId = $transcation->getCustomerId();
			if (empty($customerId)) {
				return null;
			}
			if ($transcation !== null && $transcation->getTransactionObject() !== null && $transcation->getCustomerId() == $this->getOrderContext()->getCustomerId()) {
				return $transcation->getTransactionObject();
			}
		}

		return null;
	}

	public function getCustomParameters() {
		return $this->customParameters;
	}

	public function getSuccessUrl() {
		return UnzerCw_Util::getUrl('process', 'success', array(), true);
	}

	public function getFailedUrl() {
		return UnzerCw_Util::getUrl('process', 'failed', array(), true);
	}

	public function getPaymentCustomerContext() {
		return $this->paymentCustomerContext;
	}

	public function getNotificationUrl() {
		return UnzerCw_Util::getUrl('process', 'notify', array(), true);
	}

	public function getIframeBreakOutUrl() {
		return UnzerCw_Util::getUrl('process', 'iframeBreakout', array(), true);
	}

	public function getUpdateUrl() {
		return UnzerCw_Util::getUrl('update', 'notify', array(), true);
	}

}