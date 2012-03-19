<a href="#" id="ajax-link">ajax</a>

<script type="text/javascript">
	$('#ajax-link').click(function(){
		$.get(href('ajax'), {hello: 'world'}, function(response){
			alert(response);
		});
		return false;
	});
</script>