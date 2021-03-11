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

require_once 'Customweb/Core/Language.php';
require_once 'Customweb/Payment/IConfigurationAdapter.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/SettingApi.php';


/**
 * 
 * 
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_Configuration implements Customweb_Payment_IConfigurationAdapter{
	
	/**
	 * @var UnzerCw_SettingApi
	 */
	private static $config = null;
	
	/**
	 * @return UnzerCw_SettingApi
	 */
	private static function getSetting() {
		if (self::$config === null) {
			self::$config = new UnzerCw_SettingApi();
		}
		return self::$config;
	}
	
	public function getConfigurationValue($key, $languageCode = null) {
		return self::getSetting()->getSettingValue($key, $languageCode);
	}
	
	public function existsConfiguration($key, $language = null) {
		return self::getSetting()->isSettingPresent($key);
	}
	
	public function getLanguages($currentStore = false) {
		$rs = UnzerCw_Util::getDriver()->query('SELECT cNameEnglisch FROM tsprache');
		$languages = array();
		while (($row = $rs->fetch()) !== false) {
			$languages[] = new Customweb_Core_Language($row['cNameEnglisch']);
		}
		return $languages;
	}
	
	public function getStoreHierarchy() {
		return null;
	}
	
	public function useDefaultValue(Customweb_Form_IElement $element, array $formData) {
		return false;
	}
	
	public function getOrderStatus() {
		return array();
	}
	
	public static function getLoggingLevel(){
		return self::getSetting()->getSettingValue('log_level');
	}
	
}