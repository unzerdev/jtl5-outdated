<?php 

// Implementation for hook HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE
// The hook is called when the order is finished and inserted into the database. The hook is invoked before the
// e-mail to the customer is sent. The hook does only exists for JTL 4.x

// We need to update the payment information on the order. This way the information gets also into the e-mail.
// We cannot use the same hook as for 3.x because the way insertDB works on the Bestellung class is different.

$order = $args_arr['oBestellung'];

if (isset($GLOBALS['OrderPluginCwPaymentInformation']) && $order instanceof Bestellung) {
	require_once 'UnzerCw/Util.php';
	UnzerCw_Util::getDriver()->update(
		'tbestellung', 
		array('>cwPaymentInformation' => $GLOBALS['OrderPluginCwPaymentInformation']), 
		'kBestellung = ' . $order->kBestellung
	);
}
	