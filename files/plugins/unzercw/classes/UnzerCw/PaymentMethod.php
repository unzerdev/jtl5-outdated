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

use JTL\Plugin\Payment\Method;

require_once dirname(dirname(dirname(__FILE__))) . '/init.php';

require_once 'Customweb/Payment/Entity/AbstractPaymentCustomerContext.php';
require_once 'Customweb/Payment/Authorization/IPaymentMethod.php';
require_once 'Customweb/Payment/Authorization/IAdapter.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Entity/PaymentCustomerContext.php';
require_once 'UnzerCw/Log.php';
require_once 'UnzerCw/TransactionContext.php';
require_once 'UnzerCw/PaymentMethodWrapper.php';
require_once 'UnzerCw/SessionOrderContext.php';
require_once 'UnzerCw/SettingApi.php';
require_once 'UnzerCw/OrderContext.php';


class UnzerCw_PaymentMethod extends Method implements Customweb_Payment_Authorization_IPaymentMethod{
	private $settingApi = null;
	private $paymentMethodName = null;
	private $paymentMethodMachineName = null;
	
	/**
	 * We override the constructor to prevent error messages, because
	 * the regex for moduleAbbr is not setup correct.
	 *
	 * @param string $moduleID
	 * @param boolean $nAgainCheckout
	 */
	public function __construct($moduleID, $nAgainCheckout = 0) {
		$this->moduleID = $moduleID;
		$this->moduleAbbr = null;
		$this->loadSettings();
		$this->init($nAgainCheckout);
	}
	
	protected function preparePaymentProcessInner($order) {
		
		if (!empty($order->kBestellung)) {
			$_SESSION['unzercw_last_order_id'] = $order->kBestellung;
		}
		
		$adapter = $this->createPaymentFormAdapter($order);
		if ($adapter->isHeaderRedirectionSupported()) {
			$url = $adapter->getRedirectionUrl();
			header('Location: ' . $url);
			die();
		}
		else {
			
			$output = '';
			$output .= UnzerCw_Util::buildResourceHtml(array('checkout.js'), 'js');
			$output .= UnzerCw_Util::buildResourceHtml(array('checkout.css'), 'css');
			$output .= UnzerCw_Util::buildResourceHtml(array('form.css'), 'css');
			$output .= utf8_decode($adapter->getCheckoutPageHtml());
			
			$GLOBALS['smarty']->assign('form_html', $output);
		}
	}
	
