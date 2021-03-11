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

require_once 'UnzerCw/AbstractOrderContext.php';


class UnzerCw_SessionOrderContext extends UnzerCw_AbstractOrderContext {

	public function __construct(Customweb_Payment_Authorization_IPaymentMethod $paymentMethod) {
		$invoiceItems = $this->getInvoiceItemsFromSession();
		
		$shippingName = $_SESSION['Versandart']->angezeigterName;
		if (is_array($shippingName)) {
			if (isset($shippingName[$_SESSION['cISOSprache']])) {
				$shippingName = $shippingName[$_SESSION['cISOSprache']];
			}
			else {
				$shippingName = current($shippingName);
			}
		}
		
		$orderTotal = $_SESSION['Warenkorb']->gibGesamtsummeWaren(true);
		parent::__construct(
			$paymentMethod, 
			$invoiceItems, 
			$_SESSION['Lieferadresse'], 
			$_SESSION['Kunde'],
			$shippingName,
			$orderTotal*$_SESSION['Waehrung']->fFaktor,
			$_SESSION['Waehrung']->cISO,
			$this->getSessionLanguageCode()
		);
	}
	
	protected function getInvoiceItemsFromSession() {
		return UnzerCw_Util::convertJTLItems($_SESSION['Warenkorb']->PositionenArr, $_SESSION['Waehrung']->cISO, $_SESSION['Warenkorb']->gibGesamtsummeWaren(true)*$_SESSION['Waehrung']->fFaktor);
	}
	
	
}
 