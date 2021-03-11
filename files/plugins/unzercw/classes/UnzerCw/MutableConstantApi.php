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

/**
 * This class handles the storage of constants, which may changed over time, but not 
 * on a user base or transaction base.
 * 
 * @author Thomas Hunziker
 *
 */

class UnzerCw_MutableConstantApi {
	
	private function __construct(){
		
	}
	
	public static function getConstant($name) {
		$db = UnzerCw_Util::getDriver();
		$tableName = self::getTableName();
		try {
			$statement = $db->query("SELECT constant_value FROM $tableName WHERE constant_name = >name")->setParameters(array('>name' => $name));
			$row = $statement->fetch();
			if (isset($row['constant_value'])) {
				return $row['constant_value'];
			}
			else {
				return null;
			}
		}
		catch(Exception $e) {
			return null;
		}
	}
	
	public static function setConstant($name, $value) {
		$db = UnzerCw_Util::getDriver();
		$tableName = self::getTableName();
		$sql = 'INSERT INTO ' . $tableName . ' (constant_name, constant_value, updated_on) VALUES (>name, >value, now()) ON DUPLICATE KEY UPDATE constant_value = >value';
		$db->query($sql)->execute(array('>name' => $name, '>value' => $value));
	}
	
	protected static function getTableName() {
		return "xplugin_unzercw_constants";
	}
}
