<?

class db{ 
	
	/**
	 * экземпляры класса db (один экземпляр - одно подключение)
	 * @var array()
	 */
	private static $_instances = array();
	
	/**
	 * экземпляр дефолтного соединения с БД
	 * @var null|db
	 */
	private static $_defaultInstance = null;
	
	
	/**
	 * СОЗДАНИЕ ПОДКЛЮЧЕНИЯ К БД
	 * создает новый экземпляр соединения с бд, при этом не подключаясь к ней.
	 * @param array $connParams
	 * 		string 'adapter' optional, указывает адаптер подключения к БД
	 * 			Если не указан, используется mysql
	 *		string 'host' required
	 *		string 'user' required
	 *		string 'pass' required
	 *		string 'database' required
	 *		string 'encoding' optional, устанавливает кодировку соединения
	 * @param null|string $connIdentifier - идентификатор соединения с БД.
	 * 		Если null, создается дефолтное соединение.
	 * @return db instance
	 */
	public static function create($connParams, $connIdentifier = null){
		
		// проверка, переданы ли параметры в виде массива
		if(!is_array($connParams))
			trigger_error('Параметры соединения с БД должны быть переданы в виде массива.', E_USER_ERROR);
			
		// проверка, введены ли обязательные атрибуты
		foreach(array('host', 'user', 'pass', 'database') as $key)
			if(!array_key_exists($key, $connParams))
				trigger_error('Для подключения к БД требуется указать параметр "'.$key.'"', E_USER_ERROR);
		
		// определение адаптера. по умолчанию используется mysql
		$adapter = isset($connParams['adapter'])
			? $connParams['adapter']
			: 'mysql';

		// создание экземпляра класса db
		$adapterClass = 'DbAdapter_'.$adapter;
		$db = new $adapterClass($connParams['host'], $connParams['user'], $connParams['pass'], $connParams['database']);
		
		if(!empty($connParams['encoding']))
			$db->setEncoding($connParams['encoding']);
		
		if(!empty($connParams['keepFileLog']))
			$db->keepFileLog($connParams['keepFileLog']);
		
		// если идентификатор соединения не передан
		// создаем дефолтное подключение
		if(is_null($connIdentifier)){
			
			if(is_null(self::$_defaultInstance))
				self::$_defaultInstance = & $db;
			else
				trigger_error('Соединение с БД с дефолтным идентификатором уже создано', E_USER_ERROR);
		}
		// если идентификатор соединения указан
		// создаем подключение с указанным идентификатором
		else{
			
			if(strlen($connIdentifier)){
				if(empty(self::$_instances[$connIdentifier]))
					self::$_instances[$connIdentifier] = & $db;
				else
					trigger_error('Соединение с БД с идентификатором "'.$connIdentifier.'" уже создано', E_USER_ERROR);
			}else{
				trigger_error('Идентификатор соединения с БД должен быть числом, строкой или значением null', E_USER_ERROR);
			}
		}
		
		return $db;
	}
	
	/**
	 * ПОЛУЧИТЬ ЭКЗЕМПЛЯР КЛАССА db
	 * 
	 * @param null|string $connIdentifier - идентификатор соединения с БД.
	 * 		Если не указан, возвращается дефолтное соединение.
	 * @return instance of db
	 */
	public static function get($connIdentifier = null){
		
		$db = is_null($connIdentifier)
			? self::$_defaultInstance
			: (isset(self::$_instances[$connIdentifier])
				? self::$_instances[$connIdentifier]
				: null);
		
		if(is_null($db))
			trigger_error('Соединение с БД с '.(is_null($connIdentifier) ? 'дефолтным идентификатором' : 'идентификатором "'.$connIdentifier.'"').' не создано', E_USER_ERROR);
		
		if(!$db->isConnected())
			$db->connect();
		
		return $db;
	}
	
}

abstract class DbAdapter{
	
	/** флаг, что соединение установлено */
	protected $_connected = FALSE;
	
	/** флаг о необходимости логирования sql в файл */
	protected $_keepFileLog = FALSE;
	
	/** массив сохраненных SQL запросов */
	protected $_sqls = array();
	
	/** время выполнения каждого запроса */
	protected $_queriesTime = array();

	/** число выполненных запросов */
	protected $_queriesNum = 0;
	
	/** массив сохранения сообщений об ошибках */
	protected $_error = array();
	
	/** режим накопления сообщений об ошибках */
	protected $_errorHandlingMode = FALSE;
	
	/**
	 * пользовательский обработчик ошибок
	 * если null, то ошибки складываются в стандартный контейнер (setError, hasError, getError)
	 * @var null|callback
	 */
	private $_errorHandler = null;
	
