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

require_once 'Customweb/Payment/Authorization/OrderContext/AbstractDeprecated.php';
require_once 'Customweb/Date/DateTime.php';
require_once 'Customweb/Payment/Authorization/IOrderContext.php';
require_once 'Customweb/Core/Language.php';
require_once 'Customweb/Core/Util/Rand.php';
require_once 'Customweb/Core/Charset/UTF8.php';

require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/VersionHelper.php';


class UnzerCw_AbstractOrderContext extends Customweb_Payment_Authorization_OrderContext_AbstractDeprecated implements Customweb_Payment_Authorization_IOrderContext {

	/**
	 * @var Customweb_Payment_Authorization_DefaultInvoiceItem[]
	 */
	protected $invoiceItems = array();

	/**
	 * @var Customweb_Payment_Authorization_IPaymentMethod
	 */
	protected $paymentMethod = null;

	/**
	 * @var Address
	 */
	protected $shippingAddress = null;

	/**
	 * @var Address
	 */
	protected $billingAddress = null;

	/**
	 * @var int
	 */
	protected $customerNumberOfOrders = 0;

	/**
	 * @var DateTime
	 */
	protected $customerCreationDate = null;

	/**
	 * @var string
	 */
	protected $shippingMethodName = null;

	/**
	 * @var DateTime
	 */
	protected $billingDateOfBirth = null;

	/**
	 * @var DateTime
	 */
	protected $shippingDateOfBirth = null;

	/**
	 * @var float
	 */
	protected $orderTotal = 0;

	/**
	 * @var string
	 */
	protected $currencyCode = 'EUR';

	/**
	 * @var string
	 */
	protected $languageCode = 'de';

	/**
	 * @var string
	 */
	protected $checkoutId = null;

	public function __construct(Customweb_Payment_Authorization_IPaymentMethod $paymentMethod, array $invoiceItems, $shippingAddress, $billingAddress, $shippingMethodName, $orderTotal, $currencyCode, $languageCode) {
		$this->paymentMethod = $paymentMethod;
		$this->invoiceItems = $invoiceItems;
		$this->shippingAddress = $shippingAddress;
		$this->billingAddress = $billingAddress;
		$this->shippingMethodName = $shippingMethodName;

		if ($this->shippingAddress == null) {
			$this->shippingAddress = $this->billingAddress;
		}

		$customerId = $this->getCustomerId();

		if ($customerId !== null) {
			$anzahl_obj = UnzerCw_VersionHelper::getInstance()->getDb()->executeQuery("select count(*) as anz from tbestellung where kKunde=" . $customerId . " and (cStatus=\"" . BESTELLUNG_STATUS_BEZAHLT . "\" or cStatus=\"" . BESTELLUNG_STATUS_VERSANDT . "\")", 1);
			if (isset($anzahl_obj->anz)) {
				$this->customerNumberOfOrders = $anzahl_obj->anz;
			}
			$customer = new Kunde($customerId);
			try {
				$this->customerCreationDate = new Customweb_Date_DateTime($customer->dErstellt);
			}
			catch(Exception $e) {
				$this->customerCreationDate = null;
			}
		}

		$this->billingDateOfBirth = $this->getCustomerDateOfBirth($this->billingAddress->kKunde);
		$this->shippingDateOfBirth = $this->getCustomerDateOfBirth($this->shippingAddress->kKunde);
		$this->orderTotal = $orderTotal;
		$this->currencyCode = $currencyCode;
		$this->languageCode = $languageCode;

		// The birth date is not stored in the order hence we need to load it from the session in any case.
		if (empty($this->billingDateOfBirth) && isset($_SESSION['Kunde']) && $_SESSION['Kunde'] instanceof Kunde) {
			$this->billingDateOfBirth = $this->getCustomerDateOfBirthByCustomer($_SESSION['Kunde']);
		}
		if (empty($this->shippingDateOfBirth) && isset($_SESSION['Kunde']) && $_SESSION['Kunde'] instanceof Kunde) {
			$this->shippingDateOfBirth = $this->getCustomerDateOfBirthByCustomer($_SESSION['Kunde']);
		}

		if (!isset($_SESSION['unzercw_checkout_id'])) {
			$_SESSION['unzercw_checkout_id'] = array();
		}
		if (!isset($_SESSION['unzercw_checkout_id'][$paymentMethod->getPaymentMethodName()])) {
			$_SESSION['unzercw_checkout_id'][$paymentMethod->getPaymentMethodName()] = Customweb_Core_Util_Rand::getUuid();
		}
		$this->checkoutId = $_SESSION['unzercw_checkout_id'][$paymentMethod->getPaymentMethodName()];
	}

