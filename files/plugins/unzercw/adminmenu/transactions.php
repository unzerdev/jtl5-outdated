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
require_once 'UnzerCw/Util.php';


function unzercw_add_text_field_js_fix() {
	echo UnzerCw_Util::buildResourceHtml(array('handle-text-fields.js'), 'js');
}
unzercw_add_text_field_js_fix();


$GLOBALS['params'] = $params;
try {
	$dispatcher = new UnzerCw_Dispatcher();
	$dispatcher->dispatch('transaction');
}
catch(Exception $e) {
	UnzerCw_Log::add($e->getMessage() . "\n\n" + $e->getTraceAsString());

	echo '<pre>';
	echo $e->getMessage();
	echo "\n\n";
	echo $e->getTraceAsString();
}

?>
