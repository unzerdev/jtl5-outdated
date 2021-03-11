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

require_once 'Customweb/Database/IStatement.php';
require_once 'Customweb/Grid/DataAdapter/MySqlAdapter.php';

require_once 'UnzerCw/Util.php';


class UnzerCw_Grid_Adapter extends Customweb_Grid_DataAdapter_MySqlAdapter {
	
	protected function executeQuery($query) {
		return UnzerCw_Util::getDriver()->query($query);
	}
	
	protected function fetchRow($result) {
		
		if ($result instanceof Customweb_Database_IStatement) {
			return $result->fetch();
		}
		else {
			throw new Exception("The provided must be of instance 'Customweb_Database_IStatement'.");
		}
	}
	
	protected function fetchNumberOfRows($result) {
		if ($result instanceof Customweb_Database_IStatement) {
			return $result->getRowCount();
		}
		else {
			throw new Exception("The provided must be of instance 'Customweb_Database_IStatement'.");
		}
	}
}