	public function getCheckoutId() {
		return $this->checkoutId;
	}


	public function getOrderAmountInDecimals() {
		return $this->orderTotal;
	}

	public function getCurrencyCode() {
		return $this->currencyCode;
	}

	public function getLanguage() {
		return new Customweb_Core_Language($this->languageCode);
	}

	public function getInvoiceItems() {
		return $this->invoiceItems;
	}

	public function getCustomerId() {
		if (isset($this->billingAddress->kKunde) && $this->billingAddress->kKunde > 0) {
			return $this->billingAddress->kKunde;
		}
		else {
			return null;
		}
	}

	public function isNewCustomer() {
		if ($this->customerNumberOfOrders > 0) {
			return 'existing';
		}
		else {
			return 'new';
		}
	}

	public function getCustomerRegistrationDate() {
		return $this->customerCreationDate;
	}

	public function getShippingMethod() {
		if ($this->shippingMethodName !== null) {
			return self::encodeStringToUTF8($this->shippingMethodName);
		}
		else {
			return UnzerCw_Language::_('No Shipping');
		}
	}

	public function getPaymentMethod() {
		return $this->paymentMethod;
	}

	public function getCustomerEMailAddress() {
		return $this->getBillingEMailAddress();
	}

	public function getBillingEMailAddress() {
		return $this->billingAddress->cMail;
	}

	public function getBillingGender() {
		if ($this->getBillingCompanyName() !== null) {
			return 'company';
		}
		else {
			if ($this->billingAddress->cAnrede == 'm') {
				return 'male';
			}
			else {
				return 'female';
			}
		}
	}

	public function getBillingSalutation() {
		if (!empty($this->billingAddress->cTitel)) {
			return self::encodeStringToUTF8($this->billingAddress->cTitel);
		}
		else {
			return null;
		}
	}

	public function getBillingFirstName() {
		return self::encodeStringToUTF8($this->billingAddress->cVorname);
	}

	public function getBillingLastName() {
		return self::encodeStringToUTF8($this->billingAddress->cNachname);
	}

	public function getBillingStreet() {
		return self::encodeStringToUTF8($this->billingAddress->cStrasse) . ' ' . self::encodeStringToUTF8($this->billingAddress->cHausnummer);
	}

	public function getBillingCity() {
		return self::encodeStringToUTF8($this->billingAddress->cOrt);
	}

	public function getBillingPostCode() {
		return self::encodeStringToUTF8($this->billingAddress->cPLZ);
	}

	public function getBillingState() {
		if (isset($this->billingAddress->cBundesland)) {
			return self::encodeStringToUTF8($this->getStateCode($this->billingAddress->cBundesland));
		}

		return null;
	}

	public function getBillingCountryIsoCode() {
		return self::encodeStringToUTF8($this->billingAddress->cLand);
	}

	public function getBillingPhoneNumber() {
		$phone = self::encodeStringToUTF8($this->billingAddress->cTel);
		if (!empty($phone)) {
			return $phone;
		}
		else {
			return null;
		}
	}

	public function getBillingMobilePhoneNumber() {
		$phone = self::encodeStringToUTF8($this->billingAddress->cMobil);
		if (!empty($phone)) {
			return $phone;
		}
		else {
			return null;
		}
	}

	public function getBillingDateOfBirth() {
		return $this->billingDateOfBirth;
	}

	public function getBillingCommercialRegisterNumber() {
		return null;
	}

	public function getBillingSalesTaxNumber() {
		if (!empty($this->billingAddress->cUSTID)) {
			return self::encodeStringToUTF8($this->billingAddress->cUSTID);
		}
		else {
			return null;
		}
	}

	public function getBillingSocialSecurityNumber() {
		return null;
	}

	public function getBillingCompanyName() {
		if (!empty($this->billingAddress->cFirma)) {
			return self::encodeStringToUTF8($this->billingAddress->cFirma);
		}
		else {
			return null;
		}
	}

