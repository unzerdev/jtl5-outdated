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

require_once 'Customweb/Core/Exception/CastException.php';
require_once 'Customweb/Core/Stream/Input/File.php';

require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/VersionHelper.php';


abstract class UnzerCw_Controller_Abstract {
	
	private $smarty = null;
	private $siteName = null;
	private $currentActionName = null;
	private $currentControllerName = null;
	private $breadcrumbLinks = array();
	private $javaScriptFiles = array();
	private $cssFiles = array();
	
	public function __construct() {
		
		if (!isset($GLOBALS['smarty'])) {
			throw new Exception("No 'smarty' intialized.");
		}
		$this->smarty = $GLOBALS['smarty'];
		$this->setSiteName(UnzerCw_Language::_("Payment"));
		
		$this->addBreadcrumbItem(UnzerCw_VersionHelper::getInstance()->getTranslation('startpage', 'breadcrumb'), UnzerCw_Util::getStoreBaseUrl());
	}
	
	public function setActionName($actionName) {
		$this->currentActionName = $actionName;
		return $this;
	}
	
	public function getActionName() {
		return $this->currentActionName;
	}
	
	public function setControllerName($controllerName) {
		$this->currentControllerName = $controllerName;
		return $this;
	}
	
	public function getControllerName() {
		return $this->currentControllerName;
	}
	
	protected function setSiteName($name) {
		$this->siteName = $name;
		return $this;
	}
	
	protected function addJavaScriptFile($filepath) {
		$this->javaScriptFiles[] = $filepath;
		return $this;
	}
	
	protected function getJavaScriptFiles() {
		return $this->javaScriptFiles;
	}
	
	protected function addCssFile($filepath) {
		$this->cssFiles[] = $filepath;
		return $this;
	}
	
	protected function getCssFiles() {
		return $this->cssFiles;
	}
	
	protected function getSiteName() {
		return $this->siteName;
	}
	
	protected function addBreadcrumbItem($name, $url) {
		$item = new stdClass();
		$item->name = $name;
		$item->url = $url;
		$this->breadcrumbLinks[] = $item;
		
		return $this;
	}
	
	protected function getBreadcrumbItems() {
		return $this->breadcrumbLinks;
	}
	
	protected function getUrl(array $parameters = array(), $action = null, $controller = null) {
		if ($controller === null) {
			$controller = $this->getControllerName();
		}
		if ($action === null) {
			$action = $this->getActionName();
		}
	
		return UnzerCw_Util::getUrl($controller, $action, $parameters);
	}
	
	protected function assign($key, $value) {
		$this->smarty->assign($key, $value);
	}
	
	protected function getSmarty() {
		return $this->smarty;
	}
	
	protected function display($templateFile) {
		$this->smarty->assign('Brotnavi', $this->getNavigation());
		
		$this->includeLastIncludes();
		
		$this->smarty->assign('meta_title', $this->getSiteName());
		$this->handleAdditionalResources();
		
		$asset = UnzerCw_Util::getAssetResolver()->resolveAssetStream($templateFile . '.tpl');
		if (!($asset instanceof Customweb_Core_Stream_Input_File)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Core_Stream_Input_File');
		}
		$this->smarty->display($asset->getFilePath());
	}
	
	protected function getAdditionalResourcesHtml() {
		$output = '';
		$output .= UnzerCw_Util::buildResourceHtml($this->getJavaScriptFiles(), 'js');
		$output .= UnzerCw_Util::buildResourceHtml($this->getCssFiles(), 'css');
		
		return $output;
	}
	
	protected function handleAdditionalResources() {
		
		$xajax = $this->smarty->get_template_vars('xajax_javascript');
		$xajax .= "\n" . $this->getAdditionalResourcesHtml();
		
		$this->smarty->assign('xajax_javascript', $xajax);
	}
	
	protected function includeLastIncludes() {
		extract($GLOBALS);
		require_once(PFAD_INCLUDES."letzterInclude.php");
	}
	
	protected function getNavigation() {
		$this->addBreadcrumbItem($this->getSiteName(), $this->getUrl($_GET));
		
		return $this->getBreadcrumbItems();
	}
	
}