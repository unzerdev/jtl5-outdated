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

require_once 'Customweb/Payment/Entity/AbstractTransaction.php';
require_once 'Customweb/Payment/Authorization/ITransaction.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Log.php';
require_once 'UnzerCw/PaymentMethod.php';
require_once 'UnzerCw/VersionHelper.php';


/**
 *
 * @Entity(tableName = 'xplugin_unzercw_transactions')
 * @Filter(name = 'loadByOrderInternalId', where = 'orderInternalId = >orderInternalId', orderBy = 'orderInternalId')
 */
final class UnzerCw_Entity_Transaction extends Customweb_Payment_Entity_AbstractTransaction
{
	private $orderInternalId = null;

	private $orderStatus = null;

	private $sessionData = null;

	private $sessionDataDeprecated = null;

	private $session_data_deserialized = null;

	private $payment_method_reference = null;

	private static $classesLoaded = false;

	private $acceptUncertain = false;

	public function __construct() {
		self::loadRequiredClasses();
		parent::__construct();
	}

	protected function generateExternalTransactionId(Customweb_Database_Entity_IManager $entityManager) {
		return $this->generateExternalTransactionIdAlwaysAppend($entityManager);
	}

	public function onBeforeSave(Customweb_Database_Entity_IManager $entityManager){
		if($this->isSkipOnSafeMethods()){
			return;
		}
		parent::onBeforeSave($entityManager);

		// we can set only the state, when an order exists.
		if ($this->getOrderInternalId() === null || $this->getOrderInternalId() <= 0) {
			return;
		}
		$db = UnzerCw_Util::getDriver();
		$transactionObject = $this->getTransactionObject();

		if ($transactionObject === null) {
			return;
		}

		// Add a new payment entry, when there is a difference between the captured and paid amount.
		$amountNew = round($this->getTotalAmountToMarkAsPaid() - $this->getCurrentAmountMarkedAsPaid(), 5);
		$paymentMethodName = $transactionObject->getPaymentMethod()->getPaymentMethodDisplayName();

		$comment = 'ID: ' . $transactionObject->getTransactionId();
		if ($transactionObject->isAuthorizationUncertain()) {
			$comment .= ' (Zahlung ist unsicher)';
		}

		if ($amountNew > 0.00001) {
			$db->insert('tzahlungseingang', array(
				'>kBestellung' => $this->getOrderInternalId(),
				'>cZahlungsanbieter' => 'Unzer: ' . $paymentMethodName,
				'>cISO' => $transactionObject->getCurrencyCode(),
				'>fBetrag' => $amountNew,
				'>cAbgeholt' => 'N',
				'>cHinweis' => $comment,
				'?dZeit' => time(),
			));
		}

		// We write the payment information into the order table so later it can be used.
		$information = $this->getTransactionObject()->getPaymentInformation();
		if (!empty($information)) {
			$db->update('tbestellung', array('>cwPaymentInformation' => $information), 'kBestellung = ' . $this->getOrderInternalId());

			if (JTL_VERSION >= 400) {
				$db->update('tbestellung', array('>cPUIZahlungsdaten' => $information), 'kBestellung = ' . $this->getOrderInternalId());
			}
		}
	}

	protected function updateOrderStatus(Customweb_Database_Entity_IManager $entityManager, $currentStatus, $orderStatusSettingKey) {

	}

	private function getCurrentAmountMarkedAsPaid() {
		$db = UnzerCw_Util::getDriver();
		$statement = $db->query("SELECT SUM(fBetrag) as totalAmount FROM tzahlungseingang WHERE kBestellung = >orderId GROUP BY kBestellung")->setParameter('>orderId', $this->getOrderInternalId());
		$row = $statement->fetch();

		if (isset($row['totalAmount'])) {
			return $row['totalAmount'];
		}
		else {
			return 0;
		}
	}

	private function getTotalAmountToMarkAsPaid() {
		$transactionObject = $this->getTransactionObject();
		if (!($transactionObject instanceof Customweb_Payment_Authorization_ITransaction)) {
			return 0;
		}

		if ($transactionObject->getPaymentMethod()->existsPaymentMethodConfigurationValue('payment_receipt') && $transactionObject->getPaymentMethod()->getPaymentMethodConfigurationValue('payment_receipt') == 'capturing') {
			return $transactionObject->getCapturedAmount();
		}
		else {
			if (($transactionObject->isPaid() && !$transactionObject->isAuthorizationUncertain()) || $this->acceptUncertain) {
				return $transactionObject->getAuthorizationAmount();
			}
			else {
				return 0;
			}
		}
	}

