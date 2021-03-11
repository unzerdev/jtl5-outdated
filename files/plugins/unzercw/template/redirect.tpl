{include file='layout/header.tpl'}
	<h1>{$method_name}</h1>
	
	<form action="{$form_target_url}" name="redirect_form" method="POST">
		{$hidden_fields}
		<div class="buttons  unzercw-redirect-buttons">
			<input type="submit" value="{$button_continue}" class="submit" />
		</div>
	</form>
	<script type="text/javascript"> 
	{literal}
	jQuery(document).ready(function() {
		document.redirect_form.submit(); 
	});
	{/literal}
	</script>
{include file='layout/footer.tpl'}
