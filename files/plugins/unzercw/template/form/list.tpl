<?php 

/* @var $form Customweb_Payment_BackendOperation_IForm */
/* @var $this UnzerCw_Controller_Form */

?>

<?php foreach ($forms as $form): ?>
	<a class="btn btn-default" href="<?php echo $this->getUrl(array('form' => $form->getMachineName()), 'view');?>#backend-forms"><?php ?><?php echo $form->getTitle(); ?></a>
<?php endforeach;?>