	/** ресурс соединения с базой данных */
	protected $_dbrs = null;
	
	// параметры подключения к БД
	protected $connHost = '';
	protected $connUser = '';
	protected $connPass = '';
	protected $connDatabase = '';
	
	/** кодировка соединения */
	protected $_encoding = null;
	

	abstract public function connect();
	abstract public function setEncoding($encoding);
	abstract public function getLastId();
	abstract public function getAffectedNum();
	abstract public function query($query);
	abstract public function getOne($query, $default_value = null);
	abstract public function getCell($query, $row, $column, $default_value = 0);
	abstract public function getCol($query, $default_value = array());
	abstract public function getColIndexed($query, $default_value = 0);
	abstract public function getRow($query, $default_value = array());
	abstract public function getAll($query, $default_value = array());
	abstract public function getAllIndexed($query, $index, $default_value = 0);
	abstract public function escape($str);
	
	/**
	 * ЗАКЛЮЧЕНИЕ ИМЕН ПОЛЕЙ В НУЖНЫЙ ТИП КОВЫЧЕК
	 * метод индивидуален для каждого db-адапрета
	 * @param variant $field - строка имени поля
	 * @return string заключенная в нужный тип ковычек строка
	 */
	public function quoteFieldName($field){}
	abstract public function describe($table);
	abstract public function showTables();
	abstract public function showCreateTable($table);
	
	/** КОНСТРУКТОР */
	public function __construct($host, $user, $pass, $database){
		
		$this->connHost = $host;
		$this->connUser = $user;
		$this->connPass = $pass;
		$this->connDatabase = $database;
	}
	
	/** ВКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК */
	public function enableErrorHandlingMode(){
		$this->_errorHandlingMode = TRUE;
	}
	
	/** ОТКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК */
	public function disableErrorHandlingMode(){
		$this->_errorHandlingMode = TRUE;
	}
	
	/**
	 * УСТАНОВИТЬ ОБРАБОТЧИК ОШИБОК
	 * @param null|callback $handler - функция обработки ошибок
	 * @return void
	 */
	public function setErrorHandler($handler){
		$this->_errorHandlingMode = !is_null($handler);
		$this->_errorHandler = $handler;
	}
	
	/** ВЫПОЛНЕНО ЛИ ПОДКЛЮЧЕНИЕ К БД */
	public function isConnected(){
		
		return $this->_connected;
	}
	
	/** ВЕСТИ ЛОГ SQL ЗАПРОСОВ */
	public function keepFileLog($boolEnable){
		
		$this->_keepFileLog = $boolEnable;
	}
	
	/** ПОЛУЧИТЬ ХОСТ БД */
	public function getConnHost(){
		return $this->connHost;
	}
	
	/** ПОЛУЧИТЬ ИМЯ ПОЛЬЗОВАТЕЛЯ БД */
	public function getConnUser(){
		return $this->connUser;
	}
	
	/** ПОЛУЧИТЬ ПАРОЛЬ ПОЛЬЗОВАТЕЛЯ БД */
	public function getConnPassword(){
		return $this->connPass;
	}
	
	/** ПОЛУЧИТЬ ИМЯ ТЕКУЩЕЙ БД */
	public function getDatabase(){
		return $this->connDatabase;
	}
	
	/** ПОЛУЧИТЬ ТЕКУЩУЮ КОДИРОВКУ СОЕДИНЕНИЯ */
	public function getEncoding(){
		return $this->_encoding;
	}
	
	/**
	 * INSERT
	 * вставка данных в таблицу
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для вставки
	 * @return integer последний вставленный id 
	 */
	public function insert($table, $fieldsValues){
		
		$fields = array();
		$values = array();
		
		foreach($fieldsValues as $field => $value){
			$fields[] = $this->quoteFieldName($field);
			$values[] = $this->qe($value);
		}
		
		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
		$this->query($sql);
		return $this->getLastId();
	}

	/**
	 * INSERT MULTI
	 * вставка в таблицу нескольких строк за раз
	 * @param string $table - имя таблицы
	 * @param array $fields - массив-список полей таблиц. Например: array('field1', 'field2')
	 * @param array $valuesArrArr - список списков, каждый из которых содержит в себе
	 *        данные для вставки одной строки. Например: array( array(val1, val2), array(val3, val4) )
	 * @return integer колечество вставленных строк
	 */
	public function insertMulti($table, $fields, $valuesArrArr){
		
		$valuesArrStr = array();
		foreach($fields as &$field)
			$field = $this->quoteFieldName($field);
		foreach($valuesArrArr as $_rowArr){
			$rowArr = array();
			foreach($_rowArr as $cell)
				$rowArr[] = $this->qe($cell);
			$valuesArrStr[] = '('.implode(',', $rowArr).')';
		}

		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES '.implode(',', $valuesArrStr);
		return $this->getOne($sql);
	}
	
