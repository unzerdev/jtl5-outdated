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
require_once 'Customweb/Core/Http/ContextRequest.php';
require_once 'Customweb/Core/Stream/Input/File.php';
require_once 'Customweb/Core/Charset/UTF8.php';
require_once 'Customweb/Mvc/Layout/IRenderer.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/VersionHelper.php';


/**
 * @Bean 
 */
class UnzerCw_LayoutRenderer implements Customweb_Mvc_Layout_IRenderer {

	private $smarty = null;
	
	public function __construct() {
		if (!isset($GLOBALS['smarty'])) {
			throw new Exception("No 'smarty' intialized.");
		}
		$this->smarty = $GLOBALS['smarty'];
		
		$this->addBreadcrumbItem(UnzerCw_VersionHelper::getInstance()->getTranslation('startpage', 'breadcrumb'), UnzerCw_Util::getStoreBaseUrl());
		
	}
	
	public function render(Customweb_Mvc_Layout_IRenderContext $context) {
		$this->smarty->assign('Brotnavi', $this->getNavigation($context));
		
		$this->includeLastIncludes();
		
		$this->smarty->assign('meta_title', $context->getTitle());
		$this->smarty->assign('main_content', $context->getMainContent());
		$this->handleAdditionalResources($context);
		
		$asset = UnzerCw_Util::getAssetResolver()->resolveAssetStream('base.tpl');
		if (!($asset instanceof Customweb_Core_Stream_Input_File)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Core_Stream_Input_File');
		}
		$rs = $this->smarty->fetch($asset->getFilePath());
		
		// Fix encoding.
		$rs = str_replace('content="text/html; charset=iso-8859-1"', 'content="text/html; charset=UTF-8"', $rs);
		$rs = str_replace('content="text/html; charset=windows-1252"', 'content="text/html; charset=UTF-8"', $rs);
		
		$rs = Customweb_Core_Charset_UTF8::fixCharset($rs);
		
		return $rs;
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
	
	protected function includeLastIncludes() {
		extract($GLOBALS);
		require_once(PFAD_INCLUDES."letzterInclude.php");
	}
	
	protected function getNavigation(Customweb_Mvc_Layout_IRenderContext $context) {
		$this->addBreadcrumbItem($context->getTitle(), Customweb_Core_Http_ContextRequest::getInstance()->getUrl());
	
		return $this->getBreadcrumbItems();
	}
	
	protected function getAdditionalResourcesHtml(Customweb_Mvc_Layout_IRenderContext $context) {
		$output = '';
		$css = $context->getCssFiles();
		$css[] = 'form.css';
		$output .= UnzerCw_Util::buildResourceHtml($css, 'css');
		$output .= UnzerCw_Util::buildResourceHtml($context->getJavaScriptFiles(), 'js');
		return $output;
	}
	
	protected function handleAdditionalResources(Customweb_Mvc_Layout_IRenderContext $context) {
	
		$xajax = $this->smarty->get_template_vars('xajax_javascript');
		$xajax .= "\n" . $this->getAdditionalResourcesHtml($context);
	
		$this->smarty->assign('xajax_javascript', $xajax);
	}
	

}