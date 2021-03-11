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


require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Controller/Abstract.php';


class UnzerCw_Controller_AbstractBackend extends UnzerCw_Controller_Abstract {

	private $variables = array();
	
	private $errorMessage = null;
	private $successMessage = null;
	
	public function __construct() {
		if (!isset($_SESSION['AdminAccount']) || !is_object($_SESSION['AdminAccount'])) {
			throw new Exception("You need to be logged in to see this page.");
		}
		$this->addCssFile('admin.css');
		$this->addJavaScriptFile('line-item-grid.js');
	}
	
	protected function assign($key, $value) {
		$this->variables[$key] = $value;
	}
		
	protected function setErrorMessage($message) {
		$this->errorMessage = $message;
	}
	
	protected function setSuccessMessage($message) {
		$this->successMessage = $message;
	}
	
	protected function display($templateFile) {
		$path = PFAD_ROOT . '/plugins/unzercw/template/' . $templateFile . '.tpl';
		$this->render($path);
	}
	
	private function render($path) {
		ob_start();
		echo $this->getAdditionalResourcesHtml();
		extract($this->variables);
		
		if ($this->successMessage !== null) {
			echo '<p class="box_success">' . $this->successMessage . '</p>';
		}
		
		if ($this->errorMessage !== null) {
			echo '<p class="box_error">' . $this->errorMessage . '</p>';
		}
		require_once $path;
		$content = ob_get_contents();
		ob_end_clean();
		
		echo utf8_decode($content);
	}
	
	
	protected function getUrl(array $parameters = array(), $action = null, $controller = null) {
		$currentFileName = $GLOBALS['params']['cDateiname'];
		foreach (UnzerCw_Util::getPluginObject()->oPluginAdminMenu_arr as $menu) {
			if ($menu->cDateiname === $currentFileName){
				$parameters['kPluginAdminMenu'] = $menu->kPluginAdminMenu;
				break;
			}
		}
		
		if ($controller === null) {
			$controller = $this->getControllerName();
		}
		if ($action === null) {
			$action = $this->getActionName();
		}
		
		return UnzerCw_Util::getBackendUrl($controller, $action, $parameters);
	}
	
}