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

/* @var $transaction UnzerCw_Entity_Transaction  */
?>
<div class="buttons">
	<a class="btn btn-default" href="<?php echo $back; ?>" class="button blue"><?php echo UnzerCw_Language::_('Back'); ?> </a>
	<?php if (isset($refresh_status_url)): ?>
		<a class="btn btn-success" href="<?php echo $refresh_status_url;?>">Transaktion Status Erneuern</a>
	<?php endif; ?>
	
	
	<?php if (isset($capture_url)): ?>
		<a class="btn btn-success" href="<?php echo $capture_url;?>">Verbuchen</a>
	<?php endif; ?>
	
	
	<?php if (isset($refund_url)): ?>
		<a class="btn btn-danger" href="<?php echo $refund_url;?>">Gutschreiben</a>
	<?php endif; ?>
	
	
	<?php if (isset($cancel_url)): ?>
		<a class="btn btn-danger" href="<?php echo $cancel_url;?>">Stornieren</a>
	<?php endif; ?>
	
	
	<?php if (isset($manual_accept_uncertain_url)): ?>
		<a class="btn btn-danger" href="<?php echo $manual_accept_uncertain_url;?>"><?php echo $manual_accept_uncertain_button_name;?></a>
	<?php endif; ?>
</div>

<h3>Transaktionsdaten</h3>
<table class="table">
	<tr>
		<td><?php echo UnzerCw_Language::_('Authorization Status') ?></td>
		<td><?php echo $transaction->getAuthorizationStatus(); ?></td>
	</tr>

	<tr>
		<td><?php echo UnzerCw_Language::_('Transaction ID') ?></td>
		<td><?php echo $transaction->getTransactionId(); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Transaction Number') ?></td>
		<td><?php echo $transaction->getTransactionExternalId(); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Order ID') ?></td>
		<td><?php echo $transaction->getOrderInternalId(); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Order Number') ?></td>
		<td><?php echo $transaction->getOrderNumber(); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Created On') ?></td>
		<td><?php echo $transaction->getCreatedOn()->format(Customweb_Core_Util_System::getDefaultDateTimeFormat());; ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Updated On') ?></td>
		<td><?php echo $transaction->getUpdatedOn()->format(Customweb_Core_Util_System::getDefaultDateTimeFormat()); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Customer ID') ?></td>
		<td><?php echo $transaction->getCustomerId(); ?></td>
	</tr>
	<tr>
		<td><?php echo UnzerCw_Language::_('Payment ID') ?></td>
		<td><?php echo $transaction->getPaymentId(); ?></td>
	</tr>

	<?php if (is_object($transaction->getTransactionObject())):?>
	<?php foreach ($transaction->getTransactionObject()->getTransactionLabels() as $label): ?>
	<tr>
		<td><?php echo $label['label'];?> 
		<?php if (isset($label['description'])): ?> 
			<img src="../plugins/unzercw/template/images/help.png" alt="<?php echo $label['description']; ?>" title="<?php echo $label['description']; ?>" style="vertical-align:middle; cursor:help;">
		<?php endif; ?>
		</td>
		<td><?php echo Customweb_Core_Util_Xml::escape($label['value']);?>
		</td>
	</tr>
	<?php endforeach;?>

	<?php if($transaction->getTransactionObject()->isAuthorized() && $transaction->getTransactionObject()->getPaymentInformation() != null): ?>
	<tr>
		<td><?php echo UnzerCw_Language::_('Payment Information') ?></td>
		<td><?php echo $transaction->getTransactionObject()->getPaymentInformation(); ?></td>
	</tr>
	<?php endif; ?>
	<?php endif;?>
</table>