	protected function authorize(Customweb_Database_Entity_IManager $entityManager) {
		$transactionObject = $this->getTransactionObject();
		if (!$transactionObject->isAuthorized()) {
			throw new Exception("Only authorized transaction can be authorized in the shop.");
		}

		$order = null;

		// Make sure we fetch the most current state of the transaction.
		$reloadedTransaction = $entityManager->fetch('UnzerCw_Entity_Transaction', $this->getTransactionId(), false);
		if ($reloadedTransaction->getAuthorizationStatus() !== Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_PENDING &&
				$reloadedTransaction->getAuthorizationStatus() !== self::AUTHORIZATION_STATUS_AUTHORIZING) {
			return;
		}
		$this->setAuthorizationStatus(Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_SUCCESSFUL);
		$entityManager->persist($this);

		$paymentInformation = $transactionObject->getPaymentInformation();
		if (!empty($paymentInformation)) {
			$GLOBALS['OrderPluginCwPaymentInformation'] = $paymentInformation;
		}

		$sessionBackup = $_SESSION;
		if ($reloadedTransaction->getOrderInternalId() <= 0) {
			$_SESSION = $this->getSession();

			// The plugin 'sus_its_bonus' adds a serialized variable to the session. We have to decode this otherwise we
			// would try to load the class which we don't have to.
			if (isset($_SESSION['WarenkorbBonus']) && strstr($_SESSION['WarenkorbBonus'], 'encoded') === '0') {
				$_SESSION['WarenkorbBonus'] = base64_decode(substr($_SESSION['WarenkorbBonus'], strlen('encoded')));
			}


			require_once PFAD_ROOT . 'includes/bestellabschluss_inc.php';

			// Make sure we can load the Artikel, even it is not anymore in stock.
			$GLOBALS['GlobaleEinstellungen']['global']['artikel_artikelanzeigefilter'] = 'some value which does not match any of in gibLagerfilter()';

			if (isset($_SESSION['kommentar'])) {
				$_POST['kommentar'] = $_SESSION['kommentar'];
			}

			// When the guest checkout is used JTL does not properly handle the language. As such we add it here
			// to ensure that the mail is sent with the right language.
			if (isset($_SESSION['Kunde']) && isset($_SESSION['kSprache'])) {
				$_SESSION['Kunde']->kSprache = $_SESSION['kSprache'];
			}

			// When the post array is set, we should set it again to make sure that the newsletter etc. is working.
			if (isset($_SESSION['cPost_arr'])) {
				$_POST = $_SESSION['cPost_arr'];
			}

			// JTL 4 does not consider the language in the session anymore as such we set it explicitily.
			if (class_exists('Shop', false) && method_exists('Shop', 'setLanguage')) {
				Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
			}

			// Requires that the session is set
			$order = finalisiereBestellung();
			$order = new Bestellung($order->kBestellung);
		}
		else {
			$order = new Bestellung($this->getOrderInternalId());
		}
		$order->fuelleBestellung(0);

		// By now the order has been filled with the corresponding IDs. If not
		// something went wrong.
		$kBestellNr = $order->cBestellNr;
		$kBestellung = $order->kBestellung;
		if (empty($kBestellNr) || empty($kBestellung)) {
			UnzerCw_Log::add("The order has not been created correctly. We cannot "
					. "continue at this point. It is expected that the cBestellNr and kBestellung " .
					" is set on the order. Transaction ID: " . $this->getTransactionId());
			throw new Exception("The order has not been created correctly. We cannot continue at this point. It is expected that the cBestellNr and kBestellung is set on the order.");
		}

		$this->setOrderNumber($order->cBestellNr);
		$this->setOrderInternalId($order->kBestellung);
		$kunde = new Kunde($order->kKunde);

		//mail
		$oPlugin = UnzerCw_Util::getPluginObject();
		$settings = $oPlugin->oPluginZahlungsmethodeAssoc_arr[UnzerCw_Util::getPaymentMethodModuleId($this->getPaymentMachineName())];
		if (($settings->nMailSenden & ZAHLUNGSART_MAIL_EINGANG) && strlen($kunde->cMail) > 0) {
			$obj = new stdClass();
			$obj->tkunde = $kunde;
			$obj->tbestellung = $order;
			if (!empty($paymentInformation)) {
				$obj->tbestellung->cwPaymentInformation = $paymentInformation;
			}
			$order->cwPaymentMethodName = $transactionObject->getPaymentMethod()->getPaymentMethodDisplayName();
			sendeMail(MAILTEMPLATE_BESTELLUNG_BEZAHLT, $obj);
		}

		$this->sessionData = null;
		//$this->session_data_deserialized = null;
		$_SESSION = $sessionBackup;
		unset($GLOBALS['OrderPluginCwPaymentInformation']);
	}