	public function getShippingEMailAddress() {
		return $this->shippingAddress->cMail;
	}

	public function getShippingGender() {
		if ($this->getShippingCompanyName() !== null) {
			return 'company';
		}
		else {
			if ($this->shippingAddress->cAnrede == 'm') {
				return 'male';
			}
			else {
				return 'female';
			}
		}
	}

	public function getShippingSalutation() {
		if (!empty($this->shippingAddress->cTitel)) {
			return self::encodeStringToUTF8($this->shippingAddress->cTitel);
		}
		else {
			return null;
		}
	}


	public function getShippingFirstName() {
		return self::encodeStringToUTF8($this->shippingAddress->cVorname);
	}

	public function getShippingLastName() {
		return self::encodeStringToUTF8($this->shippingAddress->cNachname);
	}

	public function getShippingStreet() {
		return self::encodeStringToUTF8($this->shippingAddress->cStrasse) . ' ' . self::encodeStringToUTF8($this->shippingAddress->cHausnummer);
	}

	public function getShippingCity() {
		return self::encodeStringToUTF8($this->shippingAddress->cOrt);
	}

	public function getShippingPostCode() {
		return self::encodeStringToUTF8($this->shippingAddress->cPLZ);
	}

	public function getShippingState() {
		if (isset($this->shippingAddress->cBundesland)) {
			return self::encodeStringToUTF8($this->getStateCode($this->shippingAddress->cBundesland));
		}
		else {
			return null;
		}
	}

	public function getShippingCountryIsoCode() {
		return self::encodeStringToUTF8($this->shippingAddress->cLand);
	}

	public function getShippingPhoneNumber() {
		$phone = self::encodeStringToUTF8($this->shippingAddress->cTel);
		if (!empty($phone))  {
			return $phone;
		}
		else {
			return null;
		}
	}

	public function getShippingMobilePhoneNumber() {
		$phone = self::encodeStringToUTF8($this->shippingAddress->cMobil);
		if (!empty($phone))  {
			return $phone;
		}
		else {
			return null;
		}
	}

	public function getShippingDateOfBirth() {
		return $this->shippingDateOfBirth;
	}

	public function getShippingCompanyName() {
		if (!empty($this->shippingAddress->cFirma)) {
			return self::encodeStringToUTF8($this->shippingAddress->cFirma);
		}
		else {
			return null;
		}
	}

	public function getShippingCommercialRegisterNumber() {
		return null;
	}

	public function getShippingSalesTaxNumber() {
		if (!empty($this->shippingAddress->cUSTID)) {
			return self::encodeStringToUTF8($this->shippingAddress->cUSTID);
		}
		else {
			return null;
		}
	}

	public function getShippingSocialSecurityNumber() {
		return null;
	}

	public function getOrderParameters() {
		return array(
			'shop_system_version' => JTL_VERSION
		);
	}

	protected function getStateCode($stateName) {
		if (!empty($stateName)) {
			$state = Staat::getRegionByName($stateName);
			if ($state !== null) {
				return $state->getCode();
			}
		}
		return null;
	}

	protected function getCustomerDateOfBirth($customerId) {
		if ($customerId !== null) {
			return $this->getCustomerDateOfBirthByCustomer(new Kunde($customerId));
		}
		return null;
	}

	protected function getCustomerDateOfBirthByCustomer(Kunde $customer) {
		if ($customer !== null) {
			if (!empty($customer->dGeburtstag) && $customer->dGeburtstag != '0000-00-00' && $customer->dGeburtstag != '00.00.0000') {
				try {
					return new Customweb_Date_DateTime($customer->dGeburtstag);
				}
				catch(Exception $e) {
					// Ignore
				}
			}
		}

		return null;
	}

	protected function getSessionLanguageCode() {
		if(isset($_SESSION['cISOSprache']) && !empty($_SESSION['cISOSprache'])) {
			return UnzerCw_VersionHelper::getInstance()->convertISO2ISO639($_SESSION['cISOSprache']);
		}
		else {
			return UnzerCw_Util::getDefaultLanguageCode();
		}
	}

	protected static function encodeStringToUTF8($string) {
		return Customweb_Core_Charset_UTF8::fixCharset(html_entity_decode($string));
	}

}