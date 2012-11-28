<?php

/**
 * класс для работы с базой данных
 * @author Yuriy Novikov
 */
class db { 
	
	/**
	 * экземпляры класса db (один экземпляр - одно подключение)
	 * @var array()
	 */
	private static $_instances = array();
	
	
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
	 * @return DbAdapter
	 */
	public static function create($connParams, $connIdentifier = 'default') {
		
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
		$adapterClass = 'DbAdapter_'.ucfirst($adapter);

		/** @var DbAdapter $db */
		$db = new $adapterClass($connParams['host'], $connParams['user'], $connParams['pass'], $connParams['database']);
		
		if(!empty($connParams['encoding']))
			$db->setEncoding($connParams['encoding']);
		
		if(!empty($connParams['keepFileLog']))
			$db->keepFileLog($connParams['keepFileLog']);
		
		// создаем подключение с указанным идентификатором
		if(empty(self::$_instances[$connIdentifier]))
			self::$_instances[$connIdentifier] = & $db;
		else
			trigger_error('Соединение с БД с идентификатором "'.$connIdentifier.'" уже создано', E_USER_ERROR);
		
		return $db;
	}
	
	/**
	 * ПОЛУЧИТЬ ЭКЗЕМПЛЯР КЛАССА db
	 * 
	 * @param null|string $connIdentifier - идентификатор соединения с БД.
	 * 		Если не указан, возвращается дефолтное соединение.
	 * @return DbAdapter
	 */
	public static function get($connIdentifier = 'default') {

        /** @var $db DbAdapter */
		$db = isset(self::$_instances[$connIdentifier])
			? self::$_instances[$connIdentifier]
			: null;
		
		if($db === null) {
			trigger_error('Соединение с БД с '.($connIdentifier == 'default' ? 'дефолтным идентификатором' : 'идентификатором "'.$connIdentifier.'"').' не создано', E_USER_ERROR);
            exit;
        }
		
		if(!$db->isConnected())
			$db->connect();
		
		return $db;
	}

	public static function getAllConnections() {

		return self::$_instances;
	}
	
}

abstract class DbAdapter {

	const ERROR_TRIGGER  = 1;
	const ERROR_THROW    = 2;
	const ERROR_STORE    = 3;
	const ERROR_CALLBACK = 4;

	/** флаг, используется ли pdo адаптер */
	protected $_isPdo = FALSE;

	/** флаг, что соединение установлено */
	protected $_connected = FALSE;
	
	/** флаг о необходимости логирования sql в файл */
	protected $_keepFileLog = FALSE;
	
	/** время подключения к БД */
	protected $_connectTime = null;
	
	/** массив сохраненных SQL запросов */
	protected $_sqls = array();
	
	/** время выполнения каждого запроса */
	protected $_queriesTime = array();

	/** число выполненных запросов */
	protected $_queriesNum = 0;
	
	/** массив сохранения сообщений об ошибках */
	protected $_error = array();
	
	/** режим накопления сообщений об ошибках */
	protected $_errorHandlingMode = 1;
	
	/**
	 * пользовательский обработчик ошибок
	 * если null, то ошибки складываются в стандартный контейнер (_setError, hasError, getError)
	 * @var null|callable
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


	/** подключение к базе данных */
	abstract public function connect();

	/** выбрать базу данных */
	abstract public function selectDb($db);

	/** установить кодировку соединения */
	abstract public function setEncoding($encoding);

	/** получить последний вставленный primary key */
	abstract public function getLastId();

	/** получить количество строк, затронутых последней операцией */
	abstract public function getAffectedNum();

	/**
	 * выполнить запрос
	 * @param string $sql - SQL-запрос
	 * @param mixed $bind - параметры для SQL запроса
	 * @return resource|PDOStatement - объект ответа базы данных
	 */
	abstract public function query($sql, $bind = array());

	/**
	 * выполнить запрос и вернуть единственное значение (первая строка, первый столбец)
	 * @param string $sql - SQL-запрос
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|null
	 */
	abstract public function fetchOne($sql, $bind = array(), $default = null);

	/**
	 * выполнить запрос и вернуть единственное значение (первая строка, указанный индекс столбца)
	 * @param string $sql - SQL-запрос
	 * @param    int $col - индекс колонки (начиная с 0)
	 * @param  mixed $bind - параметры для SQL запроса
	 * @param  mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|null
	 */
	abstract public function fetchCell($sql, $col, $bind = array(), $default = null);

