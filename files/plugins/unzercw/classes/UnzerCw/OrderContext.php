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

require_once 'Customweb/Payment/Authorization/DefaultInvoiceItem.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/AbstractOrderContext.php';


class UnzerCw_OrderContext extends UnzerCw_AbstractOrderContext
{
	/**
	 * @var Bestellung
	 */
	private $order = null;
	
	private $orderKBestellung = null;
	
	private $orderCBestellNr = null;
	
	public function __construct(Customweb_Payment_Authorization_IPaymentMethod $paymentMethod, Bestellung $order) {
		
		$order->fuelleBestellung();
	
		if (isset($order->kBestellung) && $order->kBestellung > 0){
			$this->orderKBestellung = $order->kBestellung;
		}
		
		if ($this->orderKBestellung !== null && isset($order->cBestellNr) && !empty($order->cBestellNr)) {
			$this->orderCBestellNr = $order->cBestellNr;
		}
		
		$invoiceItems = empty($order->Positionen) ?
			$this->getInvoiceItemsFromSession() : 
			$this->getInvoiceItemsFromOrder($order);

		parent::__construct(
			$paymentMethod, 
			$invoiceItems, 
			$order->Lieferadresse, 
			$order->oRechnungsadresse,
			$order->cVersandartName,
			$order->fGesamtsumme*$_SESSION['Waehrung']->fFaktor,
			$order->Waehrung->cISO,
			$this->getIsoLangaugeCode($order)
		);
		
	}
	
	protected function getInvoiceItemsFromSession() {
		return UnzerCw_Util::convertJTLItems($_SESSION['Warenkorb']->PositionenArr, $_SESSION['Waehrung']->cISO, $_SESSION['Warenkorb']->gibGesamtsummeWaren(true)*$_SESSION['Waehrung']->fFaktor);
	}
	
	
	protected function getInvoiceItemsFromOrder($order) {
		return UnzerCw_Util::convertJTLItems($order->Positionen, $order->Waehrung->cISO, $order->fGesamtsumme*$_SESSION['Waehrung']->fFaktor);
	}
	
	public function getOrderInternalId() {
		if ($this->orderKBestellung !== null ) {
			return $this->orderKBestellung;
		}
		if($this->getOrder() !== null){
			if (isset($this->getOrder()->kBestellung) && $this->getOrder()->kBestellung > 0) {
				return $this->getOrder()->kBestellung;
			}
		}
		return null;
		
	}
	
	public function getOrderNumber() {
		if($this->orderCBestellNr !== null) {
			return $this->orderCBestellNr;
		}
		if($this->getOrder() !== null) {
			if ($this->getOrderInternalId() !== null && isset($this->getOrder()->cBestellNr) && !empty($this->getOrder()->cBestellNr)) {
				return $this->getOrder()->cBestellNr;
			}
		}
		return null;
	}
	
	/**
	 * This method returns the order object as it was during the checkout. No updates are reflected.
	 * 
	 * @return Bestellung
	 */
	public function getOrder() {
		return $this->order;
	}
	
	/**
	 * Returns the language in 2-letter iso code.
	 * 
	 * @param Bestellung $order
	 */
	private function getIsoLangaugeCode(Bestellung $order) {
		if (!empty($order->oRechungsadresse->kKunde)) {
			$customer = new Kunde($order->oRechungsadresse->kKunde);
			
			$oSprache = $GLOBALS['DB']->executeQuery("SELECT kSprache, cISO FROM tsprache WHERE kSprache = '" . (int)$customer->kSprache . "'", 1);
			if($oSprache->kSprache > 0) {
				return UnzerCw_VersionHelper::getInstance()->convertISO2ISO639($oSprache->cISO);
			}
		}
		return $this->getSessionLanguageCode();
	}
}





