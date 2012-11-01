<?

class DbAdapter_pdo_sqlite extends DbAdapter{
	
	/** ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ */
	public function connect(){
		
		$start = microtime(1);
		try {
			$this->_dbrs = new PDO('sqlite:'.$this->connDatabase);
			$this->_connected = TRUE;
		} catch (PDOException $e) {
			$this->error('Невозможно подключиться к базе данных: '.$e->getMessage());
		}
		$this->_saveConnectTime(microtime(1) - $start);
	}

	/** УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ */
	public function setEncoding($encoding){}
	
	/** ВЫБРАТЬ БАЗУ ДАННЫХ */
	public function selectDb($db){}
	
	/** ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY */
	public function getLastId(){
	
		return $this->_dbrs->lastInsertId();
	}
	
	/** ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ */
	public function getAffectedNum(){
		
		trigger_error('function is not available in PDO', E_USER_ERROR);
	}

	/**
	 * ВЫПОЛНИТЬ ЗАПРОС
	 * @param string $query - SQL-запрос
	 * @return resource - ресурс ответа базы данных
	 */
	public function query($sql, $bind = array()){
		
		$bind = (array)$bind;
		$this->_saveQuery($sql.($bind ? '; BIND ['.implode('; ', $bind).']' : ''));
		$this->_queriesNum++;
		
		$start = microtime(1);
		$stmt = $this->_dbrs->prepare($sql) or $this->error($this->_dbrs->errorInfo(), $sql);
		$stmt->execute($bind) or $this->error($stmt->errorInfo(), $sql);
		$this->_saveQueryTime(microtime(1) - $start);
		
		return $stmt;
	}
	
	/**
	 * GET ONE
	 * выполнить запрос и вернуть единственное значение (первая строка, первый столбец)
	 * @param string $sql - SQL-запрос
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|$default
	 */
	public function getOne($sql, $default = null){
		
		$data = $this->query($sql)->fetchColumn();
		return $data !== FALSE ? $data : $default;
	}
	
