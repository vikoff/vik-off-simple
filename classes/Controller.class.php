<?

class Controller {
	
	/** выполнение действия */
	public function action($methodIdentifier, $redirect){
				
		$method = $this->getActionMethodName($methodIdentifier);
			
		if(!method_exists($this, $method)){
			$this->display_404();
			exit;
		}
		
		if($this->$method($method, $redirect))
			if(!empty($redirect))
				redirect($redirect);
		
		return TRUE;
	}
	
	/** выполнение отображения */
	public function display($methodIdentifier, $params){
				
		$method = $this->getDisplayMethodName($methodIdentifier);
			
		if(!method_exists($this, $method))
			return FALSE;
		
		$this->$method($params);
		return TRUE;
	}
	
	/** выполнение ajax */
	public function ajax($methodIdentifier, $params){
				
		$method = $this->getAjaxMethodName($methodIdentifier);
			
		if(!method_exists($this, $method))
			return FALSE;
		
		$this->$method($params);
		return TRUE;
	}
	
	/** выполнение ajax */
	public function cli($methodIdentifier, $params){
		$method = $this->getCliMethodName($methodIdentifier);
			
		if(!method_exists($this, $method))
			return FALSE;
		
		$this->$method($params);
		return TRUE;
	}
	
	/**
	 * получить имя класса контроллера по идентификатору
	 * @param string $controllerIdentifier - идентификатор контроллера
	 * @return string|null - имя класса  контроллера или null, если контроллер не найден
	 */
	public function getControllerClassName($controllerIdentifier){
			
		// если идентификатор контроллера не передан, вернем null
		if(empty($controllerIdentifier))
			return null;
		
		// если идентификатор контроллера содержит недопустимые символы, вернем null
		if(!preg_match('/^[\w\-]$/', $controllerIdentifier))
			return null;
			
		// преобразует строку вида 'any-class-name' в 'AnyClassNameController'
		$controller = str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($controllerIdentifier)))).'Controller';
		return class_exists($controller) ? $controller : null;
	}
	
	/** получить имя метода действия по идентификатору */
	public function getActionMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'action_'.strtolower(str_replace('-', '_', $method));
		return $method;
	}
	
	/** получить имя метода отображения по идентификатору */
	public  function getDisplayMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'display_'.(strlen($method) ? strtolower(str_replace('-', '_', $method)) : 'default');
		return $method;
	}
	
	/** получить имя ajax метода по идентификатору */
	public function getAjaxMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'ajax_'.strtolower(str_replace('-', '_', $method));
		return $method;
	}
	
	/** получить имя ajax метода по идентификатору */
	public function getCliMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'cli_'.strtolower(str_replace('-', '_', $method));
		return $method;
	}
	
	
	/////////////////////
	////// DISPLAY //////
	/////////////////////
	
	public function display_404($method = ''){
		
		if(AJAX_MODE){
			echo 'Страница не найдена ('.$method.')';
		}else{
			Layout::get()
				->setContent('<h1 style="text-align: center;">Страница не найдена</h1> ('.$method.')')
				->render();
		}
		exit;
	}
	
}

?>