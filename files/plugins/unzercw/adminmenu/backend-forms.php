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

require_once(PFAD_ROOT . PFAD_INCLUDES . "tools.Global.php");
require_once dirname(dirname(__FILE__)) . '/init.php';
require_once 'UnzerCw/Dispatcher.php';
require_once 'UnzerCw/Log.php';


if (false) {
	$reason = Customweb_Licensing_UnzerCw_License::getValidationErrorMessage();
	if ($reason === null) {
		$reason = 'Unknown error.';
	}
	$token = Customweb_Licensing_UnzerCw_License::getCurrentToken();
	echo '<div class="box_error">Es gibt ein Problem mit Ihrer Lizenz. Bitte nehmen Sie mit dem Sellxed Support Kontakt auf (www.sellxed.com/support).  Reason: ' . $reason. ' Current Token: '.$token.'</div>';
}

try {
	$dispatcher = new UnzerCw_Dispatcher();
	$dispatcher->dispatch('form');
}
catch(Exception $e) {
	UnzerCw_Log::add($e->getMessage() . "\n\n" + $e->getTraceAsString());

	echo '<pre>';
	echo $e->getMessage();
	echo "\n\n";
	echo $e->getTraceAsString();
}
	

?>
<br />
<div class="category">Informationen</div>
<table class="list">

	<tr>
		<td>Unzer Plugin Version:</td><td>1.0.58</td>
	</tr>
	<tr>
		<td>Unzer Plugin Release Datum:</td><td>Wed, 10 Mar 2021 12:27:30 +0100</td>
	</tr>
	<tr>
		<td>Cron URL:</td><td><?php echo UnzerCw_Util::getUrl('process', 'cron'); ?></td>
	</tr>
</table>