	/**
	 * UPDATE
	 * обновление записей в таблице
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для обновления
	 * @param string $conditions - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @return integer количество затронутых строк
	 */
	public function update($table, $fieldsValues, $conditions) {
		
		$update_arr = array();
		foreach($fieldsValues as $field => $value)
			$update_arr[] = $this->quoteFieldName($field).'='.$this->qe($value);
		$update_str = implode(',',$update_arr);
		
		$conditions = trim(str_replace('WHERE', '', $conditions));
		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';
	
		if(!strlen($conditions))
			trigger_error('Функции update не передано условие', E_USER_ERROR);
		
		$sql = 'UPDATE '.$table.' SET '.$update_str.$conditions;
		$this->query($sql);
		return $this->getAffectedNum();
	}

	/**
	 * UPDATE INSERT
	 * обновляет информацию в таблице.
	 * Если не было обновлено ни одной строки, создает новую строку.
	 * Возвращает 0, если было произведено обновление существующей строки,
	 * Возвращает id, если была произведена вставка новой строки
	 * ВАЖНО: при создании новой строки, функция заполнит ее данными из $fieldsValues и $conditionArr
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - поля для обновление
	 * @param array $conditionFieldsValues - поля, задающие условие обновления
	 * @return integer количество затронутых строк
	 */
	public function updateInsert($table, $fieldsValues, $conditionFieldsValues){
		
		$update_arr = array();
		foreach($fieldsValues as $field => $value)
			$update_arr[] = $this->quoteFieldName($field).'='.$this->qe($value);
		
		if(!is_array($conditionFieldsValues) || !count($conditionFieldsValues)){
			$this->error('функция updateInsert получила неверное условие conditionFieldsValues');
			return false;
		}
		$conditionArr = array();
		foreach($conditionFieldsValues as $field => $value)
			$conditionArr[] = $this->quoteFieldName($field).'='.$this->qe($value);
		
		$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.implode(' AND ',$conditionArr);
		$this->query($sql);
		
		$affected = $this->getAffectedNum();
		
		// если не было изменено ни одной строки, смотрим внимательно
		if($affected == 0){
			// если такая запись присутствует в таблице, то все хорошо
			if($this->getOne('SELECT COUNT(1) FROM '.$table.' WHERE '.implode(' AND ',$conditionArr), 0))
				return 0;
			// если же нет, то создаем ее
			else
				return $this->insert($table, array_merge($fieldsValues, $conditionFieldsValues));
		}
		// если строки были изменены, значит такая запись уже присутствует в таблице
		else{
			return 0;
		}
	}
	
	/**
	 * DELETE
	 * обновление записей в таблице
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для обновления
	 * @param string $conditions - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @return integer количество затронутых строк
	 */
	public function delete($table, $conditions) {
		
		$conditions = trim(str_replace('WHERE', '', $conditions));
		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';
	
		if(!strlen($conditions))
			trigger_error('Функции delete не передано условие. Необходимо использовать truncate', E_USER_ERROR);
		
		$sql = 'DELETE FROM '.$table.$conditions;
		$this->query($sql);

		return $this->getAffectedNum();
	}
	
	/**
	 * ЗАКЛЮЧЕНИЕ СТРОК В КОВЫЧКИ
	 * в зависимости от типа данных
	 * @param variant $cell - исходная строка
	 * @return string заключенная в нужный тип ковычек строка
	 */
	public function quote($cell){
		
		switch(strtolower(gettype($cell))){
			case 'boolean':
				return $cell ? 'TRUE' : 'FALSE';
			case 'null':
				return 'NULL';
			default:
				return "'".$cell."'";
		}
	}
	
	/**
	 * ЭСКЕЙПИРОВАНИЕ И ЗАКЛЮЧЕНИЕ СТРОКИ В КОВЫЧКИ
	 * замена последовательному вызову функций db::escape и db::quote
	 * @param variant $cell - исходная строка
	 * @return string эскейпированая и заключенная в нужный тип ковычек строка
	 */
	public function qe($cell){
		
		return $this->quote($this->escape($cell));
	}
	
	/** 
	 * СОХРАНИТЬ ЗАПРОС
	 * @access protected
	 */
	protected function _saveQuery($sql){
		
		$this->_sqls[] = $sql;
	}
	
	/** 
	 * СОХРАНИТЬ ВРЕМЯ ИСПОЛНЕНИЯ ЗАПРОСА
	 * @access protected
	 */
	protected function _saveQueryTime($t){
	
		$this->_queriesTime[] = $t;
	}
	
