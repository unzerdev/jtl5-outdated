{include file='layout/header.tpl'}
	<h1>{$method_name}</h1>
	{if !empty($error_message)}
		<p class="box_error">{$error_message}</p>
	{/if}
	
	{$checkout_form}
{include file='layout/footer.tpl'}