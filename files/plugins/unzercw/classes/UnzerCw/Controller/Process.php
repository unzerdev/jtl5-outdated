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

require_once 'UnzerCw/Controller/Abstract.php';
require_once 'Customweb/Util/System.php';
require_once 'Customweb/Util/Html.php';
require_once 'UnzerCw/Cron.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Log.php';
require_once 'UnzerCw/Adapter/IframeAdapter.php';
require_once 'UnzerCw/PaymentMethod.php';
require_once 'UnzerCw/Cron.php';
require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Adapter/WidgetAdapter.php';
require_once 'UnzerCw/Controller/Abstract.php';



class UnzerCw_Controller_Process extends UnzerCw_Controller_Abstract {


	public function cronAction() {
		$cron = new UnzerCw_Cron();
		$cron->run();
	}

	public function notifyAction() {
		$dispatcher = new Customweb_Payment_Endpoint_Dispatcher(UnzerCw_Util::getEndpointAdapter(), UnzerCw_Util::getContainer(), array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		));
		$response = new Customweb_Core_Http_Response($dispatcher->invokeControllerAction(Customweb_Core_Http_ContextRequest::getInstance(), 'process', 'index'));
		$response->send();
		die();
	}
	
	public function serverAuthorizationAction() {
		$transaction = $this->getTransactionFromRequest();
		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adapter = $adapterFactory->getAuthorizationAdapterByName($transaction->getAuthorizationType());
		if (!($adapter instanceof Customweb_Payment_Authorization_Server_IAdapter)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Payment_Authorization_Server_IAdapter');
		}
		$transactionObject = $transaction->getTransactionObject();
		$response = new Customweb_Core_Http_Response($adapter->processAuthorization($transactionObject, $_REQUEST));
		UnzerCw_Util::getTransactionHandler()->persistTransactionObject($transactionObject);
		$response->send();
		die();
	}

	public function iframeAction() {
		$transaction = $this->getTransactionFromRequest();

		$this->addBreadcrumbItem(UnzerCw_Language::_('Checkout'), UnzerCw_Util::getStoreBaseUrl() . '/bestellvorgang.php');

		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adpater = UnzerCw_Util::getJtlAdapterByPaymentAdapter($adapterFactory->getAuthorizationAdapterByName($transaction->getTransactionObject()->getAuthorizationMethod()));

		if (!($adpater instanceof UnzerCw_Adapter_IframeAdapter)) {
			throw new Exception("Only supported for iframe authorization.");
		}

		$adpater->prepareWithFormData($_REQUEST, $transaction);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		$this->assign('iframe', $adpater->getIframe());
		$this->assign('method_name', $transaction->getTransactionObject()->getPaymentMethod()->getPaymentMethodDisplayName());
		$this->display('iframe');
	}

	public function widgetAction() {
		$transaction = $this->getTransactionFromRequest();

		$this->addBreadcrumbItem(UnzerCw_Language::_('Checkout'), UnzerCw_Util::getStoreBaseUrl() . '/bestellvorgang.php');

		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adpater = UnzerCw_Util::getJtlAdapterByPaymentAdapter($adapterFactory->getAuthorizationAdapterByName($transaction->getTransactionObject()->getAuthorizationMethod()));

		if (!($adpater instanceof UnzerCw_Adapter_WidgetAdapter)) {
			throw new Exception("Only supported for widget authorization.");
		}

		$adpater->prepareWithFormData($_REQUEST, $transaction);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		$this->assign('widget', $adpater->getWidget());
		$this->assign('method_name', $transaction->getTransactionObject()->getPaymentMethod()->getPaymentMethodDisplayName());
		$this->display('widget');
	}

	public function iframeBreakoutAction() {

		$transaction = $this->getTransactionFromRequest();

		$redirectionUrl = '';
		if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
			$redirectionUrl = Customweb_Util_Url::appendParameters(
				$transaction->getTransactionObject()->getTransactionContext()->getFailedUrl(),
				$transaction->getTransactionObject()->getTransactionContext()->getCustomParameters()
			);
		}
		else {
			$redirectionUrl = Customweb_Util_Url::appendParameters(
				$transaction->getTransactionObject()->getTransactionContext()->getSuccessUrl(),
				$transaction->getTransactionObject()->getTransactionContext()->getCustomParameters()
			);
		}

		$this->assign('breakout_url', $redirectionUrl);
		$this->assign('continue', UnzerCw_Language::_('Continue'));

		$this->display('iframe-breakout');
	}

	public function paymentAction() {

		$failedTransaction = null;
		$errorMessage = '';
		$order = null;
		$paymentMethod = null;
		if (isset($_REQUEST['failed_transaction_id'])) {
			$failedTransaction = UnzerCw_Entity_Transaction::loadById($_REQUEST['failed_transaction_id']);

			$customerId = $failedTransaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getCustomerId();
			if (!isset($_SESSION['Kunde']) || $customerId === null || $_SESSION['Kunde']->kKunde !== $customerId) {
				$failedTransaction = null;
			}
		}

		if ($failedTransaction !== null) {
			$errorMessage = current($failedTransaction->getTransactionObject()->getErrorMessages());
			if ($errorMessage instanceof Customweb_Payment_Authorization_IErrorMessage) {
				$errorMessage =  Customweb_Util_Html::convertSpecialCharacterToEntities($errorMessage->getUserMessage());
			}
			if (empty($errorMessage)) {
				$errorMessage = UnzerCw_Language::_("Unknown error.");
			}

			if ($failedTransaction->getOrderInternalId() !== null && $failedTransaction->getOrderInternalId() > 0) {
				$order = new Bestellung($failedTransaction->getOrderInternalId());
			}

			$paymentMethod = $failedTransaction->getPaymentMethod();
		}

		$this->addBreadcrumbItem(UnzerCw_Language::_('Checkout'), UnzerCw_Util::getStoreBaseUrl() . '/bestellvorgang.php');
		$this->addCssFile('checkout.css');
		$this->addCssFile('form.css');
		$this->addJavaScriptFile('checkout.js');
		$this->assign('error_message', $errorMessage);

		if (isset($_SESSION['unzercw_last_order_id'])) {
			$order = new Bestellung($_SESSION['unzercw_last_order_id']);
		}

		if ($order === null) {
			require_once PFAD_ROOT . PFAD_INCLUDES . "bestellabschluss_inc.php";
			require_once PFAD_ROOT . PFAD_INCLUDES . "bestellvorgang_inc.php";
			$order = fakeBestellung();
		}

		if ($paymentMethod === null) {
			$paymentMethod = UnzerCw_PaymentMethod::create($_SESSION["Zahlungsart"]->cModulId);
		}

		$formAdapter = $paymentMethod->createPaymentFormAdapter($order, $failedTransaction);
		$this->assign('checkout_form', utf8_decode($formAdapter->getCheckoutPageHtml()));
		$this->assign('method_name', $paymentMethod->getPaymentMethodDisplayName());

		$this->display('payment-pane');
	}

	public function failedAction() {
		$failedTransaction = $this->getTransactionFromRequest();
		$url = $this->getUrl(array('failed_transaction_id' => $failedTransaction->getTransactionId()), 'payment');
		
		$errorPage = 'overview';
		if ($failedTransaction->getPaymentMethod()->existsPaymentMethodConfigurationValue('error_display_page')) {
			$errorPage = $failedTransaction->getPaymentMethod()->getPaymentMethodConfigurationValue('error_display_page');
		}
		if (strtolower($errorPage) != 'separte') {
			$url = trim(UnzerCw_Util::getStoreBaseUrl(), '/ ') . '/bestellvorgang.php?unzercw_failed_transaction_id=' . $failedTransaction->getTransactionId();
		}

		header('Location: ' . $url);
		die();
	}

	public function successAction() {

		$this->getTransactionFromRequest();

		$start = time();
		$maxExecutionTime = Customweb_Util_System::getMaxExecutionTime() - 5;

		// We limit the timeout in case the server has set a very high timeout.
		if ($maxExecutionTime > 30) {
			$maxExecutionTime = 30;
		}
		
		// We have to close the session here otherwise the transaction may not be updated by the notification
		// callback.
		session_write_close();

		// Wait as long as the notification is done in the background
		while (true) {

			$transaction = $this->getTransactionFromRequest(false);

			$transactionObject = $transaction->getTransactionObject();

			$url = null;
			if ($transactionObject->isAuthorizationFailed()) {
				$url = UnzerCw_Util::getUrl('process', 'failed', array('cw_transaction_id' => $_REQUEST['cw_transaction_id']), true);
			}
			else if ($transactionObject->isAuthorized()) {
				$url = $transaction->getJTLSuccessUrl();
				unset($_SESSION['unzercw_last_order_id']);
			}

			if (time() - $start > $maxExecutionTime) {
				// We redirect to timeout action so user is not logged out with SameSite Cookie issues
				$url = UnzerCw_Util::getUrl('process', 'timeout', array('cw_transaction_id' => $_REQUEST['cw_transaction_id']), true);
			}

			if ($url !== null) {
				header('Location: ' . $url);
				die();
			}
			else {
				// Wait 2 seconds for the next try.
				sleep(2);
			}
		}
	}
	
	public function timeoutAction() {
		// We run into a timeout. Write a log entry and show the customer a description of the actual situation.
		$message = "The transaction takes too long for processing. May be the callback was not successful in the background. Transaction id: " . $_REQUEST['cw_transaction_id'];
		UnzerCw_Log::add($message);
		$transaction = $this->getTransactionFromRequest();
		$this->assign("method_name", $transaction->getTransactionObject()->getPaymentMethod()->getPaymentMethodDisplayName());
		$this->assign("message", UnzerCw_Language::_(
				"Your payment seems to be accepted. However the payment could not be processed correctly with in the given time.
					Please contact us to figure out what happends with your order. As reference use please the transaction id '!transactionId'.", array('!transactionId' => $_REQUEST['cw_transaction_id'])));
		$this->display('timeout');
		die();
	}

	public function redirectAction() {

		$transaction = $this->getTransactionFromRequest();

		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adpater = $adapterFactory->getAuthorizationAdapterByName($transaction->getTransactionObject()->getAuthorizationMethod());

		if (!($adpater instanceof Customweb_Payment_Authorization_PaymentPage_IAdapter)) {
			throw new Exception("Redirection is only supported for payment page authorization.");
		}

		$headerRedirection = $adpater->isHeaderRedirectionSupported($transaction->getTransactionObject(), $_REQUEST);

		if ($headerRedirection) {
			$url = $adpater->getRedirectionUrl($transaction->getTransactionObject(), $_REQUEST);
			UnzerCw_Util::getEntityManager()->persist($transaction);
			header('Location: ' . $url);
			die();
		}
		else {
			$this->addBreadcrumbItem(UnzerCw_Language::_('Checkout'), UnzerCw_Util::getStoreBaseUrl() . '/bestellvorgang.php');

			$this->assign('method_name', $transaction->getTransactionObject()->getPaymentMethod()->getPaymentMethodDisplayName());
			$this->assign('form_target_url', $adpater->getFormActionUrl($transaction->getTransactionObject(), $_REQUEST));
			$this->assign('hidden_fields', Customweb_Util_Html::buildHiddenInputFields($adpater->getParameters($transaction->getTransactionObject(), $_REQUEST)));
			$this->assign('button_continue', UnzerCw_Language::_("Continue"));
			UnzerCw_Util::getEntityManager()->persist($transaction);
			$this->display('redirect');
		}
	}

	/**
	 * @throws Exception
	 * @return UnzerCw_Entity_Transaction
	 */
	private function getTransactionFromRequest($cache = true) {
		if (!isset($_REQUEST['cw_transaction_id'])) {
			throw new Exception("No transaction id given.");
		}

		$transaction = UnzerCw_Entity_Transaction::loadById($_REQUEST['cw_transaction_id'], $cache);

		if ($transaction === null) {
			throw new Exception("No transaction found for the ID: " . $_REQUEST['cw_transaction_id']);
		}

		return $transaction;
	}

}