	/** ПОЛУЧИТЬ ЧИСЛО ВЫПОЛНЕННЫХ SQL ЗАПРОСОВ */
	public function getQueriesNum(){
		
		return $this->_queriesNum;
	}
	
	/** ПОЛУЧИТЬ ВЫПОЛНЕННЫЕ SQL ЗАПРОСЫ */
	public function getQueries(){
		
		return $this->_sqls;
	}
	
	/** ПОЛУЧИТЬ ОБЩЕЕ ВРЕМЯ ВЫПОЛНЕНИЯ SQL ЗАПРОСОВ */
	public function getQueriesTime(){
		
		return array_sum($this->_queriesTime);
	}
	
	/** ПОЛУЧИТЬ ВЫПОЛЕННЫЕ ЗАПРОСЫ В ВИДЕ МАССИВА (ЗАПРОС => ВРЕМЯ) */
	public function getQueriesWithTime(){
		
		$output = array();
		foreach($this->_sqls as $index => $sql)
			$output[] = array(
				'sql' => $sql,
				'time' => isset($this->_queriesTime[$index]) ? $this->_queriesTime[$index] : '-'
			);
		return $output;
	}
	
	/**
	 * ПЕРЕХВАТ ОШИБОК ВЫПОЛНЕНИЯ SQL-ЗАПРОСОВ
	 * Дальнейший путь ошибки зависит от установки _errorHandlingMode
	 * @access protected
	 * @param string $msg - сообщение, сгенерированное СУБД
	 * @param string $sql - SQL-запрос, в котором возникла ошибка
	 * @return void
	 */
	protected function error($msg, $sql = ''){
	
		$fullmsg = ""
			."\n\nError on ".date('Y-m-d H:i:s')."\n"
			."[  SQL] ".str_repeat('-', 80)."\n\n"
			.$sql
			."\n\n[ERROR] ".str_repeat('-', 80)."\n\n"
			.$msg
			."\n\n--------".str_repeat('-', 80)."\n\n";
		
		// улавливание ошибок
		if($this->_errorHandlingMode){
			
			if(!is_null($this->_errorHandler))
				call_user_func($this->_errorHandler, $msg, $sql, $fullmsg);
			else
				$this->setError($fullmsg);
			
		}
		// выброс ошибок
		else{
		
			if(PHP_SAPI != 'cli')
				$fullmsg = '<pre>'.$fullmsg.'</pre>';
			
			trigger_error($fullmsg, E_USER_ERROR);
		}
	}
	
	/** СОХРАНИТЬ ОШИБКУ */
	public function setError($error){
		$this->_error[] = $error;
	}
	
	/** ПОЛУЧИТЬ ВСЕ ОШИБКИ */
	public function getError(){
		return implode('<br />', $this->_error);
	}
	
	/** ПРОВЕРИТЬ, ЕСТЬ ЛИ ОШИБКИ */
	public function hasError(){
		return !empty($this->_error);
	}
	
	/** ОЧИСТИТЬ НАКОПИВШИЕСЯ ОШИБКИ */
	public function resetError(){
		$this->_error = array();
	}
	
	/** ЗАГРУЗИТЬ ДАМП ДАННЫХ */
	public function loadDump($fileName){
	
		if(!$fileName){
			$this->setError('Файл дампа не загружен');
			return FALSE;
		}
		
		if(!file_exists($fileName)){
			$this->setError('Файл дампа не найден');
			return FALSE;
		}
		
		$singleQuery = '';
		$numCommands = 0;
		$completeCommands = 0;
		$failedCommands = 0;
		
		$rs = fopen($fileName, "r");
		while(!feof($rs)){
		
			$row = fgets($rs);
			$singleQuery .= $row;
			
			if(substr($row, -2) == ";\n"){
				try{
					$this->query($singleQuery);
					$completeCommands++;
				}
				catch(Exception $e){
					echo 'error: '.$e->getMessage().'<br />';
					$failedCommands++;
				}
				$singleQuery = '';
				$numCommands++;
			}
		}
		fclose($rs);
		return TRUE;
	}
	
	/**
	 * ДЕСТРУКОТР
	 * запись лога выполненных sql-запросов в файл (если требуется)
	 */
	public function __destruct(){
		
		if($this->_keepFileLog){
			$f = fopen(FS_ROOT.'logs/mysql.log', 'a');
			fwrite($f, '-------- '.date('Y-m-d H:i:s')." --------\n");
			fwrite($f, implode("\n", $this->_sqls));
			fwrite($f, "\n\n");
			fclose($f);
		}
	}

}

?>