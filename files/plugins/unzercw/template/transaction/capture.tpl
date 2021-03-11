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
/* @var $item Customweb_Payment_Authorization_IInvoiceItem */

?>


<div class="buttons">
	<a class="btn btn-default" href="<?php echo $back; ?>" class="button blue"><?php echo UnzerCw_Language::_('Back'); ?> </a>
</div>

<?php if ($transaction->getTransactionObject()->isPartialCapturePossible()):?>
	<h3><?php echo UnzerCw_Language::_('Partial Capture'); ?></h3>
	<form action="<?php $captureConfirmUrl; ?>" method="POST" class="unzercw-line-item-grid" id="capture-form">
	
		<input type="hidden" id="unzercw-decimal-places" value="<?php echo Customweb_Util_Currency::getDecimalPlaces($transaction->getTransactionObject()->getCurrencyCode()); ?>" />
		<input type="hidden" id="unzercw-currency-code" value="<?php echo strtoupper($transaction->getTransactionObject()->getCurrencyCode()); ?>" />
		<table class="table">
			<thead>
				<tr>
					<th class="left-align"><?php echo UnzerCw_Language::_('Name'); ?></th>
					<th class="left-align"><?php echo UnzerCw_Language::_('SKU'); ?></th>
					<th class="left-align"><?php echo UnzerCw_Language::_('Type'); ?></th>
					<th class="left-align"><?php echo UnzerCw_Language::_('Tax Rate'); ?></th>
					<th class="right-align"><?php echo UnzerCw_Language::_('Quantity'); ?></th>
					<th class="right-align"><?php echo UnzerCw_Language::_('Total Amount (excl. Tax)'); ?></th>
					<th class="right-align"><?php echo UnzerCw_Language::_('Total Amount (incl. Tax)'); ?></th>
					</tr>
			</thead>
		
			<tbody>
			<?php foreach ($transaction->getTransactionObject()->getUncapturedLineItems() as $index => $item):?>
				<?php 
					$amountExcludingTax = Customweb_Util_Currency::formatAmount($item->getAmountExcludingTax(), $transaction->getTransactionObject()->getCurrencyCode());
					$amountIncludingTax = Customweb_Util_Currency::formatAmount($item->getAmountIncludingTax(), $transaction->getTransactionObject()->getCurrencyCode());
					if ($item->getType() == Customweb_Payment_Authorization_IInvoiceItem::TYPE_DISCOUNT) {
						$amountExcludingTax = $amountExcludingTax * -1;
						$amountIncludingTax = $amountIncludingTax * -1;
					}
				?>
				
				<tr id="line-item-row-<?php echo $index ?>" class="line-item-row" data-line-item-index="<?php echo $index; ?>" >
					<td class="left-align"><?php echo $item->getName(); ?></td>
					<td class="left-align"><?php echo $item->getSku();?></td>
					<td class="left-align"><?php echo $item->getType(); ?></td>
					<td class="left-align"><?php echo $item->getTaxRate();?> %<input type="hidden" class="tax-rate" value="<?php echo $item->getTaxRate(); ?>" /></td>
					<td class="right-align"><input type="text" class="line-item-quantity form-control" name="quantity[<?php echo $index;?>]" value="<?php echo $item->getQuantity(); ?>" /></td>
					<td class="right-align"><input type="text" class="line-item-price-excluding form-control" name="price_excluding[<?php echo $index;?>]" value="<?php echo $amountExcludingTax; ?>" /></td>
					<td class="right-align"><input type="text" class="line-item-price-including form-control" name="price_including[<?php echo $index;?>]" value="<?php echo $amountIncludingTax; ?>" /></td>
				</tr>
			<?php endforeach;?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="6" class="right-align"><?php echo UnzerCw_Language::_('Total Capture Amount'); ?>:</td>
					<td id="line-item-total" class="right-align">
					<?php echo Customweb_Util_Currency::formatAmount($transaction->getTransactionObject()->getCapturableAmount(), $transaction->getTransactionObject()->getCurrencyCode()); ?> 
					<?php echo strtoupper($transaction->getTransactionObject()->getCurrencyCode());?>
				</tr>
			</tfoot>
		</table>
		<?php if ($transaction->getTransactionObject()->isCaptureClosable()):?>
			<div class="closable-box">
				<label for="close-transaction"><?php echo UnzerCw_Language::_('Close transaction for further captures'); ?></label>
				<input id="close-transaction" type="checkbox" name="close" value="on" />
			</div>
		<?php endif;?>
		
		<div style="text-align: right;">
			<a onclick="$('#capture-form').submit();" class="btn btn-success"><?php echo UnzerCw_Language::_('Capture'); ?> </a>
		</div>
	</form>
<?php endif;?>

