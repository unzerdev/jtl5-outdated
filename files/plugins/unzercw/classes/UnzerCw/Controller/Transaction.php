<?php
/**
 *  * You are allowed to use this API in your web application.
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

require_once 'Customweb/Grid/Column.php';
require_once 'Customweb/Payment/Authorization/DefaultInvoiceItem.php';
require_once 'Customweb/Payment/BackendOperation/Adapter/Service/ICapture.php';
require_once 'Customweb/Payment/BackendOperation/Adapter/Service/ICancel.php';
require_once 'Customweb/Payment/Update/IAdapter.php';
require_once 'Customweb/Payment/Authorization/IInvoiceItem.php';
require_once 'Customweb/Grid/DataAdapter/DriverAdapter.php';
require_once 'Customweb/Payment/BackendOperation/Adapter/Service/IRefund.php';
require_once 'Customweb/Grid/Loader.php';

require_once 'UnzerCw/Grid/TransactionActionColumn.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Installer.php';
require_once 'UnzerCw/Grid/Renderer.php';
require_once 'UnzerCw/Controller/AbstractBackend.php';
require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Grid/TransactionStatusColumn.php';

require_once 'Customweb/Core/Util/Xml.php';

$memoryString = ini_get("memory_limit");
$memoryString = trim($memoryString);
$unit = strtolower(substr($memoryString, -1));
$value = substr($memoryString, 0, -1);
switch ($unit) {
	case 'g':
		$value *= 1024;
	case 'm':
		$value *= 1024;
	case 'k':
		$value *= 1024;
}
if ($value < 134217728) {
	ini_set("memory_limit", "128M");
}

class UnzerCw_Controller_Transaction extends UnzerCw_Controller_AbstractBackend {

	public function __construct(){
		parent::__construct();
		$this->assign('dateFormat', 'Y-m-d H:i:s');
	}

	public function indexAction(){
		$this->listAction();
	}

	public function listAction(){
		UnzerCw_Installer::update();
		
		$adapter = new Customweb_Grid_DataAdapter_DriverAdapter(UnzerCw_Entity_Transaction::getGridQuery(), 
				UnzerCw_Util::getDriver());
		
		$loader = new Customweb_Grid_Loader();
		$loader->setDataAdapter($adapter);
		$loader->setRequestData($_GET);
		$loader->addColumn(new Customweb_Grid_Column('transactionId', '#'))->addColumn(
				new Customweb_Grid_Column('transactionExternalId', 'Transaktionsnummer'))->addColumn(
				new Customweb_Grid_Column('orderInternalId', 'Bestellid'))->addColumn(new Customweb_Grid_Column('orderId', 'Bestellnummer'))->addColumn(
				new Customweb_Grid_Column('paymentMachineName', 'Zahlmethode'))->addColumn(
				new UnzerCw_Grid_TransactionStatusColumn('status', 'Authorization Status'))->addColumn(
				new Customweb_Grid_Column('createdOn', 'Erstellungsdatum', 'DESC'))->addColumn(
				new UnzerCw_Grid_TransactionActionColumn('actions'));
		
		$renderer = new UnzerCw_Grid_Renderer($loader, $this->getUrl());
		$renderer->setGridId('transaction-grid');
		
		$this->assign('grid', $renderer->render());
		$this->display('transaction/list');
	}

	public function viewAction(){
		$transaction = $this->getTransactionFromRequest();
		
		$relatedTransactions = array();
		if ($transaction->getOrderInternalId() > 0) {
			$relatedTransactions = UnzerCw_Entity_Transaction::getTransactionsByOrderInternalId($transaction->getOrderInternalId());
		}
		
		$this->assign('transaction', $transaction);
		$this->assign('related_transactions', $relatedTransactions);
		
		$this->assign('refresh_status_url', $this->getUrl(array(
			'transaction_id' => $transaction->getTransactionId() 
		), 'refreshOrderStatus'));
		
		if (is_object($transaction->getTransactionObject()) && !$transaction->isAcceptUncertain() &&
				 ($transaction->getTransactionObject()->isAuthorizationUncertain() || !$transaction->getTransactionObject()->isPaid())) {
			$buttonName = UnzerCw_Language::_('Accept Uncertain');
			if ($transaction->getTransactionObject()->getPaymentMethod()->existsPaymentMethodConfigurationValue('payment_receipt') &&
					 $transaction->getTransactionObject()->getPaymentMethod()->getPaymentMethodConfigurationValue('payment_receipt') == 'capturing') {
				if ($transaction->getTransactionObject()->isCaptured()) {
					$buttonName = UnzerCw_Language::_('Accept Uncertain');
				}
				else {
					$buttonName = UnzerCw_Language::_('Skip Uncertain');
				}
			}
			else {
				if (!$transaction->getTransactionObject()->isPaid() && $transaction->getTransactionObject()->isAuthorizationUncertain()) {
					$buttonName = UnzerCw_Language::_('Accept Uncertain & Mark Paid');
				}
				elseif (!$transaction->getTransactionObject()->isPaid()) {
					$buttonName = UnzerCw_Language::_('Mark Paid');
				}
				else {
					$buttonName = UnzerCw_Language::_('Accept Uncertain');
				}
			}
			
			$this->assign('manual_accept_uncertain_url', 
					$this->getUrl(array(
						'transaction_id' => $transaction->getTransactionId() 
					), 'manualAcceptUncertain'));
			$this->assign('manual_accept_uncertain_button_name', $buttonName);
		}
		
		
		if (is_object($transaction->getTransactionObject()) && $transaction->getTransactionObject()->isCapturePossible()) {
			$this->assign('capture_url', $this->getUrl(array(
				'transaction_id' => $transaction->getTransactionId() 
			), 'capture'));
		}
		
		
		if (is_object($transaction->getTransactionObject()) && $transaction->getTransactionObject()->isRefundPossible()) {
			$this->assign('refund_url', $this->getUrl(array(
				'transaction_id' => $transaction->getTransactionId() 
			), 'refund'));
		}
		
		
		if (is_object($transaction->getTransactionObject()) && $transaction->getTransactionObject()->isCancelPossible()) {
			$this->assign('cancel_url', $this->getUrl(array(
				'transaction_id' => $transaction->getTransactionId() 
			), 'cancel'));
		}
		
		
		

		$this->assign('back', $this->getUrl(array(), 'list'));
		
		$this->display('transaction/view');
	}
	
	
	public function captureAction(){
		$transaction = $this->getTransactionFromRequest();
		
		if (isset($_POST['quantity'])) {
			
			$captureLineItems = array();
			$lineItems = $transaction->getTransactionObject()->getUncapturedLineItems();
			foreach ($_POST['quantity'] as $index => $quantity) {
				if (isset($_POST['price_including'][$index]) && floatval($_POST['price_including'][$index]) != 0) {
					$originalItem = $lineItems[$index];
					if ($originalItem->getType() == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT) {
						$priceModifier = -1;
					}
					else {
						$priceModifier = 1;
					}
					$captureLineItems[$index] = new Customweb_Payment_Authorization_DefaultInvoiceItem($originalItem->getSku(), 
							$originalItem->getName(), $originalItem->getTaxRate(), $priceModifier * (floatval($_POST['price_including'][$index])), 
							$quantity, $originalItem->getType());
				}
			}
			if (count($captureLineItems) > 0) {
				$adapter = UnzerCw_Util::getContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICapture');
				if (!($adapter instanceof Customweb_Payment_BackendOperation_Adapter_Service_ICapture)) {
					throw new Exception("No adapter with interface 'Customweb_Payment_BackendOperation_Adapter_Service_ICapture' provided.");
				}
				
				$close = false;
				if (isset($_POST['close']) && $_POST['close'] == 'on') {
					$close = true;
				}
				try {
					$adapter->partialCapture($transaction->getTransactionObject(), $captureLineItems, $close);
					UnzerCw_Util::getEntityManager()->persist($transaction);
					$this->setSuccessMessage(UnzerCw_Language::_('Capture was successful.'));
					$this->viewAction();
					return;
				}
				catch (Exception $e) {
					$this->setErrorMessage($e->getMessage());
					UnzerCw_Util::getEntityManager()->persist($transaction);
				}
			}
		}
		
		$this->assign('transaction', $transaction);
		$this->assign('back', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'view'));
		$this->assign('captureConfirmUrl', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'refund'));
		
		$this->display('transaction/capture');
	}

	public function viewCaptureAction(){
		$transaction = $this->getTransactionFromRequest();
		
		$capture = null;
		foreach ($transaction->getTransactionObject()->getCaptures() as $item) {
			if ($item->getCaptureId() == $_GET['capture_id']) {
				$capture = $item;
				break;
			}
		}
		
		if ($capture == null) {
			die('No capture found with the given id.');
		}
		
		$this->assign('transaction', $transaction);
		$this->assign('back', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'view'));
		$this->assign('capture', $capture);
		
		$this->display('transaction/view-capture');
	}
	
	

	
	public function refundAction(){
		$transaction = $this->getTransactionFromRequest();
		
		if (isset($_POST['quantity'])) {
			
			$refundLineItems = array();
			$lineItems = $transaction->getTransactionObject()->getNonRefundedLineItems();
			foreach ($_POST['quantity'] as $index => $quantity) {
				if (isset($_POST['price_including'][$index]) && floatval($_POST['price_including'][$index]) != 0) {
					$originalItem = $lineItems[$index];
					if ($originalItem->getType() == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT) {
						$priceModifier = -1;
					}
					else {
						$priceModifier = 1;
					}
					$refundLineItems[$index] = new Customweb_Payment_Authorization_DefaultInvoiceItem($originalItem->getSku(), 
							$originalItem->getName(), $originalItem->getTaxRate(), $priceModifier * (floatval($_POST['price_including'][$index])), 
							$quantity, $originalItem->getType());
				}
			}
			if (count($refundLineItems) > 0) {
				$adapter = UnzerCw_Util::getContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_IRefund');
				if (!($adapter instanceof Customweb_Payment_BackendOperation_Adapter_Service_IRefund)) {
					throw new Exception("No adapter with interface 'Customweb_Payment_BackendOperation_Adapter_Service_IRefund' provided.");
				}
				
				$close = false;
				if (isset($_POST['close']) && $_POST['close'] == 'on') {
					$close = true;
				}
				try {
					$adapter->partialRefund($transaction->getTransactionObject(), $refundLineItems, $close);
					UnzerCw_Util::getEntityManager()->persist($transaction);
					$this->setSuccessMessage(UnzerCw_Language::_('Refund was successful.'));
					$this->viewAction();
					return;
				}
				catch (Exception $e) {
					$this->setErrorMessage($e->getMessage());
					UnzerCw_Util::getEntityManager()->persist($transaction);
				}
			}
		}
		
		$this->assign('transaction', $transaction);
		$this->assign('back', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'view'));
		$this->assign('refundConfirmUrl', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'refund'));
		
		$this->display('transaction/refund');
	}

	public function viewRefundAction(){
		$transaction = $this->getTransactionFromRequest();
		
		$refund = null;
		foreach ($transaction->getTransactionObject()->getRefunds() as $item) {
			if ($item->getRefundId() == $_GET['refund_id']) {
				$refund = $item;
				break;
			}
		}
		
		if ($refund == null) {
			die('No refund found with the given id.');
		}
		
		$this->assign('transaction', $transaction);
		$this->assign('back', $this->getUrl(array(
			'transaction_id' => $_REQUEST['transaction_id'] 
		), 'view'));
		$this->assign('refund', $refund);
		
		$this->display('transaction/view-refund');
	}
	
	

	
	public function cancelAction(){
		$transaction = $this->getTransactionFromRequest();
		$adapter = UnzerCw_Util::getContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICancel');
		if (!($adapter instanceof Customweb_Payment_BackendOperation_Adapter_Service_ICancel)) {
			throw new Exception("No adapter with interface 'Customweb_Payment_BackendOperation_Adapter_Service_ICancel' provided.");
		}
		
		try {
			$adapter->cancel($transaction->getTransactionObject());
			UnzerCw_Util::getEntityManager()->persist($transaction);
			$this->setSuccessMessage(UnzerCw_Language::_('Cancel was successful.'));
		}
		catch (Exception $e) {
			$this->setErrorMessage($e->getMessage());
			UnzerCw_Util::getEntityManager()->persist($transaction);
		}
		$this->viewAction();
	}
	
	

	
	public function refreshOrderStatusAction(){
		$transaction = $this->getTransactionFromRequest();
		UnzerCw_Util::getEntityManager()->persist($transaction);
		$this->viewAction();
	}

	public function manualAcceptUncertainAction(){
		$transaction = $this->getTransactionFromRequest();
		$transaction->setAcceptUncertain(true);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		$this->viewAction();
	}

	/**
	 *
	 * @throws Exception
	 * @return UnzerCw_Entity_Transaction
	 */
	private function getTransactionFromRequest(){
		if (!isset($_REQUEST['transaction_id'])) {
			throw new Exception("No transaction id given.");
		}
		
		$transaction = UnzerCw_Entity_Transaction::loadById($_REQUEST['transaction_id']);
		
		if ($transaction === null) {
			throw new Exception("No transaction found for the ID: " . $_REQUEST['transaction_id']);
		}
		
		return $transaction;
	}
}