	/**
	 * выполнить запрос и вернуть единственную строку (первую)
	 * @param string $sql - SQL-запрос
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|null
	 */
	abstract public function fetchRow($sql, $bind = array(), $default = null);

	/**
	 * выполнить запрос и вернуть единственный столбец (первый)
	 * @param string $sql - SQL-запрос
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	abstract public function fetchCol($sql, $bind = array(), $default = array());

	/**
	 * возвращает одномерный ассоциативный массив.
	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД
	 * значение массива - значение второго столбца, извлекаемого из БД
	 * @param string $sql
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default
	 * @return array|$default
	 */
	abstract public function fetchPairs($sql, $bind = array(), $default = array());

	/**
	 * выполнить запрос и вернуть многомерный ассоциативный массив данных
	 * @param string $sql - SQL-запрос
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	abstract public function fetchAll($sql, $bind = array(), $default = array());

	/**
	 * выполнить запрос и вернуть многомерный индексированных ассоциативный массив данных
	 * @param string $sql - SQL-запрос
	 * @param string $index - имя поля, по которому будет индексироваться массив результатов.
	 *        Важно проследить, чтобы значение у индекса было уникальным у каждой строки,
	 *        иначе дублирующиеся строки будут затерты.
	 * @param mixed $bind - параметры для SQL запроса
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	abstract public function fetchAssoc($sql, $index, $bind = array(), $default = array());

	/**
	 * экранирование данных
	 * выполняется с учетом типа данных для предотвращения SQL-инъекций
	 * @param mixed строка для экранирования
	 * @return mixed - безопасная строка
	 */
	abstract public function escape($str);
	
	/**
	 * заключение имен полей в нужный тип ковычек
	 * метод индивидуален для каждого db-адапрета
	 * @param string $field - строка имени поля
	 * @return string заключенная в нужный тип ковычек строка
	 */
	abstract function quoteFieldName($field);

	/**
	 * получить массив, описывающий структуру таблицы
	 * @param string $table - имя таблицы
	 * @return array - структура таблицы
	 */
	abstract public function describe($table);

	/**
	 * получить список бд
	 * @return array - массив-список баз данных
	 */
	abstract public function showDatabases();

	/**
	 * получить список таблиц в текущей базе данных
	 * @return array - массив-список таблиц
	 */
	abstract public function showTables();

	/**
	 * показать строку create table
	 * @param string $table - имя таблицы
	 * @return string - строка CREATE TABLE
	 */
	abstract public function showCreateTable($table);
	
	/** конструктор */
	public function __construct($host, $user, $pass, $database) {
		
		$this->connHost = $host;
		$this->connUser = $user;
		$this->connPass = $pass;
		$this->connDatabase = $database;
	}
	
	/**
	 * Установить режим обработки ошибок адаптера
	 * @param int $mode
	 * @param null|callable $callback
	 */
	public function setErrorHandlingMode($mode, $callback = null) {
		$all = array(self::ERROR_CALLBACK, self::ERROR_STORE, self::ERROR_THROW, self::ERROR_TRIGGER);
		if (!in_array($mode, $all)) {
			trigger_error("invalid sql error handling mode '$mode'", E_USER_ERROR);
			exit;
		}

		if ($mode == self::ERROR_CALLBACK && !is_callable($callback)) {
			trigger_error("db error mode 'callback' need real callback, passed - '".gettype($callback)."'", E_USER_ERROR);
			exit;
		}

		$this->_errorHandlingMode = $mode;
		$this->_errorHandler = $mode == self::ERROR_CALLBACK ? $callback : null;
	}
	
	/** выполнено ли подключение к бд */
	public function isConnected() {
		
		return $this->_connected;
	}
	
	/** вести лог sql запросов */
	public function keepFileLog($boolEnable) {
		
		$this->_keepFileLog = $boolEnable;
	}
	
	/** получить хост бд */
	public function getConnHost() {
		return $this->connHost;
	}
	
	/** получить имя пользователя бд */
	public function getConnUser() {
		return $this->connUser;
	}
	
	/** получить пароль пользователя бд */
	public function getConnPassword() {
		return $this->connPass;
	}
	
	/** получить имя текущей бд */
	public function getDatabase() {
		return $this->connDatabase;
	}
	
