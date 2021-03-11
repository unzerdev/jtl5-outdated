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

require_once 'Customweb/Payment/Authorization/DefaultInvoiceItem.php';
require_once 'Customweb/DependencyInjection/Container/Default.php';
require_once 'Customweb/Asset/Resolver/Composite.php';
require_once 'Customweb/Mvc/Template/Smarty/ContainerBean.php';
require_once 'Customweb/Util/Invoice.php';
require_once 'Customweb/Database/Entity/Manager.php';
require_once 'Customweb/Core/Http/ContextRequest.php';
require_once 'Customweb/Util/Currency.php';
require_once 'Customweb/Cache/Backend/Memory.php';
require_once 'Customweb/Asset/Resolver/Simple.php';
require_once 'Customweb/Core/Url.php';
require_once 'Customweb/Util/Url.php';
require_once 'Customweb/DependencyInjection/Bean/Provider/Annotation.php';
require_once 'Customweb/Payment/Authorization/IInvoiceItem.php';
require_once 'Customweb/DependencyInjection/Bean/Provider/Editable.php';
require_once 'Customweb/Storage/Backend/Database.php';
require_once 'Customweb/Core/Util/Class.php';
require_once 'Customweb/Payment/Authorization/IAdapterFactory.php';

require_once 'UnzerCw/NiceDbWrapper.php';
require_once 'UnzerCw/Adapter/IAdapter.php';
require_once 'UnzerCw/VersionHelper.php';
require_once 'UnzerCw/Util.php';


final class UnzerCw_Util {

	private static $pluginObject = null;
	private static $paymentMethodPrefixName = null;
	private static $paymentMethodNames = array(
		'creditcard' => array(
			'name' => 'Credit / Debit Card',
 			'externalId' => 'credit/debitcard',
 			'machineName' => 'CreditCard',
 		),
 		'directdebitssepa' => array(
			'name' => 'Sepa Direct Debits',
 			'externalId' => 'sepadirectdebits',
 			'machineName' => 'DirectDebitsSepa',
 		),
 		'securesepa' => array(
			'name' => 'Secure SEPA',
 			'externalId' => 'securesepa',
 			'machineName' => 'SecureSepa',
 		),
 		'openinvoice' => array(
			'name' => 'Invoice',
 			'externalId' => 'invoice',
 			'machineName' => 'OpenInvoice',
 		),
 		'secureinvoice' => array(
			'name' => 'Secure Invoice',
 			'externalId' => 'secureinvoice',
 			'machineName' => 'SecureInvoice',
 		),
 		'paypal' => array(
			'name' => 'PayPal',
 			'externalId' => 'paypal',
 			'machineName' => 'PayPal',
 		),
 		'sofortueberweisung' => array(
			'name' => 'SOFORT',
 			'externalId' => 'sofort',
 			'machineName' => 'Sofortueberweisung',
 		),
 		'giropay' => array(
			'name' => 'giropay',
 			'externalId' => 'giropay',
 			'machineName' => 'Giropay',
 		),
 		'przelewy24' => array(
			'name' => 'Przelewy24',
 			'externalId' => 'przelewy24',
 			'machineName' => 'Przelewy24',
 		),
 		'ideal' => array(
			'name' => 'iDEAL',
 			'externalId' => 'ideal',
 			'machineName' => 'IDeal',
 		),
 		'prepayment' => array(
			'name' => 'Prepayment',
 			'externalId' => 'prepayment',
 			'machineName' => 'Prepayment',
 		),
 		'eps' => array(
			'name' => 'EPS',
 			'externalId' => 'eps',
 			'machineName' => 'Eps',
 		),
 		'unzerbanktransfer' => array(
			'name' => 'Unzer Bank Transfer',
 			'externalId' => 'unzerbanktransfer',
 			'machineName' => 'UnzerBankTransfer',
 		),
 		'unzerinstallment' => array(
			'name' => 'Unzer Instalment',
 			'externalId' => 'unzerinstalment',
 			'machineName' => 'UnzerInstallment',
 		),
 		'alipay' => array(
			'name' => 'Alipay',
 			'externalId' => 'alipay',
 			'machineName' => 'Alipay',
 		),
 		'wechatpay' => array(
			'name' => 'WeChat Pay',
 			'externalId' => 'wechatpay',
 			'machineName' => 'WeChatPay',
 		),
 		'bcmc' => array(
			'name' => 'Bancontact',
 			'externalId' => 'bancontact',
 			'machineName' => 'Bcmc',
 		),
 	);
	private static $container = null;
	private static $entityManager = null;
	private static $driver = null;