	public function getPaymentMethod() {
		if ($this->payment_method_reference === null) {
			$this->payment_method_reference = UnzerCw_PaymentMethod::getInstanceByPaymentMethodName($this->getPaymentMachineName());
		}
		return $this->payment_method_reference;
	}

	/**
	 * Returns the primary key of the order id.
	 *
	 * @Column(type = 'varchar')
	 */
	public function getOrderInternalId(){
		return $this->orderInternalId;
	}

	public function setOrderInternalId($orderInternalId){
		$this->orderInternalId = $orderInternalId;
		return $this;
	}

	/**
	 * @Column(type = 'varchar')
	 */
	public function getOrderStatus(){
		return $this->orderStatus;
	}

	public function setOrderStatus($orderStatus){
		$this->orderStatus = $orderStatus;
		return $this;
	}

	/**
	 * @Column(type = 'boolean')
	 */
	public function isAcceptUncertain() {
		return $this->acceptUncertain;
	}

	public function setAcceptUncertain($accept = true) {
		if ($accept == false && $this->acceptUncertain == true) {
			throw new Exception("Denying a accepted uncertain transaction is not possible.");
		}
		$this->acceptUncertain = $accept;
		return $this;
	}

	/**
	 * Alias to getOrderId()
	 *
	 * @return string
	 */
	public function getOrderNumber() {
		return $this->getOrderId();
	}

	/**
	 * Alias to setOrderId()
	 *
	 * @param string $orderId
	 * @return UnzerCw_Entity_Transaction
	 */
	public function setOrderNumber($orderId) {
		$this->setOrderId($orderId);
		return $this;
	}


	/**
	 * @Column(type = 'binaryObject', name='sessionDataBinary')
	 */
	public function getSessionData() {
		return $this->sessionData;
	}

	public function setSessionData($data) {
		$this->sessionData = $data;

		unset($this->sessionData['Kundengruppe']);
		unset($this->sessionData['Hersteller']);
		unset($this->sessionData['Lieferlaender']);
		unset($this->sessionData['Zahlungsarten']);
		unset($this->sessionData['Linkgruppen']);
		unset($this->sessionData['oArtikelUebersichtKey_arr']);
		unset($this->sessionData['oKategorie_arr']);
		unset($this->sessionData['kKategorieVonUnterkategorien_arr']);

		// The plugin 'sus_its_bonus' adds a serialized variable to the session. We have to encode this otherwise we
		// would try to load the class which we don't have to.
		if (isset($this->sessionData['WarenkorbBonus']) && strstr($this->sessionData['WarenkorbBonus'], 'encoded') !== '0') {
			$this->sessionData['WarenkorbBonus'] = 'encoded' . base64_encode($this->sessionData['WarenkorbBonus']);
		}

		return $this;
	}


	/**
	 * @Column(type = 'text', name='sessionData')
	 *
	 * @return array
	 */
	public function getSessionDataDeprecated(){

		return $this->sessionDataDeprecated;
	}

	public function setSessionDataDeprecated($data){
		$value = unserialize(base64_decode($data));
		if(!empty($value)){
			$this->sessionData = $value;
		}
		$this->sessionDataDeprecated = $data;
		return $this;
	}


	public function setSession(array $data) {
		$this->sessionData = $data;

		unset($this->sessionData['Kundengruppe']);
		unset($this->sessionData['Hersteller']);
		unset($this->sessionData['Lieferlaender']);
		unset($this->sessionData['Zahlungsarten']);
		unset($this->sessionData['Linkgruppen']);
		unset($this->sessionData['oArtikelUebersichtKey_arr']);
		unset($this->sessionData['oKategorie_arr']);
		unset($this->sessionData['kKategorieVonUnterkategorien_arr']);


		// The plugin 'sus_its_bonus' adds a serialized variable to the session. We have to encode this otherwise we
		// would try to load the class which we don't have to.
		if (isset($this->sessionData['WarenkorbBonus']) && strstr($this->sessionData['WarenkorbBonus'], 'encoded') !== '0') {
			$this->sessionData['WarenkorbBonus'] = 'encoded' . base64_encode($this->sessionData['WarenkorbBonus']);
		}
		return $this;
	}

	public function getSession() {
		return $this->sessionData;
	}

