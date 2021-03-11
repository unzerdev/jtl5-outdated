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

require_once 'Customweb/Grid/Renderer.php';

require_once 'UnzerCw/Language.php';


class UnzerCw_Grid_Renderer extends Customweb_Grid_Renderer {
	
	public function getFilterControlCssClass() {
		return 'form-control';
	}
	
	public function getHrefCssClass() {
		return 'ajax-event page';
	}
	
	public function getGridCssClass() {
		return 'grid ajax-pane';
	}
	
	public function getTableCssClass() {
		return 'table ';
	}
	
	public function getPageCssClass() {
		return 'pages';
	}
	
	public function getInfoBoxCssClass() {
		return 'pageinfo';
	}
	
	public function getResultSelectorButtonCssClass() {
		return 'button orange';
	}
	
	public function getResultSelctorWrapperCssClass() {
		return 'result-selector'; 
	}
	
	public function renderResultSelectorButton() {
		return parent::renderResultSelectorButton();
	}
	
	public function getInfoPattern() {
		return UnzerCw_Language::_('Showing !startingItem to !endingItem of !totalItems items.');
	}
	
	public function getSubmitButtonLabel() {
		return UnzerCw_Language::_('Apply');
	}
	
	protected function renderFooter() {
		$html = '';
	
		$html .= '<div class="grid-footer block clearall">';
		$html .= $this->renderInfo();
		$html .= $this->renderPager();
		$html .= $this->renderResultSelector();
		$html .= '</div>';
	
		return $html;
	}
	
	protected function renderPagerLink($pageIndex) {
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex($pageIndex)->getParameters());
	
		$class = '';
		if ($pageIndex == $this->getRequestHandler()->getPageIndex()) {
			$class = 'active';
		}
	
		return '<li class="' . $class . '"><a class="' . $class . ' ' . $this->getHrefCssClass() . '" href="' . $url . '">' . ($pageIndex + 1) . '</a></li>';
	}
	
}