<?php if (is_object($transaction->getTransactionObject())):?>
<h3>Kundendaten:</h3>
<table class="table">
	<tr>
		<td>Rechnungsadresse:</td>
		<td>
			<?php $address = $transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getBillingAddress(); ?>
			<?php echo $address->getFirstName();?> <?php echo $address->getLastName();?><br />
			<?php $value = $address->getCompanyName(); if (!empty($value)): ?>
				<?php echo $address->getCompanyName(); ?>
			<?php endif;?>
			<?php echo $address->getStreet();?><br />
			<?php echo $address->getPostCode(); ?> <?php echo $address->getCity(); ?><br />
			<?php echo $address->getCountryIsoCode(); ?><br />
			<?php $value = $address->getPhoneNumber(); if (!empty($value)): ?>
				Tel: <?php echo $address->getPhoneNumber(); ?><br />
			<?php endif;?>
			<?php $value = $address->getMobilePhoneNumber(); if (!empty($value)):?>
				Mobile: <?php echo $address->getMobilePhoneNumber(); ?><br />
			<?php endif;?>
			<?php $value = $address->getMobilePhoneNumber(); if (!empty($value)):?>
				E-Mail: <?php echo $address->getEMailAddress(); ?><br />
			<?php endif;?>
		</td>
	</tr>
	<tr>
		<td>Lieferadresse:</td>
		<td>
			<?php $address = $transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getShippingAddress(); ?>
			<?php echo $address->getFirstName();?> <?php echo $address->getLastName();?><br />
			<?php $value = $address->getCompanyName(); if (!empty($value)): ?>
				<?php echo $address->getCompanyName(); ?>
			<?php endif;?>
			<?php echo $address->getStreet();?><br />
			<?php echo $address->getPostCode(); ?> <?php echo $address->getCity(); ?><br />
			<?php echo $address->getCountryIsoCode(); ?><br />
			<?php $value = $address->getPhoneNumber(); if (!empty($value)): ?>
				Tel: <?php echo $address->getPhoneNumber(); ?><br />
			<?php endif;?>
			<?php $value = $address->getMobilePhoneNumber(); if (!empty($value)):?>
				Mobile: <?php echo $address->getMobilePhoneNumber(); ?><br />
			<?php endif;?>
			<?php $value = $address->getMobilePhoneNumber(); if (!empty($value)):?>
				E-Mail: <?php echo $address->getEMailAddress(); ?><br />
			<?php endif;?>
		</td>
	</tr>
	<tr>
		<td>E-Mail Adresse:</td>
		<td>
			<?php echo $transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getCustomerEMailAddress(); ?>
		</td>
	</tr>
</table>

<h3>Artikel:</h3>
<table class="table">
	<thead>
		<tr>
			<th>Name</th>
			<th>Artikelnummer</th>
			<th>Quantit&auml;t</th>
			<th>Mwst. Satz</th>
			<th>MwSt.</th>
			<th>Total Preis (inkl. MwSt.)</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($transaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getInvoiceItems() as $item):?>
		<tr>
			<td><?php echo $item->getName(); ?></td>
			<td><?php echo $item->getSku(); ?></td>
			<td><?php echo round($item->getQuantity(), 2); ?></td>
			<td><?php echo round($item->getTaxRate(), 2); ?> %</td>
			<td><?php echo Customweb_Util_Currency::formatAmount($item->getTaxAmount(), $transaction->getTransactionObject()->getCurrencyCode()); ?></td>
			<td><?php echo Customweb_Util_Currency::formatAmount($item->getAmountIncludingTax(), $transaction->getTransactionObject()->getCurrencyCode()); ?></td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>
<?php endif;?>



			
<?php if (is_object($transaction->getTransactionObject()) && count($transaction->getTransactionObject()->getCaptures()) > 0): ?>
<h3><?php echo UnzerCw_Language::_('Captures for this transaction'); ?></h3>
<table class="table">
	<thead>
		<tr>
			<th><?php echo UnzerCw_Language::_('Date'); ?></th>
			<th><?php echo UnzerCw_Language::_('Amount'); ?></th>
			<th><?php echo UnzerCw_Language::_('Status'); ?></th>
			<th> </th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($transaction->getTransactionObject()->getCaptures() as $capture):?>
		<tr>
			<td><?php echo $capture->getCaptureDate()->format(Customweb_Core_Util_System::getDefaultDateTimeFormat()); ?></td>
			<td><?php echo $capture->getAmount(); ?></td>
			<td><?php echo $capture->getStatus(); ?></td>
			<td>
				<a class="button orange" href="<?php echo $this->getUrl(array('transaction_id' => $transaction->getTransactionId(), 'capture_id' => $capture->getCaptureId()), 'viewCapture'); ?>">
					<?php echo UnzerCw_Language::_('View'); ?>
				</a>
			</td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>
