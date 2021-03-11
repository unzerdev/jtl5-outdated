<?php
use JTL\Shop;
use JTL\Helpers\Text;
use JTL\Helpers\Template;
use JTL\Language\LanguageHelper;

require_once ("IVersionHelper.php");

/**
 * Interface for accessing functions which differ by version
 * 
 * @author sebastian
 *
 */
class UnzerCw_VersionHelper implements UnzerCw_IVersionHelper {
	private static $instance;

	private function __construct(){}

	public static function getInstance(){
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public function getPluginConfigurations($plugin) {
		$confs = array();
		$options = $plugin->getConfig()->getOptions();
		foreach($options as $option) {
			$confs[$option->valueID] = $option->value;
		}
		return $confs;
	}
	
	
	public function getPluginKey($plugin) {
		return $plugin->getID();
	}
	
	public function getTranslations($plugin) {
		return $plugin->getLocalization()->getTranslations();
	}

	public function getTranslation($text, $context){
		return LanguageHelper::getInstance()->getTranslation($text, $context);
	}

	public function getShopSettings($arr){
		return Shop::getSettings($arr);
	}

	public function writeLog($level, $message, $context){
		Shop::Container()->getLogService()->log($level, $message, [
			$context
		]);
	}

	public function getTemplateDirectory(){
		return Template::getInstance()->templateDir;
	}

	public function getDb(){
		return Shop::Container()->getDB();
	}

	public function convertISO2ISO639($text){
		return Text::convertISO2ISO639($text);
	}

	public function buildScriptTag($source){
		return "<script defer type='text/javascript' src='$source'></script>";
	}
}