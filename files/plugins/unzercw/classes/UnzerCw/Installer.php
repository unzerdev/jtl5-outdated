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

require_once 'Customweb/Database/Migration/Manager.php';

require_once 'UnzerCw/Util.php';


/**
 * We can not use the default installer for the tables, because its is not possible to update changes in
 * the info.xml without removing the whole transaction information, which is not feasible.
 *
 * Hence we use this class to install the tables.
 *
 * The referenced version here has nothing to do with the version defined in the XML or the
 * version defined for the module code. This version is only used for the database!
 *
 * @author Thomas Hunziker
 *
 */
final class UnzerCw_Installer {

	private function __construct() {

	}

	public static function update() {
		$manager = new Customweb_Database_Migration_Manager(
				UnzerCw_Util::getDriver(), 
				dirname(__FILE__) . '/Migration/', 
				'xplugin_unzercw_schema_version'
		);
		$manager->migrate();
	}



}