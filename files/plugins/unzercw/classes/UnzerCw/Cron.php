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

require_once 'Customweb/Util/System.php';
require_once 'Customweb/Cron/Processor.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/SettingApi.php';
require_once 'UnzerCw/MutableConstantApi.php';


class UnzerCw_Cron {
	
	
	const TRANSACTION_LIMIT_PER_BATCH = 50;
	
	const CRON_MUTEX_NAME = 'last_cron_start';
	
	private $cronStarts = 0;
	
	/**
	 * This is executed on every cron invocation.
	 */
	public function run() {
		$this->cronStarts = time() - 1;
		if (!$this->isOtherCronRunning()) {
			$config = new UnzerCw_SettingApi();
			UnzerCw_MutableConstantApi::setConstant(self::CRON_MUTEX_NAME, 0);
		}
		$cronProcessor = new Customweb_Cron_Processor(UnzerCw_Util::getContainer());
		$cronProcessor->run();
	}
	
	public function getCronStartTime() {
		return $this->cronStarts;
	}
	
	private function isOtherCronRunning() {
		$mutex = UnzerCw_MutableConstantApi::getConstant(self::CRON_MUTEX_NAME);
		if ($mutex === null) {
			UnzerCw_MutableConstantApi::setConstant(self::CRON_MUTEX_NAME, time());
			return false;
		}
		$maxExecutionTime = Customweb_Util_System::getMaxExecutionTime() + 5;
		if ($mutex + $maxExecutionTime < time()) {
			UnzerCw_MutableConstantApi::setConstant(self::CRON_MUTEX_NAME, time());
			return false;
		}
		else {
			return true;
		}
	}
	
	
}