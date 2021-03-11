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

require_once 'Customweb/Payment/BackendOperation/Form.php';
require_once 'Customweb/IForm.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/BackendFormRenderer.php';
require_once 'UnzerCw/Controller/AbstractBackend.php';


class UnzerCw_Controller_Form extends UnzerCw_Controller_AbstractBackend {

	
	public function indexAction() {
		$this->listAction();
	}
	
	public function listAction() {
		$forms = array();
		$adapter = UnzerCw_Util::getBackendFormAdapter();
		if ($adapter !== null) {
			$forms = $adapter->getForms();
		}
		$this->assign('forms', $forms);
		
		$this->display('form/list');
	}
	
	public function viewAction() {
		$form = $this->getCurrentForm();
	
		$buttons = null;
		if ($form->isProcessable()) {
			$form = new Customweb_Payment_BackendOperation_Form($form);
			$form->setTargetUrl($this->getUrl(array('form' => $form->getMachineName()), 'save') . "#backend-forms")->setRequestMethod(Customweb_IForm::REQUEST_METHOD_POST);
		}
	
		$renderer = new UnzerCw_BackendFormRenderer();
	
		$this->addCssFile('form.css');
		$this->assign('back', $this->getUrl(array(), 'list'));
		$this->assign('form', $form);
		$this->assign('formHtml', $renderer->renderForm($form));
		$this->display('form/view');
	}
	
	public function saveAction() {
	
		$form = $this->getCurrentForm();
	
		$params = $_REQUEST;
		if (!isset($params['button'])) {
			throw new Exception("No button returned.");
		}
		$pressedButton = null;
		foreach ($params['button'] as $buttonName => $value) {
			foreach ($form->getButtons() as $button) {
				if ($button->getMachineName() == $buttonName) {
					$pressedButton = $button;
				}
			}
		}
	
		if ($pressedButton === null) {
			throw new Exception("Could not find pressed button.");
		}
		UnzerCw_Util::getBackendFormAdapter()->processForm($form, $pressedButton, $params);
				
		$this->setSuccessMessage("Die Verarbeitung war erfolgreich.");
		
		$this->viewAction();
	}
	
	
	/**
	 * @return Customweb_Payment_BackendOperation_IForm
	 */
	protected function getCurrentForm() {
		$adapter = UnzerCw_Util::getBackendFormAdapter();
	
		if ($adapter !== null && isset($_GET['form'])) {
			$forms = $adapter->getForms();
			$formName = $_GET['form'];
			$currentForm = null;
			foreach ($forms as $form) {
				if ($form->getMachineName() == $formName) {
					return $form;
				}
			}
		}
	
		die('No form is set or no backend adapter present in the container.');
	}
	
	
	
	
}