<br />
<?php endif;?>



<?php if (is_object($transaction->getTransactionObject()) && count($transaction->getTransactionObject()->getRefunds()) > 0): ?>
<h3><?php echo UnzerCw_Language::_('Refunds for this transaction'); ?></h3>
<table class="table">
	<thead>
		<tr>
			<th><?php echo UnzerCw_Language::_('Date'); ?></th>
			<th><?php echo UnzerCw_Language::_('Amount'); ?></th>
			<th><?php echo UnzerCw_Language::_('Status'); ?></th>
			<th> </th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($transaction->getTransactionObject()->getRefunds() as $refund):?>
		<tr>
			<td><?php echo $refund->getRefundedDate()->format(Customweb_Core_Util_System::getDefaultDateTimeFormat()); ?></td>
			<td><?php echo $refund->getAmount(); ?></td>
			<td><?php echo $refund->getStatus(); ?></td>
			<td>
				<a class="button orange" href="<?php echo $this->getUrl(array('transaction_id' => $transaction->getTransactionId(), 'refund_id' => $refund->getRefundId()), 'viewRefund'); ?>">
					<?php echo UnzerCw_Language::_('View'); ?>
				</a>
			</td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>
<br />
<?php endif;?>



<?php if (is_object($transaction->getTransactionObject()) && count($transaction->getTransactionObject()->getHistoryItems()) > 0): ?>
<h3><?php echo UnzerCw_Language::_('Transactions History'); ?></h3>
<table class="table">
	<thead>
		<tr>
			<th><?php echo UnzerCw_Language::_('Date'); ?></th>
			<th><?php echo UnzerCw_Language::_('Action'); ?></th>
			<th><?php echo UnzerCw_Language::_('Message'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($transaction->getTransactionObject()->getHistoryItems() as $item):?>
		<tr>
			<td><?php echo $item->getCreationDate()->format(Customweb_Core_Util_System::getDefaultDateTimeFormat()); ?></td>
			<td><?php echo $item->getActionPerformed(); ?></td>
			<td><?php echo $item->getMessage(); ?></td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>
<br />
<?php endif;?>


<?php if (count($relatedTransactions) > 0): ?>
<h3><?php echo UnzerCw_Language::_('Transactions related to the same order'); ?></h3>
<table class="table">
	<thead>
		<tr>
			<th><?php echo UnzerCw_Language::_('Transaction Number'); ?></th>
			<th><?php echo UnzerCw_Language::_('Is Authorized'); ?></th>
			<th><?php echo UnzerCw_Language::_('Authorization Amount'); ?></th>
			<th></th>
		</tr>
	</thead>
	<?php foreach ($relatedTransactions as $transaction): ?>
		<?php if (is_object($transaction->getTransactionObject())) : ?>
		<tr>
			<td><?php echo $transaction->getTransactionExternalId(); ?></td>
			<td><?php echo $transaction->getTransactionObject()->isAuthorized() ? UnzerCw_Language::_('yes') : UnzerCw_Language::_('no'); ?></td>
			<td><?php echo $transaction->getTransactionObject()->getAuthorizationAmount(); ?></td>
			<td><a class="button orange" href="<?php echo $this->getUrl(array('transaction_id' => $transaction->getTransactionId()), 'view'); ?>"><?php echo UnzerCw_Language::_('View'); ?></a>
		</tr>
		<?php endif; ?>
	<?php endforeach;?>
</table>
<br />
<?php endif; ?>




			
