<?

class FrontController extends Controller{
	
	private static $_instance = null;
	
	public $requestMethod = null;
	public $requestParams = array();
	
	/** контейнер обмена данными между методами */
	public $data = array();
	
	
	/** ПОЛУЧЕНИЕ ЭКЗЕМПЛЯРА FrontController */
	public static function get(){
		
		if(is_null(self::$_instance))
			self::$_instance = new FrontController();
		
		return self::$_instance;
	}
	
	/**
	 * КОНСТРУКТОР
	 * Приватный. Доступ к объекту осуществляется через статический метод self::get()
	 * Выполняет примитивную авторизацию пользователя.
	 * Парсит полученную query string.
	 */
	private function __construct(){
		
		// авторизация
		$this->_checkAuth();
		
		// парсинг запроса
		$request = explode('/', getVar($_GET['r']));
		$_rMethod = array_shift($request);
		
		$this->requestMethod = !empty($_rMethod) ? $_rMethod : 'index';
		$this->requestParams = $request;
	}
	
	/** ЗАПУСК ПРИЛОЖЕНИЯ */
	public function run(){
		
		$this->_checkAction();
		
		if($this->_checkDisplay())
			exit;
		
		$this->display_404();
	}
	
	/** ЗАПУСК ПРИЛОЖЕНИЯ В AJAX-РЕЖИМЕ */
	public function ajax(){
		
		if($this->_checkAction())
			exit;
			
		if($this->_checkAjax())
			exit;
		
		if($this->_checkDisplay())
			exit;
		
		$this->display_404();
	}
	
	/** ПРОВЕРКА АВТОРИЗАЦИИ */
	private function _checkAuth(){
		
		if(getVar($_POST['action']) == 'login')
			$this->action_login();
		
		// if(empty($_SESSION['logged']))
			// $this->display_login();
	}
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ ДЕЙСТВИЯ */
	private function _checkAction(){
		
		if(!isset($_POST['action']) || !checkFormDuplication())
			return FALSE;
		
		$action = $_POST['action'];
		
		// если action вида 'controller/action'
		if(strpos($action, '/')){
			
			list($controller, $action) = explode('/', $action);
			$controllerClass = $this->getControllerClassName($controller);
			
			if(empty($controllerClass)){
				$this->display_404('action '.$controllerClass.'/'.$action.' not found');
				exit;
			}
			
			$instance = new $controllerClass();
			return $instance->action($action, getVar($_POST['redirect']));
		}
		// если action вида 'action'
		else{
			return $this->action($action, getVar($_POST['redirect']));
		}
	}
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ ОТОБРАЖЕНИЯ */
	private function _checkDisplay(){
		
		return $this->display($this->requestMethod, $this->requestParams);
	}
	
	/** ПРОВЕРКА НЕОБХОДИМОСТИ ВЫПОЛНЕНИЯ AJAX */
	private function _checkAjax(){
		
		return $this->ajax($this->requestMethod, $this->requestParams);
	}
	
	
	
	/////////////////////
	////// DISPLAY //////
	/////////////////////
	
	public function display_index(){
		
		Layout::get()
			->setContentPhpFile('index.php')
			->render();
	}
	
	public function display_docs($params = array()){
		
		$page = getVar($params[0], 'index');
		
		if(!preg_match('/^[\w\-]+$/', $page))
			$this->display_404();
		
		$page = FS_ROOT.'templates/docs/'.$page.'.php';
		
		if(!file_exists($page))
			$this->display_404();
		
		$variables = array(
			'page' => $page,
		);
		
		Layout::get()
			->setTitle('Документация к vik-off simple')
			->setContentPhpFile('docs.php', $variables)
			->render();
	}
	
	public function display_test_db(){
			
			$dbs = db::get()->showDatabases();
			$tables = db::get()->showTables();
			
			$content = ''
				.'<h1>Базы данных</h1><pre>'.print_r($dbs, 1).'</pre>'
				.'<h1>Таблицы текущей БД</h1><pre>'.print_r($tables, 1).'</pre>';
				
			Layout::get()
				->setTitle('Тест базы данных')
				->setContent($content)
				->render();
	}
	
	public function display_tabs(){
		
		$layout = Layout::get();
		
		$variables = array(
			'tab_1' => $layout->getContentPhpFile('tabs/tab1.php', array('param' => 'ololo')),
			'tab_2' => $layout->getContentHtmlFile('tabs/tab2.html'),
		);
		
		Layout::get()
			->setTitle('Тест')
			->setContentPhpFile('test.php', $variables)
			->render();
	}
	
	public function display_ajax(){
				
			Layout::get()
				->setContentPhpFile('ajax.php')
				->render();
	}
	
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
	
	
	////////////////////
	////// ACTION //////
	////////////////////
	
	public function action_login(){
		
		if (getVar($_POST['login']) == 'abc' &&
			getVar($_POST['pass']) == '123'
		){
			$_SESSION['logged'] = 1;
			reload();
		}
	}
	
	public function action_logout(){
		
		$_SESSION['logged'] = 0;
		reload();
	}
	
	public function action_test(){
		
		echo '<pre>'; print_r($_POST);
	}

	////////////////////
	//////  AJAX  //////
	////////////////////
	
	public function ajax_test(){
		
		print_r($_GET);
	}

	////////////////////
	//////  MODEL  /////
	////////////////////
	
}

?>