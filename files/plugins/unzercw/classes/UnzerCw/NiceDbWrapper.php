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

require_once 'Customweb/Database/Driver/MySQL/Driver.php';
require_once 'Customweb/Database/Driver/MySQLi/Driver.php';
require_once 'Customweb/Database/Driver/PDO/Driver.php';

/**
 * This class handles the storage of constants, which may changed over time, but not 
 * on a user base or transaction base.
 * 
 * @author Thomas Hunziker
 *
 */
class UnzerCw_NiceDbWrapper extends NiceDB{
	
	/**
	 * @var stdClass
	 */
	private $object = null;
	
	/**
	 * @var Customweb_Database_IDriver
	 */
	private $driver = null;
	
	public function __construct(NiceDB $object) {
		$this->object = $object;
	}
	
	
	public function __isset($name) {
		if (isset($this->object->{$name})) {
			return true;
		}
		else {
			return false;
		}
	}
	
	protected function getProxyObject() {
		return $this->object;
	}
	
	public function __unset($name) {
		unset($this->object->{$name});
	}
	
	public function __set($name, $value) {
		$this->object->{$name} = $value;
	}
	
	public function __get($name) {
		return $this->object->{$name};
	}
	
	public function __call($method, $args) {
		return call_user_func_array(array($this->object, $method), $args);
	}
	
	public function __wakeup() {
		return $this->object->__wakeup();
	}
	
	public function __sleep() {
		return $this->object->__sleep();
	}
	
	/**
	 * @throws Exception
	 * @return Customweb_Database_IDriver
	 */
	public function getDatabaseDriver() {
		
		if ($this->driver === null) {
			if (isset($this->object->DB_Connection)) {
				$this->driver = new Customweb_Database_Driver_MySQL_Driver($this->object->DB_Connection);
			}
			else if (isset($this->object->db) && $this->object->db instanceof mysqli) {
				$this->driver = new Customweb_Database_Driver_MySQLi_Driver($this->object->db);
			}
			else if (method_exists($this->object, 'getPDO')) {
				$this->driver = new Customweb_Database_Driver_PDO_Driver($this->object->getPDO());
			}
			else {
				throw new Exception("Unknown database connection.");
			}
		}
		
		return $this->driver;
	}
	
}
 