<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title><?= $this->_getHtmlTitle(); ?></title>
	<base href="<?= $this->_getHtmlBaseHref(); ?>" />
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
	<script type="text/javascript" src="http://scripts.vik-off.net/debug.js"></script>
	<script type="text/javascript">
	
		function href(href){
			return 'index.php?r=' + href;
		}
		
		$(function(){
			
			VikDebug.init();
			
			// отлов ajax-ошибок
			$.ajaxSetup({
				error: function(xhr){
					trace(xhr.responseText);
					return true;
				}
			});
		});
	
	</script>
</head>
<body>

	<?= $this->_getHtmlContent(); ?>
	
</body>
</html>