	/**
	 * GET COL
	 * выполнить запрос и вернуть единственный столбец (первый)
	 * @param string $sql - SQL-запрос
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	public function getCol($sql, $default = array()){
		
		$data = $this->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
		return $data ? $data : $default;
	}
	
	/**
	 * GET COL INDEXED
	 * возвращает одномерный ассоциативный массив.
	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД
	 * значение массива - значение второго столбца, извлекаемого из БД
	 * @param string $sql
	 * @param mixed $default
	 * @return array|$default
	 */
	public function getColIndexed($sql, $default = array()){
		
		$rs = $this->query($sql);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_NUM); $data[ $row[0] ] = $row[1]);
		
		return $data ? $data : $default;
	}

	/**
	 * GET ROW
	 * выполнить запрос и вернуть единственную строку (первую)
	 * @param string $sql - SQL-запрос
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	public function getRow($sql, $default = null){
		
		$data = $this->query($sql)->fetch(PDO::FETCH_ASSOC);
		return $data ? $data : $default;
	}
	
	/**
	 * GET ALL
	 * выполнить запрос и вернуть многомерный ассоциативный массив данных
	 * @param string $sql - SQL-запрос
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	public function getAll($sql, $default = array()){

		$data = $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		return $data !== FALSE ? $data : $default;
	}
	
	/**
	 * GET ALL INDEXED
	 * выполнить запрос и вернуть многомерный индексированных ассоциативный массив данных
	 * @param string $sql - SQL-запрос
	 * @param string $index - имя поля, по которому будет индексироваться массив результатов.
	 *        Важно проследить, чтобы значение у индекса было уникальным у каждой строки,
	 *        иначе дублирующиеся строки будут затерты.
	 * @param mixed $default - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default
	 */
	public function getAllIndexed($sql, $index, $default = array()){
		
		$rs = $this->query($sql);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_ASSOC); $data[ $row[$index] ] = $row);
		
		return $data ? $data : $default;
	}
	
	public function fetchOne($sql, $bind = array(), $default = null){
		
		$data = $this->query($sql, $bind)->fetchColumn();
		return $data !== FALSE ? $data : $default;
	}
	
	public function fetchRow($sql, $bind = array(), $default = null){
		
		$data = $this->query($sql, $bind)->fetch(PDO::FETCH_ASSOC);
		return $data ? $data : $default;
	}
	
	public function fetchPairs($sql, $bind = array(), $default = array()){
		
		$rs = $this->query($sql, $bind);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_NUM); $data[ $row[0] ] = $row[1]);
		
		return $data ? $data : $default;
	}
	
	public function fetchCol($sql, $bind = array(), $default = array()){
		
		$data = $this->query($sql, $bind)->fetchAll(PDO::FETCH_COLUMN, 0);
		return $data ? $data : $default;
	}
	
	public function fetchAssoc($sql, $bind = array(), $key = 'id', $default = array()){
		
		$rs = $this->query($sql, $bind);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_ASSOC); $data[ $row[$key] ] = $row);
		
		return $data ? $data : $default;
	}
	
	public function fetchAll($sql, $bind = array(), $default = array()){
		
		$data = $this->query($sql, $bind)->fetchAll(PDO::FETCH_ASSOC);
		return $data !== FALSE ? $data : $default;
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
	 * UPDATE
	 * обновление записей в таблице
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для обновления
	 * @param string $where - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @return integer количество затронутых строк
	 */
	public function update($table, $fieldsValues, $where) {
		
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
	
		if(!strlen($where))
			trigger_error('Функции update не передано условие', E_USER_ERROR);
		
		$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.$where;
		$rs = $this->query($sql, $bind_arr);
		return $rs->rowCount();
	}
	
	/**
	 * DELETE
	 * удаление записей из таблицы
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для обновления
	 * @param string $where - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @return integer количество затронутых строк
	 */
	public function delete($table, $where) {
	
		if(!strlen($where))
			trigger_error('Функции delete не передано условие. Необходимо использовать truncate', E_USER_ERROR);
		
		$sql = 'DELETE FROM '.$table.' WHERE '.$where;
		$rs = $this->query($sql);

		return $rs->rowCount();
	}
	
	/**
	 * TRUNCATE
	 * очистка таблицы
	 * @param string $table - имя таблицы
	 * @return void
	 */
	public function truncate($table){
		
		$this->query('DELETE FROM '.$table);
	}
	
	/**
	 * ЭСКЕЙПИРОВАНИЕ И ЗАКЛЮЧЕНИЕ СТРОКИ В КОВЫЧКИ
	 * замена последовательному вызову функций db::escape и db::quote
	 * @param variant $cell - исходная строка
	 * @return string эскейпированая и заключенная в нужный тип ковычек строка
	 */
	public function qe($cell){
		
		return $this->quote($cell);
	}
	
	/**
	 * ЭКРАНИРОВАНИЕ ДАННЫХ
	 * выполняется с учетом типа данных для предотвращения SQL-инъекций
	 * @param mixed строка для экранирования
	 * @param mixed - безопасная строка
	 */
	public function escape($str){
		
		return is_string($str)
			? $this->_dbrs->quote($str)
			: $str;
	}
	
	/**
	 * ЗАКЛЮЧЕНИЕ ИМЕНИ ПОЛЯ В КАВЫЧКИ
	 * для полей, имена которых совпадают с ключевыми словами
	 * @param string $fieldname - имя поля
	 * @param string - имя поля, заключенное в кавычки
	 */
	public function quoteFieldName($field){
		return '"'.$field.'"';
	}
	
	/**
	 * ЗАКЛЮЧЕНИЕ СТРОК В КОВЫЧКИ И ЭКРАНИРОВАНИЕ
	 * в зависимости от типа данных
	 * @override DbAdapter method
	 * @param variant $cell - исходная строка
	 * @return string заключенная в нужный тип ковычек строка
	 */
	public function quote($cell){
		
		switch(strtolower(gettype($cell))){
			case 'boolean':
				return $cell ? '1' : '0';
			case 'null':
				return 'NULL';
			case 'string':
				return $this->_dbrs->quote($cell);
			default:
				return $cell;
		}
	}
	
	/**
	 * DESCRIBE
	 * получить массив, описывающий структуру таблицы
	 * @param string $table - имя таблицы
	 * @return array - структура таблицы
	 */
	public function describe($table){
		
		return $this->getAll('PRAGMA table_info('.$table.')');
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК ТАБЛИЦ
	 * в текущей базе данных
	 * @return array - массив-список таблиц
	 */
	public function showTables(){
	
		return $this->fetchCol('SELECT name FROM sqlite_master WHERE type = "table"');
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК БД
	 * @return array - массив-список баз данных
	 */
	public function showDatabases(){
	
		return array($this->connDatabase);
	}
	
	/**
	 * ПОКАЗАТЬ СТРОКУ CREATE TABLE
	 * @param string $table - имя таблицы
	 * @return string - строка CREATE TABLE
	 */
	public function showCreateTable($table){
	
		return $this->getOne('SELECT sql FROM sqlite_master WHERE type = "table" AND name= "'.$table.'"');
	}
	
	protected function error($msg, $sql = ''){
		
		parent::error(is_array($msg) ? implode('; ', $msg) : $msg, $sql);
	}
	
	/**
	 * СОЗДАТЬ ДАМП БАЗЫ ДАННЫХ
	 * @param string|null $database - база данных (или дефолтная, если null)
	 * @param array|null $tables - список таблиц (или все, если null)
	 * @output выдает текст sql-дампа
	 * @return void
	 */
	public function makeDump($database = null, $tables = null){

		$lf = "\n";
		$cmnt = '--';
		$createtable = array();
		
		if(!is_null($database))
			$this->selectDb($database);
			
		if(is_null($tables))
			$tables = $this->showTables();

		// get 'table create' parts for all tables
		foreach ($tables as $table){
			$createtable[$table] = $this->showCreateTable($table);
		}
		
		header('Expires: 0');
		header('Cache-Control: private');
		header('Pragma: cache');
		header('Content-type: application/download');
		header('Content-Disposition: attachment; filename='.$this->connDatabase.'_'.strtolower(date("Y-m-d_H-i")).'.sql');
		
		echo $cmnt.' '.$lf;
		echo $cmnt.' START SQLITE DATABASE DUMP'.$lf;
		echo $cmnt.' dump created with Vik-Off-Dumper'.$lf;
		echo $cmnt.' '.$lf;
		echo $cmnt.' Host: '.$_SERVER['SERVER_NAME'].$lf;
		echo $cmnt.' Database : '.$this->connDatabase.$lf;
		echo $cmnt.' Encoding : '.$this->_encoding.$lf;
		echo $cmnt.' Generation Time: '.date('d M Y H:i:s').$lf;
		echo $cmnt.' PHP Version: '.phpversion().$lf;
		echo $lf;
		echo $cmnt.' START TRANSACTION'.$lf;
		echo 'BEGIN;'.$lf;
		
		foreach($tables as $table){

			echo $lf;
			echo $cmnt.' '.str_repeat('-', 80).$lf;
			echo $lf;
			echo $cmnt."".$lf;
			echo $cmnt.' TABLE '.$table.' STRUCTURE'.$lf;
			echo $cmnt."".$lf;
			echo $lf;
			
			echo "DROP TABLE IF EXISTS ".$table.';'.$lf;
			echo $lf;
				
			echo $createtable[$table].';'.$lf;
			echo $lf;
			
			$numRows = $this->getOne('SELECT COUNT(1) FROM '.$table);
			
			if($numRows){
				
				// за раз из таблицы извлекается 100 строчек
				$rowsPerIteration = 100;
				$numIterations = ceil($numRows / $rowsPerIteration);
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
				
					echo $cmnt.$lf;
					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;
					echo $cmnt.$lf;
					echo $lf;
						
					foreach($rows as $rowIndex => $row){
						foreach($row as &$cell){
							if(is_string($cell)){
								$cell = str_replace("\n", '\n', $cell);
								$cell = str_replace("\r", '\\r', $cell);
							}
							$cell = $this->qe($cell);
						}

						echo "INSERT INTO ".$table." VALUES(".implode(',', $row).");".$lf;
					}
					echo $lf;
				}
			}
		}
		echo $lf;
		echo $cmnt.' COMMIT TRANSACTION'.$lf;
		echo 'COMMIT;'.$lf;
		echo $lf;
		echo $cmnt." ".$lf;
		echo $cmnt." END DATABASE DUMP".$lf;
		echo $cmnt." ".$lf;
		
		exit();
	}

}

?>