	public function getJTLSuccessUrl() {

		// When, for whatever reason, no order ID has been set the code below may cause issues because
		// we eventually insert for a order ID which is zero an entry. This can be problematic. Hence we have to prevent this.
		if ($this->getOrderInternalId() <= 0) {
			die('The order ID has not been set and as such we cannot redirect to the success page.');
		}

		$db = UnzerCw_Util::getDriver();

		$order = new Bestellung($this->getOrderInternalId() , true);
		$statement = $db->query('SELECT * FROM tbestellid WHERE kBestellung = >orderId')->setParameter('>orderId', $this->getOrderInternalId());

		$i = 'not-present';
		$row = $statement->fetch();
		if ($row !== false && count($row) > 0) {
			$i = $row['cId'];
		}
		else {
			$bestellid = new stdClass();
			$bestellid->cId = gibUID(40, $order->kBestellung.md5(time()));
			$bestellid->kBestellung = $order->kBestellung;
			$bestellid->dDatum = "now()";
			UnzerCw_VersionHelper::getInstance()->getDb()->insertRow('tbestellid',$bestellid);
			$i = $bestellid->cId;
		}

		$url = UnzerCw_Util::getStoreBaseUrl() . '/bestellabschluss.php?i=' . $i;

		return $url;
	}



	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	public static function loadById($id, $cache = true) {
		return UnzerCw_Util::getEntityManager()->fetch('UnzerCw_Entity_Transaction', $id, $cache);
	}


	public static function getGridQuery() {
		return 'SELECT * FROM xplugin_unzercw_transactions WHERE ${WHERE} ${ORDER_BY} ${LIMIT}';
	}


	/**
	 *
	 * @param int $id
	 * @return UnzerCw_Entity_Transaction[]
	 */
	public static function getTransactionsByOrderInternalId($id) {
		$rs = UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_Transaction', 'loadByOrderInternalId', array('>orderInternalId' => $id));
		if (count($rs) > 0) {
			return $rs;
		}
		else {
			return array();
		}
	}


	public static function getTransactionsWithChangedOrderStatus($limit = 50) {
		$db = UnzerCw_Util::getDriver();

		$transactions = array();
		$statment = $db->query("SELECT t.transactionId FROM xplugin_unzercw_transactions AS t, tbestellung AS b WHERE t.orderInternalId = b.kBestellung AND t.orderStatus != b.cStatus LIMIT 0," . (int)$limit);

		while (($data = $statment->fetch()) !== false) {
			$transactions[$data['transactionId']] = self::loadById($data['transactionId']);
		}

		return $transactions;
	}

	private static function checkFileInclusion($file) {
		if (file_exists($file)) {
			require_once $file;
		}
	}

	private static function loadRequiredClasses() {

		if (self::$classesLoaded === false) {
			// Require all classes, which may be used in the session.
			self::checkFileInclusion(PFAD_ROOT . PFAD_CLASSES . "class.JTL-Shop.Bestellung.php");
			self::checkFileInclusion(PFAD_ROOT . PFAD_CLASSES . "class.JTL-Shop.Warenkorb.php");
			self::checkFileInclusion(PFAD_ROOT . PFAD_CLASSES . "class.JTL-Shop.WarenkorbPos.php");
			self::checkFileInclusion(PFAD_ROOT . PFAD_CLASSES . 'class.JTL-Shop.Shopsetting.php');

			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.CacheFactory.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES_CORE."class.core.Session.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Kategorie.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.helper.ArtikelListe.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.helper.KategorieListe.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.helper.Url.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.helper.Link.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Sprache.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES."tools.Global.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES."sprachfunktionen.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_BLOWFISH."xtea.class.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES_EXT."auswahlassistent_ext_inc.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES."artikelsuchspecial_inc.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Artikel.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Hersteller.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Bewertung.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Bestellung.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Preise.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Kunde.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Lieferadresse.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.ZahlungsInfo.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Warenkorb.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WarenkorbPos.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WarenkorbPosEigenschaft.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WarenkorbPers.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WarenkorbPersPos.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WarenkorbPersPosEigenschaft.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Wunschliste.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WunschlistePos.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.WunschlistePosEigenschaft.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Vergleichsliste.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Eigenschaft.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.EigenschaftWert.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Merkmal.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.MerkmalWert.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.UstID.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Rechnungsadresse.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Versandart.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Boxen.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Kampagne.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.KundenwerbenKunden.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.ZahlungsLog.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.CheckBox.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Jtllog.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Trennzeichen.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Nummern.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.ExtensionPoint.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.ImageMap.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Slider.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Bestseller.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Kuponneukunde.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Redirect.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Staat.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Preisradar.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.GarbageCollector.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Emailhistory.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Kundendatenhistory.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_CLASSES."class.JTL-Shop.Link.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES_EXT."class.JTL-Shop.Download.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES_EXT."class.JTL-Shop.AuswahlAssistent.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES_EXT."class.JTL-Shop.Upload.php");
			self::checkFileInclusion(PFAD_ROOT.PFAD_INCLUDES_EXT."class.JTL-Shop.Konfigurator.php");
			self::$classesLoaded = true;
		}

	}

}