	private static $resolver = null;

	private static $baseUrl = null;

	private function __construct() {

	}

	/**
	 * @return string
	 */
	public static function getCurrentActiveLanguageCode() {
		if (isset($_SESSION['cISOSprache']) && !empty($_SESSION['cISOSprache'])) {
			return $_SESSION['cISOSprache'];
		}
		else {
			return 'ger';
		}
	}

	public static function getStoreBaseUrl() {
		if (self::$baseUrl === null) {
			$url = new Customweb_Core_Url(URL_SHOP);
			$url->setHost(Customweb_Core_Http_ContextRequest::getInstance()->getHost());
			$url->setScheme(strtolower(Customweb_Core_Http_ContextRequest::getInstance()->getProtocol()));
			self::$baseUrl = $url->toString();
		}
		return self::$baseUrl;
	}

	/**
	 * @return Customweb_DependencyInjection_Container_Default
	 */
	public static function getContainer() {
		if (self::$container === null) {

			$packages = array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		);
			$packages[] = 'UnzerCw_';
			$packages[] = 'Customweb_Payment_Alias';
			$packages[] = 'Customweb_Payment_Update';
			$packages[] = 'Customweb_Payment_TransactionHandler';
			$packages[] = 'UnzerCw_EndpointAdapter';
			$packages[] = 'UnzerCw_LayoutRenderer';
			$packages[] = 'Customweb_Mvc_Template_Smarty_Renderer';
			$packages[] = 'Customweb_Payment_SettingHandler';

			$provider = new Customweb_DependencyInjection_Bean_Provider_Editable(new Customweb_DependencyInjection_Bean_Provider_Annotation(
				$packages
			));

			$storageBackend = new Customweb_Storage_Backend_Database(self::getEntityManager(), self::getDriver(), 'UnzerCw_Entity_Storage');
			$provider
				->addObject(self::getEntityManager())
				->addObject($storageBackend)
				->addObject(Customweb_Core_Http_ContextRequest::getInstance())
				->addObject(self::getAssetResolver())
				->add('databaseTransactionClassName', 'UnzerCw_Entity_Transaction')
				->addObject(self::getDriver());

			$templateRenderer = new Customweb_Mvc_Template_Smarty_ContainerBean($GLOBALS['smarty']);
			$provider->addObject($templateRenderer);

			self::$container = new Customweb_DependencyInjection_Container_Default($provider);
		}

