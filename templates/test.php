

<form id="form" action="" method="post">
	<input type="hidden" name="action" value="test" />
	<?= FORMCODE; ?>
	
	<input type="text" name="test" value="" />
	<input type="submit" value="Отправить" />

</form>

<script>
	$(function(){
		$('#form').submit(function(){
			$.post(href(''), $(this).serializeArray(), function(response){
				alert(response);
			});
			return false;
		});
	});
</script>