	/** получить текущую кодировку соединения */
	public function getEncoding() {
		return $this->_encoding;
	}
	
	/**
	 * вставка данных в таблицу
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для вставки
	 * @return integer последний вставленный id 
	 */
	public function insert($table, $fieldsValues) {

		$fields = array();
		$values = array();
		$valuePhs = array();

		foreach($fieldsValues as $field => $value){
			$fields[] = $this->quoteFieldName($field);
			if (is_object($value)) {
				$valuePhs[] = $value;
			} else {
				$values[] = $value;
				$valuePhs[] = '?';
			}
		}

		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES ('.implode(',', $valuePhs).')';
		$this->query($sql, $values);
		return $this->getLastId();
	}

	/**
	 * вставка в таблицу нескольких строк за раз
	 * @param string $table - имя таблицы
	 * @param array $fields - массив-список полей таблиц. Например: array('field1', 'field2')
	 * @param array $valuesRows - список списков, каждый из которых содержит в себе
	 *        данные для вставки одной строки. Например: array( array(val1, val2), array(val3, val4) )
	 * @return integer колечество вставленных строк
	 */
	public function insertMulti($table, $fields, $valuesRows) {

		$rows = array();
		$values = array();
		foreach($fields as $index => $field)
			$fields[$index] = $this->quoteFieldName($field);
		foreach($valuesRows as $_rowArr){
			$rowArr = array();
			foreach($_rowArr as $cell) {
				$rowArr[] = '?';
				$values[] = $cell;
			}
			$rows[] = '('.implode(',', $rowArr).')';
		}

		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES '.implode(',', $rows);
		return $this->fetchOne($sql, $values);
	}
	
