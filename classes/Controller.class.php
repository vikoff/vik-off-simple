<?

class Controller {
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ ДЕЙСТВИЯ */
	protected function _checkAction(){
		
		if(isset($_POST['action']) && checkFormDuplication()){
			
			$action = $_POST['action'];
			$method = $this->getActionMethodName($action);
			
			if(!method_exists($this, $method))
				$this->display_404($method);
				
			if($this->$method())
				if(!empty($_POST['redirect']))
					redirect($_POST['redirect']);
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ ОТОБРАЖЕНИЯ */
	protected function _checkDisplay(){
		
		$method = $this->getDisplayMethodName($this->requestMethod);
		
		if(!method_exists($this, $method))
			$this->display_404($method);
		
		$this->$method($this->requestParams);
	}
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ AJAX */
	protected function _checkAjax(){
		
		$method = $this->getAjaxMethodName($this->requestMethod);
		
		if(!method_exists($this, $method))
			$this->display_404($method);
		
		$this->$method($this->requestParams);
	}
	
	/**
	 * ПОЛУЧИТЬ ИМЯ КЛАССА КОНТРОЛЛЕРА ПО ИДЕНТИФИКАТОРУ
	 * @param string $controllerIdentifier - идентификатор контроллера
	 * @return string|null - имя класса  контроллера или null, если контроллер не найден
	 */
	public static function getControllerClassName($controllerIdentifier){
			
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
	
	/** ПОЛУЧИТЬ ИМЯ МЕТОДА ДЕЙСТВИЯ ПО ИДЕНТИФИКАТОРУ */
	public function getActionMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'action_'.strtolower(str_replace('-', '_', $method));
		return $method;
	}
	
	/** ПОЛУЧИТЬ ИМЯ МЕТОДА ОТОБРАЖЕНИЯ ПО ИДЕНТИФИКАТОРУ */
	public  function getDisplayMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'display_'.(strlen($method) ? strtolower(str_replace('-', '_', $method)) : 'default');
		return $method;
	}
	
	/** ПОЛУЧИТЬ ИМЯ AJAX МЕТОДА ПО ИДЕНТИФИКАТОРУ */
	public function getAjaxMethodName($method){
	
		// преобразует строку вида 'any-Method-name' в 'any_method_name'
		$method = 'ajax_'.strtolower(str_replace('-', '_', $method));
		return $method;
	}
	
	public function performAction($methodIdentifier, $redirect){
				
		$method = $this->getActionMethodName($methodIdentifier);
			
		if(!method_exists($this, $method))
			throw new Exception('');
		
		if($this->$method($method, $redirect))
			if(!empty($redirect))
				redirect($redirect);
	}
	
	public function peformDispaly($methodIdentifier, $params){
				
		$method = $this->getDisplayMethodName($methodIdentifier);
				
		if(!method_exists($this, $method))
			throw new Exception('');
		
		$this->$method($params);
	}
	
	public function performAjax($method, $params){
				
		$method = $this->getAjaxMethodName($methodIdentifier);
				
		if(!method_exists($this, $method))
			throw new Exception('');
		
		$this->$method($params);
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