	/**
	 * @param Bestellung $order
	 * @param UnzerCw_Entity_Transaction $failedTransaction
	 * @return UnzerCw_Adapter_IAdapter
	 */
	public function createPaymentFormAdapter($order, $failedTransaction = null) {
		
		$orderContext = new UnzerCw_OrderContext(new UnzerCw_PaymentMethodWrapper($this), $order);
		
		$paymentAdapter = UnzerCw_Util::getAuthorizationAdapterFactory()->getAuthorizationAdapterByContext($orderContext);
		$adapter = UnzerCw_Util::getJtlAdapterByPaymentAdapter($paymentAdapter);
		$adapter->prepareCheckout($this, $orderContext, $failedTransaction);
		
		return $adapter;
	}
	
	
	protected function isValidInternInner($args_arr = array()) {
		
		$orderContext = new UnzerCw_SessionOrderContext($this);
		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adapter = $adapterFactory->getAuthorizationAdapterByContext($orderContext);
		
		if (!($adapter instanceof Customweb_Payment_Authorization_IAdapter)) {
			throw new Exception("The adapter must implement Customweb_Payment_Authorization_IAdapter.");
		}
		$paymentContext = UnzerCw_Entity_PaymentCustomerContext::getPaymentCustomerContext($orderContext->getCustomerId());
		try {
			$adapter->preValidate($orderContext, $paymentContext);
			if ($paymentContext instanceof Customweb_Payment_Entity_AbstractPaymentCustomerContext) {
				UnzerCw_Util::getEntityManager()->persist($paymentContext);
			}
		}
		catch(Exception $e) {
			if ($paymentContext instanceof Customweb_Payment_Entity_AbstractPaymentCustomerContext) {
				UnzerCw_Util::getEntityManager()->persist($paymentContext);
			}
			UnzerCw_Log::add("Validation failed with message: " . $e->getMessage());
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * This method authorized the given transaction.
	 */
	public function authorizeTransaction(UnzerCw_Entity_Transaction $transaction) {
		
	}
	
	
	/**
	 * @param array $args
	 * @return UnzerCw_Entity_Transaction
	 */
	private function getTransaction(array $args) {
		
		if (!isset($args['cw_transaction_id'])) {
			$this->handleError("No transaction id given.");
		}
		
		$transaction = UnzerCw_Entity_Transaction::loadById($args['cw_transaction_id']);
		
		if ($transaction === null) {
			$this->handleError("No transaction found for the ID: " . $args['cw_transaction_id']);
		}
		
		return $transaction;
	}
	
	private function handleError($message) {
		UnzerCw_Log::add($message);
		die($message);
	}
	
	
	/**
	 * @param string $machineName
	 * @return UnzerCw_PaymentMethod
	 */
	public static function getInstanceByPaymentMethodName($machineName) {
		$moduleId = UnzerCw_Util::getPaymentMethodModuleId($machineName);
		$instance = self::create($moduleId);
		
		if ($instance === null) {
			throw new Exception("Could not instanciate payment method with machine name '" . $machineName . "' and module id '" . $moduleId . "'.");
		}
		
		return $instance;
	}
	
	public function getPaymentMethodName() {
		if ($this->paymentMethodMachineName === null) {
			$prefix = UnzerCw_Util::getPaymentMethodPrefix();
			$pos = strpos($this->moduleID, $prefix) + strlen($prefix);
			$externalId = substr($this->moduleID, $pos);
			$this->paymentMethodMachineName = UnzerCw_Util::mapExternalIdToMachineName($externalId);
			
		}
		return $this->paymentMethodMachineName;
	}
	
	public function getPaymentMethodDisplayName() {
		if ($this->paymentMethodName === null) {
			$db = UnzerCw_Util::getDriver();
			$statement = $db->query("SELECT l.cName AS name FROM tzahlungsartsprache AS l, tzahlungsart AS p WHERE p.cModulId = >moduleId AND p.kZahlungsart = l.kZahlungsart AND l.cISOSprache = >language");
			$statement->setParameter('>moduleId', $this->moduleID)->setParameter('>language', UnzerCw_Util::getCurrentActiveLanguageCode());
			
			if (($row = $statement->fetch()) === false) {
				throw new Exception("Could not query the database for the payment method name.");
			}
			if (!isset($row['name'])) {
				throw new Exception("Could not find name in result set.");
			}
			$this->paymentMethodName = $row['name'];
		}
		
		return $this->paymentMethodName;
	}
	
	public function getPaymentMethodConfigurationValue($key, $languageCode = null) {
		return $this->getSettingApi()->getSettingValue($key, $languageCode);
	}
	
	public function existsPaymentMethodConfigurationValue($key, $languageCode = null) {
		return $this->getSettingApi()->isSettingPresent($key);
	}
	
	/**
	 * @return UnzerCw_SettingApi
	 */
	private function getSettingApi() {
		if ($this->settingApi === null) {
			$this->settingApi = new UnzerCw_SettingApi($this->getPaymentMethodName());
		}
		
		return $this->settingApi;
	}
	
	
	/**
	 * @return UnzerCw_Entity_Transaction
	 */
	public function newTransaction(UnzerCw_OrderContext $orderContext, $aliasTransactionId = null, $failedTransactionObject = null) {
		$transaction = new UnzerCw_Entity_Transaction();
		
		$transaction->setOrderInternalId($orderContext->getOrderInternalId())->setCustomerId($orderContext->getCustomerId())->setOrderNumber($orderContext->getOrderNumber());
		UnzerCw_Util::getEntityManager()->persist($transaction);
		
		$transactionContext = new UnzerCw_TransactionContext($transaction, $orderContext, $aliasTransactionId);
		
		$adapterFactory = UnzerCw_Util::getAuthorizationAdapterFactory();
		$adapter = $adapterFactory->getAuthorizationAdapterByContext($orderContext);
		if (!($adapter instanceof Customweb_Payment_Authorization_IAdapter)) {
			throw new Exception("The adapter has to implement Customweb_Payment_Authorization_IAdapter.");
		}
		$transactionObject = $adapter->createTransaction($transactionContext, $failedTransactionObject);
		unset($_SESSION['unzercw_checkout_id'][$this->getPaymentMethodName()]);
		
		$transaction->setTransactionObject($transactionObject);
		$transaction->setSession($_SESSION);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		
		return $transaction;
	}
	
	public function isValidIntern(array $args_arr = array()): bool {
		return $this->isValidInternInner($args_arr);
	}
	
	public function preparePaymentProcess(Bestellung $order): void {
		$this->preparePaymentProcessInner($order);
	}
}