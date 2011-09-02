<a href="#" id="ajax-link">ajax</a>

<script type="text/javascript">
	$('#ajax-link').click(function(){
		$.get(href('test'), {hello: 'world'}, function(response){
			alert(response);
		});
		return false;
	});
</script>