		return self::$container;
	}

	/**
	 * @return Customweb_Database_Entity_Manager
	 */
	public static function getEntityManager() {
		if (self::$entityManager === null) {
			$cache = new Customweb_Cache_Backend_Memory();
			self::$entityManager = new Customweb_Database_Entity_Manager(self::getDriver(), $cache);
		}
		return self::$entityManager;
	}

	/**
	 * @return Customweb_Database_IDriver
	 */
	public static function getDriver() {
		if (self::$driver === null) {
			$wrapper = new UnzerCw_NiceDbWrapper(UnzerCw_VersionHelper::getInstance()->getDb());
			self::$driver = $wrapper->getDatabaseDriver();
		}
		return self::$driver;
	}

	/**
	 *
	 * @return Customweb_Payment_ITransactionHandler
	 */
	public static function getTransactionHandler(){
		$container = self::getContainer();
		return $container->getBean('Customweb_Payment_ITransactionHandler');
	}

	/**
	 * @throws Exception
	 * @return Customweb_Payment_Authorization_IAdapterFactory
	 */
	public static function getAuthorizationAdapterFactory() {
		$factory = self::getContainer()->getBean('Customweb_Payment_Authorization_IAdapterFactory');

		if (!($factory instanceof Customweb_Payment_Authorization_IAdapterFactory)) {
			throw new Exception("The payment api has to provide a class which implements 'Customweb_Payment_Authorization_IAdapterFactory' as a bean.");
		}

		return $factory;
	}

	public static function isAliasManagerActive(Customweb_Payment_Authorization_IOrderContext $orderContext) {
		$paymentMethod = $orderContext->getPaymentMethod();
		if ($paymentMethod->existsPaymentMethodConfigurationValue('alias_manager') && strtolower($paymentMethod->getPaymentMethodConfigurationValue('alias_manager')) == 'active') {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * @return Plugin
	 */
	public static function getPluginObject() {
		if (self::$pluginObject === null) {
			if (isset($GLOBALS['oPlugin']) && $GLOBALS['oPlugin'] !== null && $GLOBALS['oPlugin']->cPluginID == 'unzercw') {
				self::$pluginObject = $GLOBALS['oPlugin'];
			}
			else {
				self::$pluginObject = Plugin::getPluginById('unzercw');
			}

			if (self::$pluginObject === null || UnzerCw_VersionHelper::getInstance()->getPluginKey(self::$pluginObject) === null) {
				throw new Exception("Could not instanciate the Plugin.");
			}
		}

		return self::$pluginObject;
	}

	/**
	 * @param string $paymentMethodMachineName
	 * @return string
	 */
	public static function getPaymentMethodModuleId($paymentMethodMachineName) {
		$externalId = self::mapMachineNameToExternalId($paymentMethodMachineName);
		$plugin = self::getPluginObject();
		return 'kPlugin_' . UnzerCw_VersionHelper::getInstance()->getPluginKey($plugin) . '_' . self::getPaymentMethodPrefix() . $externalId;
	}

	public static function getPaymentMethodPseudoSettingPrefix($paymentMethodMachineName) {
		$externalId = self::mapMachineNameToExternalId($paymentMethodMachineName);
		return "kPlugin_1000_" . self::getPaymentMethodPrefix() . $externalId;
	}

	/**
	 * @return string
	 */
	public static function getPaymentMethodPrefix() {
		if (self::$paymentMethodPrefixName === null) {
			self::$paymentMethodPrefixName = preg_replace('/[^0-9a-z]+/i', '', strtolower('Unzer'));
		}
		return self::$paymentMethodPrefixName;
	}

	/**
	 * Gets the default language code in 2-letter ISO format.
	 *
	 * @return string
	 */
	public static function getDefaultLanguageCode() {
		$oSprache = UnzerCw_VersionHelper::getInstance()->getDb()->executeQuery("SELECT kSprache, cISO FROM tsprache WHERE cShopStandard = 'Y'", 1);
		if($oSprache->kSprache > 0) {
			return UnzerCw_VersionHelper::getInstance()->convertISO2ISO639($oSprache->cISO);
		}
		else {
			return 'de';
		}
	}

	public static function getUrl($controller, $action = null, array $parameters = array(), $secure = true) {
		$url = self::getEndpointFileUrl();

		$parameters['controller'] = $controller;
		if ($action !== null && $action !== 'index') {
			$parameters['action'] = $action;
		}

		return Customweb_Util_Url::appendParameters($url, $parameters);
	}

	public static function getEndpointFileUrl() {
		return trim(UnzerCw_Util::getStoreBaseUrl(), '/ ') . '/unzercw.php';
	}

	public static function getBackendUrl($controller, $action = null, array $parameters = array()) {
		$pluginId = '';

		if (isset($_REQUEST['kPlugin'])) {
			$pluginId = $_REQUEST['kPlugin'];
		}
		else {
			$pluginId = UnzerCw_VersionHelper::getInstance()->getPluginKey(self::getPluginObject());
		}

		$admin = 'admin/';
		if (defined('PFAD_ADMIN')) {
			$admin = PFAD_ADMIN;
		}

		$url = trim(UnzerCw_Util::getStoreBaseUrl(), '/ ') . '/' . $admin. 'plugin.php?kPlugin=' . $pluginId;

		$parameters['controller'] = $controller;
		if ($action !== null && $action !== 'index') {
			$parameters['action'] = $action;
		}

		return Customweb_Util_Url::appendParameters($url, $parameters);
	}

	public static function mapExternalIdToMachineName($externalId) {
		$externalIdClean = preg_replace("/[ \-_]/", '', strtolower($externalId));
		foreach (self::$paymentMethodNames as $machineName => $data) {
			if ($data['externalId'] == $externalIdClean) {
				return $data['machineName'];
			}
		}
		throw new Exception("Could not find machine name for the given payment method (" . $externalId . ").");
	}

	public static function mapMachineNameToExternalId($machineName) {
		if (isset(self::$paymentMethodNames[strtolower($machineName)])) {
			return self::$paymentMethodNames[strtolower($machineName)]['externalId'];
		}

		throw new Exception("Could not find machine name for the given payment method (" . $machineName . ").");
	}



	/**
	 * @param array $files
	 * @param string $type Either 'js' or 'css'
	 */
	public static function buildResourceHtml(array $files, $type) {

		$resolver = self::getAssetResolver();
		$output = '';
		foreach ($files as $file) {
			if (strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0 || strpos($file, '//') === 0) {
				$relativeFilePath = $file;
			} else {
				$relativeFilePath = $resolver->resolveAssetUrl($file);
			}

			if ($type == 'js') {
				$output .= UnzerCw_VersionHelper::getInstance()->buildScriptTag($relativeFilePath);
			}
			else {
				$output .= '<link rel="stylesheet" type="text/css" href="' . $relativeFilePath . '" />';
			}
		}

		return $output;
	}

	/**
	 * @param Customweb_Payment_Authorization_IAdapter $paymentAdapter
	 * @throws Exception
	 * @return UnzerCw_Adapter_IAdapter
	 */
	public static function getJtlAdapterByPaymentAdapter(Customweb_Payment_Authorization_IAdapter $paymentAdapter) {
		$reflection = new ReflectionClass($paymentAdapter);
		$adapters = self::getContainer()->getBeansByType('UnzerCw_Adapter_IAdapter');
		foreach ($adapters as $adapter) {
			if ($adapter instanceof UnzerCw_Adapter_IAdapter) {
				$inferfaceName = $adapter->getPaymentAdapterInterfaceName();
				try {
					Customweb_Core_Util_Class::loadLibraryClassByName($inferfaceName);
					if ($reflection->implementsInterface($inferfaceName)) {
						$adapter->setInterfaceAdapter($paymentAdapter);
						return $adapter;
					}
				}
				catch(Customweb_Core_Exception_ClassNotFoundException $e) {
					// Ignore
				}
			}
		}

		throw new Exception("Could not resolve to JTL adapter.");
	}

	public static function convertJTLItems(array $lines, $currencyCode, $expectedSum) {
		$items = array();
		foreach ($lines as $line) {
			/* @var $line WarenkorbPos */

			$type = self::getInvoiceLineItemTypeByPosTyp($line->nPosTyp);

			$name = $line->cName;
			if (is_array($name)) {
				if (isset($name[$_SESSION['cISOSprache']])) {
					$name = $name[$_SESSION['cISOSprache']];
				}
				else {
					$name = current($name);
				}
			}

			$totalWithoutTax = $line->fPreisEinzelNetto * $_SESSION['Waehrung']->fFaktor * $line->nAnzahl;

			$taxRate = $line->fMwSt;
			if (empty($taxRate)) {
				if ($line->Artikel instanceof Artikel && !empty($line->Artikel->fMwSt)) {
					$taxRate = $line->Artikel->fMwSt;
				}
				else {
					$taxRate = gibUst($line->kSteuerklasse);
				}
			}
			$totalWithTax = $totalWithoutTax * (1 + $taxRate / 100);

			if (isset($line->Artikel) && isset($line->Artikel->cArtNr)) {
				$sku = $line->Artikel->cArtNr;
			}
			else {
				$sku = $name;
			}

			// When the cache is activated and the configurator module is used there is a bug
			// which does not fill the sku and name properly. As such we insert here some dummy data
			// to make sure the transaction is going through even when some data is missing.
			if (empty($sku)) {
				$sku = 'not-available';
			}
			if (empty($name)) {
				$name = 'not-available';
			}

			if ($type == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT) {
				$totalWithTax = $totalWithTax * -1;
			}
			$totalWithTax = Customweb_Util_Currency::formatAmount($totalWithTax, $currencyCode);

			$name = utf8_encode($name);
			$sku = utf8_encode($sku);

			$item = new Customweb_Payment_Authorization_DefaultInvoiceItem($sku, $name, $taxRate, $totalWithTax, $line->nAnzahl, $type);
			$items[] = $item;
		}

		return Customweb_Util_Invoice::cleanupLineItems($items, $expectedSum, $currencyCode);
	}

	private static function delocalizeCurrencyAmount($amount) {
		return (float)str_replace(',', '.', preg_replace('/([^0-9.,\']+)/i', '', $amount));
	}


	/**
	 * @return Customweb_Payment_Alias_Handler
	 */
	public static function getAliasHandler() {
		return self::getContainer()->getBean('Customweb_Payment_Alias_Handler');
	}

	/**
	 * @return UnzerCw_EndpointAdapter
	 */
	public static function getEndpointAdapter() {
		return self::getContainer()->getBean('UnzerCw_EndpointAdapter');
	}

	/**
	 * @return Customweb_Payment_BackendOperation_Form_IAdapter
	 */
	public static function getBackendFormAdapter() {
		try {
			return self::getContainer()->getBean('Customweb_Payment_BackendOperation_Form_IAdapter');
		}
		catch(Customweb_DependencyInjection_Exception_BeanNotFoundException $e) {
			return null;
		}
	}

	/**
	 * @return Customweb_Asset_IResolver
	 */
	public static function getAssetResolver() {
		if (self::$resolver === null) {
			$currentTemplate = UnzerCw_VersionHelper::getInstance()->getTemplateDirectory();
			$templatePath = PFAD_ROOT . PFAD_TEMPLATES . $currentTemplate . '/';
			$templateUrl = trim(UnzerCw_Util::getStoreBaseUrl(), '/ ') . '/' . PFAD_TEMPLATES . $currentTemplate . '/';

			$pluginPath = PFAD_ROOT . '/plugins/unzercw/';
			$pluginUrl = trim(UnzerCw_Util::getStoreBaseUrl(), '/ ') . '/plugins/unzercw/';

			self::$resolver = new Customweb_Asset_Resolver_Composite(array(
				new Customweb_Asset_Resolver_Simple(
						$templatePath . 'unzercw/snippets/',
						$templateUrl . 'unzercw/snippets/',
						array('application/x-smarty')
				),
				new Customweb_Asset_Resolver_Simple(
						$templatePath . 'unzercw/',
						$templateUrl . 'unzercw/',
						array('application/x-smarty')
				),
				new Customweb_Asset_Resolver_Simple(
						$templatePath . 'unzercw/css/',
						$templateUrl . 'unzercw/css/',
						array('text/css')
				),
				new Customweb_Asset_Resolver_Simple(
						$templatePath . 'unzercw/js/',
						$templateUrl . 'unzercw/js/',
						array('application/javascript')
				),
				new Customweb_Asset_Resolver_Simple(
						$templatePath . 'unzercw/images/',
						$templateUrl . 'unzercw/images/',
						array('image/png')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'template/snippets/',
						$pluginUrl . 'template/snippets/',
						array('application/x-smarty')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'template/',
						$pluginUrl . 'template/',
						array('application/x-smarty')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'template/css/',
						$pluginUrl . 'template/css/',
						array('text/css')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'template/js/',
						$pluginUrl . 'template/js/',
						array('application/javascript')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'template/images/',
						$pluginUrl . 'template/images/',
						array('image/png')
				),
				new Customweb_Asset_Resolver_Simple(
						$pluginPath . 'assets/',
						$pluginUrl . 'assets/'
				),
			));
		}

		return self::$resolver;
	}



	private static function getInvoiceLineItemTypeByPosTyp($posTyp) {
		switch($posTyp) {
			case C_WARENKORBPOS_TYP_ARTIKEL:
				return Customweb_Payment_Authorization_IInvoiceItem::TYPE_PRODUCT;
			case C_WARENKORBPOS_TYP_VERSANDPOS:
				return Customweb_Payment_Authorization_IInvoiceItem::TYPE_SHIPPING;
			case C_WARENKORBPOS_TYP_KUPON:
			case C_WARENKORBPOS_TYP_GUTSCHEIN:
			case C_WARENKORBPOS_TYP_NEUKUNDENKUPON:
				return Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT;
			default:
				return Customweb_Payment_Authorization_IInvoiceItem::TYPE_FEE;
		}
	}


}