	/**
	 * обновление записей в таблице
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для обновления
	 * @param string $where - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @param mixed $bind - параметры для SQL запроса
	 * @return integer количество затронутых строк
	 */
	public function update($table, $fieldsValues, $where, $bind = array()) {

		$update_arr = array();
		$bind_arr = array();
		foreach($fieldsValues as $field => $value) {
			if (is_object($value)) {
				$update_arr[] = $this->quoteFieldName($field).'='.$value;
			} else {
				$update_arr[] = $this->quoteFieldName($field).'=?';
				$bind_arr[] = $value;
			}
		}

		$bind = $bind === null ? array(null) : (array)$bind;
		$bind_arr = array_merge($bind_arr, $bind);

		$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.$where;
		$rs = $this->query($sql, $bind_arr);
		return $this->_isPdo ? $rs->rowCount() : $this->getAffectedNum();
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
	public function updateInsert($table, $fieldsValues, $conditionFieldsValues) {
		
		$update_arr = array();
		foreach($fieldsValues as $field => $value)
			$update_arr[] = $this->quoteFieldName($field).'='.$this->qe($value);
		
		if(!is_array($conditionFieldsValues) || !count($conditionFieldsValues)) {
			$this->_error('функция updateInsert получила неверное условие conditionFieldsValues');
			return false;
		}
		$conditionArr = array();
		foreach($conditionFieldsValues as $field => $value)
			$conditionArr[] = $this->quoteFieldName($field).'='.$this->qe($value);
		
		$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.implode(' AND ',$conditionArr);
		$this->query($sql);
		
		$affected = $this->getAffectedNum();
		
		// если не было изменено ни одной строки, смотрим внимательно
		if($affected == 0) {
			// если такая запись присутствует в таблице, то все хорошо
			if($this->fetchOne('SELECT COUNT(1) FROM '.$table.' WHERE '.implode(' AND ',$conditionArr)))
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
	 * удаление записей из таблицы
	 * @param string $table - имя таблицы
	 * @param string $conditions - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @param mixed $bind - параметры для SQL запроса
	 * @return integer количество затронутых строк
	 */
	public function delete($table, $conditions, $bind = array()) {

		$sql = 'DELETE FROM '.$table.' WHERE '.$conditions;
		$this->query($sql, $bind);

		return $this->getAffectedNum();
	}

	/**
	 * @deprecated
	 * @see $this->fetchOne
	 */
	public function getOne($sql, $bind = array(), $default = null) {
		return $this->fetchOne($sql, $bind, $default);
	}

	/**
	 * @deprecated
	 * @see $this->fetchRow
	 */
	public function getRow($sql, $bind = array(), $default = null) {
		return $this->fetchRow($sql, $bind, $default);
	}

	/**
	 * @deprecated
	 * @see $this->fetchPairs
	 */
	public function getPairs($sql, $bind = array(), $default = array()) {
		return $this->fetchPairs($sql, $bind, $default);
	}

	/**
	 * @deprecated
	 * @see $this->fetchCol
	 */
	public function getCol($sql, $bind = array(), $default = array()) {
		return $this->fetchCol($sql, $bind, $default);
	}

	/**
	 * @deprecated
	 * @see $this->fetchAll
	 */
	public function getAll($sql, $bind = array(), $default = array()) {
		return $this->fetchAll($sql, $bind, $default);
	}

	/**
	 * @deprecated
	 * @see $this->fetchAssoc
	 */
	public function getAssoc($sql, $index, $bind = array(), $default = array()) {
		return $this->fetchAssoc($sql, $index, $bind, $default);
	}

	/**
	 * очистка таблицы
	 * @param string $table - имя таблицы
	 * @return void
	 */
	public function truncate($table) {

		$this->query('TRUNCATE TABLE '.$table);
	}
	
	/** НАЧАТЬ ТРАНЗАКЦИЮ */
	public function beginTransaction() {
		
		$this->query('BEGIN');
	}
	
	/** ПРИМЕНИТЬ ТРАНЗАКЦИЮ */
	public function commit() {
		
		$this->query('COMMIT');
	}
	
	/** ОТКАТИТЬ ТРАНЗАКЦИЮ */
	public function rollBack() {
		
		$this->query('ROLLBACK');
	}

	/**
	 * ЗАКЛЮЧЕНИЕ СТРОК В КОВЫЧКИ
	 * в зависимости от типа данных
	 * @param mixed $cell - исходная строка
	 * @return string заключенная в нужный тип ковычек строка
	 */
	public function quote($cell) {

		switch(strtolower(gettype($cell))) {
			case 'boolean':
				return $cell ? 'TRUE' : 'FALSE';
			case 'null':
				return 'NULL';
			case 'object':
				return $cell;
			default:
				return "'".$cell."'";
		}
	}

	/**
	 * ЭСКЕЙПИРОВАНИЕ И ЗАКЛЮЧЕНИЕ СТРОКИ В КОВЫЧКИ
	 * замена последовательному вызову функций db::escape и db::quote
	 * @param mixed $cell - исходная строка
	 * @return string эскейпированая и заключенная в нужный тип ковычек строка
	 */
	public function qe($cell) {

		return $this->quote($this->escape($cell));
	}

	/**
	 * получить строку, которая будет обработана адаптером без преобразований
	 * (без эскейпирования и заковычивания)
	 * полезно для SQL функций, например NOW()
	 * @param string $statement - SQL выражение
	 * @return DbStatement object
	 */
	public function raw($statement) {
		
		return new DbStatement($statement);
	}

	/** получить время подключения к бд */
	public function getConnectTime() {
		
		return $this->_connectTime;
	}
	
	/** получить число выполненных sql запросов */
	public function getQueriesNum() {
		
		return $this->_queriesNum;
	}
	
	/** получить выполненные sql запросы */
	public function getQueries() {
		
		return $this->_sqls;
	}
	
	/** получить общее время выполнения sql запросов */
	public function getQueriesTime() {
		
		return array_sum($this->_queriesTime);
	}
	
	/** получить выполенные запросы в виде массива (запрос => время) */
	public function getQueriesWithTime() {
		
		$output = array();
		foreach($this->_sqls as $index => $sql)
			$output[] = array(
				'sql' => $sql,
				'time' => isset($this->_queriesTime[$index]) ? $this->_queriesTime[$index] : '-'
			);
		return $output;
	}
	
	/** получить информацию о последнем запросе (sql, time) */
	public function getLastQueryInfo() {
		
		return array(
			'sql' => end($this->_sqls),
			'time' => end($this->_queriesTime)
		);
	}
	
	/**
	 * перехват ошибок выполнения sql-запросов
	 * Дальнейший путь ошибки зависит от установки _errorHandlingMode
	 * @access protected
	 * @throws ExceptionDB
	 * @param string $msg - сообщение, сгенерированное СУБД
	 * @param string $sql - SQL-запрос, в котором возникла ошибка
	 * @return void
	 */
	protected function _error($msg, $sql = '') {
		
		$fullmsg = ""
			."\n\nError on ".date('Y-m-d H:i:s')."\n"
			."[  SQL] ".str_repeat('-', 80)."\n\n"
			.$sql
			."\n\n[ERROR] ".str_repeat('-', 80)."\n\n"
			.$msg
			."\n\n--------".str_repeat('-', 80)."\n\n";

		switch ($this->_errorHandlingMode) {
			case self::ERROR_TRIGGER:
				trigger_error(PHP_SAPI == 'cli' ? $fullmsg : '<pre>'.$fullmsg.'</pre>', E_USER_ERROR);
				break;
			case self::ERROR_THROW:
				throw new ExceptionDB($fullmsg);
				break;
			case self::ERROR_STORE:
				$this->_setError($fullmsg);
				break;
			case self::ERROR_CALLBACK:
				call_user_func($this->_errorHandler, $msg, $sql, $fullmsg);
				break;
		}
	}
	
	/** сохранить ошибку */
	protected function _setError($error) {
		$this->_error[] = $error;
	}
	
	/** получить все ошибки */
	public function getError($clear = false) {

		$separator = PHP_SAPI == 'cli' ? "\n" : '<br />';
		$output = implode($separator, $this->_error);

		if ($clear) $this->resetError();

		return $output;
	}
	
	/** проверить, есть ли ошибки */
	public function hasError() {
		return !empty($this->_error);
	}
	
	/** очистить накопившиеся ошибки */
	public function resetError() {
		$this->_error = array();
	}
	
	/** загрузить дамп данных */
	public function loadDump($fileName) {
	
		if(!$fileName) {
			$this->_setError('Файл дампа не загружен');
			return FALSE;
		}
		
		if(!file_exists($fileName)) {
			$this->_setError('Файл дампа не найден');
			return FALSE;
		}
		
		$singleQuery = '';
		$numCommands = 0;

		$rs = fopen($fileName, "r");
		while(!feof($rs)) {
		
			$row = fgets($rs);
			$row = preg_replace('/;\r\n/', ";\n", $row);
			$singleQuery .= $row;
			
			if(substr($row, -2) == ";\n") {
				$singleQuery = str_replace(array('\r', '\n'), array("\r", "\n"), $singleQuery);
				$this->query($singleQuery);
				$singleQuery = '';
				$numCommands++;
			}
		}
		fclose($rs);
		return TRUE;
	}
	
	/**
	 * создать дамп базы данных
	 * @param string|null $database - база данных (или дефолтная, если null)
	 * @param array|null $tables - список таблиц (или все, если null)
	 * @output выдает текст sql-дампа
	 * @return void
	 */
	public function makeDump($database = null, $tables = null) {}
	
	/**
	 * деструкотр
	 * запись лога выполненных sql-запросов в файл (если требуется)
	 */
	public function __destruct() {
		
		if($this->_keepFileLog) {
			
			$logpath = FS_ROOT.'logs/';
			if (!is_dir($logpath))
				mkdir($logpath, 0777, true);
				
			$f = fopen($logpath.'sql.log', 'a');
			fwrite($f, '-------- '.date('Y-m-d H:i:s')." --------\n");
			fwrite($f, implode("\n", $this->_sqls));
			fwrite($f, "\n\n");
			fclose($f);
		}
	}

	/**
	 * сохранить запрос
	 * @access protected
	 */
	protected function _saveQuery($sql) {

		$this->_sqls[] = $sql;
	}

	/**
	 * сохранить время подключения к бд
	 * @access protected
	 */
	protected function _saveConnectTime($t) {

		$this->_connectTime = $t;
	}

	/**
	 * сохранить время исполнения запроса
	 * @access protected
	 */
	protected function _saveQueryTime($t) {

		$this->_queriesTime[] = $t;
	}

}

/**
 * Класс, экземпляры которого используются как части SQL выражения
 * над которыми не надо производить эскейпирование или закавычивание
 */
class DbStatement {
	
	private $_statement = '';
	
	public static function create($statement) {
		
		return new DbStatement($statement);
	}
	
	public function __construct($statement) {
		
		$this->_statement = $statement;
	}
	
	public function __toString() {
		
		return $this->_statement;
	}
}

/**
 * Класс исключений для Адаптера БД
 */
class ExceptionDB extends Exception {}
