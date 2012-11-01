<?

class DbAdapter_sqlite extends DbAdapter{
	
	/** ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ */
	public function connect(){
		
		$start = microtime(1);
		
		$this->_dbrs = sqlite_open($this->connDatabase)or $this->error('Невозможно подключиться к базе данных');
		$this->_connected = TRUE;
		
		$this->_saveConnectTime(microtime(1) - $start);
	}

	/** УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ */
	public function setEncoding($encoding){
	
	}
	
	/** ВЫБРАТЬ БАЗУ ДАННЫХ */
	public function selectDb($db){}
	
	/** ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY */
	public function getLastId(){
	
		return sqlite_last_insert_rowid($this->_dbrs);
	}
	
	/** ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ */
	public function getAffectedNum(){
		
		return sqlite_changes($this->_dbrs);
	}

	/**
	 * ВЫПОЛНИТЬ ЗАПРОС
	 * @param string $query - SQL-запрос
	 * @return resource - ресурс ответа базы данных
	 */
	public function query($query){
		
		$sql = $query;
		$this->_saveQuery($sql);
		$this->_queriesNum++;
		
		$start = microtime(1);
		$rs = sqlite_query($this->_dbrs, $sql) or $this->error(sqlite_error_string(sqlite_last_error($this->_dbrs)), $sql);
		$this->_saveQueryTime(microtime(1) - $start);
		
		return $rs;
	}
	
	/**
	 * GET ONE
	 * выполнить запрос и вернуть единственное значение (первая строка, первый столбец)
	 * @param string $query - SQL-запрос
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|$default_value
	 */
	public function getOne($query, $default_value = 0){
		
		$rs = $this->query($query);
		$data = sqlite_fetch_single($rs);
		if($data !== FALSE)
			return $data;
		else
			return $default_value;
	}
	
	/**
	 * GET CELL
	 * выполнить запрос и вернуть единственное значение (указанные строка и столбец)
	 * @param string $query - SQL-запрос
	 * @param integer $row - номер строки, значение которой будет возвращено
	 * @param integer $column - номер столбца, значение которого будет возвращено
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|$default_value
	 */
	public function getCell($query, $row, $column, $default_value = 0){
		
		trigger_error('not implemented', E_USER_ERROR);
		// $rs = $this->query($query);
		// if(mysql_num_rows($rs))
			// $cell = mysql_result($rs, $row, $column);
		// else
			// $cell = $default_value;
		
		// return $cell;
	}
	
	/**
	 * GET STATIC ONE
	 * выполнить запрос и вернуть единственное значение (первая строка, первый столбец)
	 * а если строка не найдена, то вставить ее в таблицу
	 * @param string $query - SQL-запрос
	 * @param string $table - таблица для вставки
	 * @param array $fieldsvalues - ассоциативный массив данных для вставки
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return mixed|$default_value
	 */
	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){
		
		trigger_error('not implemented', E_USER_ERROR);
		// $rs = $this->query($query);
		// if(mysql_num_rows($rs)){
			// $row = mysql_result($rs, 0, 0);
		// }else{
			// $this->insert($table, $fieldsvalues);
			// $row = $default_value;
		// }
		// return $row;
	}
	
	/**
	 * GET COL
	 * выполнить запрос и вернуть единственный столбец (первый)
	 * @param string $query - SQL-запрос
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default_value
	 */
	public function getCol($query, $default_value = array()){
		
		$rs = $this->query($query);
		for($data = array(); $row = sqlite_fetch_single($rs); $data[] = $row);
		if(count($data))
			return $data;
		else
			return $default_value;
	}
	
	/**
	 * GET COL INDEXED
	 * возвращает одномерный ассоциативный массив.
	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД
	 * значение массива - значение второго столбца, извлекаемого из БД
	 * @param string $query
	 * @param mixed $default_value
	 * @return array|$default_value
	 */
	public function getColIndexed($query, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs))
			for($col = array(); $row = sqlite_fetch_array($rs, SQLITE_NUM); $col[$row[0]] = $row[1]);
		else
			$col = $default_value;
		
		return $col;
	}

	/**
	 * GET ROW
	 * выполнить запрос и вернуть единственную строку (первую)
	 * @param string $query - SQL-запрос
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default_value
	 */
	public function getRow($query, $default_value = 0){
		
		$rs = $this->query($query);
		$data = sqlite_fetch_array($rs, SQLITE_ASSOC);
		if(is_array($data))
			return $data;
		else
			return $default_value;
	}
	
	/**
	 * GET STATIC ROW
	 * выполнить запрос и вернуть единственную строку (первую)
	 * а если строка не найдена, то вставить ее в таблицу
	 * @param string $query - SQL-запрос
	 * @param string $table - таблица для вставки
	 * @param array $fieldsvalues - ассоциативный массив данных для вставки
	 * @return array|$fieldsvalues
	 */
	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){
		
		$rs = $this->query($query);
		if(mysql_num_rows($rs)){
			$row = mysql_fetch_assoc($rs);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}
		return $row;
	}
	
	/**
	 * GET ALL
	 * выполнить запрос и вернуть многомерный ассоциативный массив данных
	 * @param string $query - SQL-запрос
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default_value
	 */
	public function getAll($query, $default_value = array()){

		$rs = $this->query($query);
		$data = sqlite_fetch_all($rs, SQLITE_ASSOC);
		
		return !empty($data) ? $data : $default_value;
	}
	
	/**
	 * GET ALL INDEXED
	 * выполнить запрос и вернуть многомерный индексированных ассоциативный массив данных
	 * @param string $query - SQL-запрос
	 * @param string $index - имя поля, по которому будет индексироваться массив результатов.
	 *        Важно проследить, чтобы значение у индекса было уникальным у каждой строки,
	 *        иначе дублирующиеся строки будут затерты.
	 * @param mixed $default_value - значение, возвращаемое если запрос ничего не вернул
	 * @return array|$default_value
	 */
	public function getAllIndexed($query, $index, $default_value = 0){
		
		$rs = $this->query($query);
		$data = array();
		foreach(sqlite_fetch_all($rs, SQLITE_ASSOC) as $row)
			$data[$row[$index]] = $row;
		
		return !empty($data) ? $data : $default_value;
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
	 * ЭКРАНИРОВАНИЕ ДАННЫХ
	 * выполняется с учетом типа данных для предотвращения SQL-инъекций
	 * @param mixed строка для экранирования
	 * @param mixed - безопасная строка
	 */
	public function escape($str){
		
		return is_string($str)
			? sqlite_escape_string($str)
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
	 * ЗАКЛЮЧЕНИЕ СТРОК В КОВЫЧКИ
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
			default:
				return "'".$cell."'";
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
	
		return $this->getCol('SELECT name FROM sqlite_master WHERE type = "table"');
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
		
		echo $cmnt." ".$lf;
		echo $cmnt." START SQLITE DATABASE DUMP".$lf;
		echo $cmnt." dump created with Vik-Off-Dumper".$lf;
		echo $cmnt." ".$lf;
		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;
		echo $cmnt." Database : ".$this->connDatabase.$lf;
		echo $cmnt." Encoding : ".$this->_encoding.$lf;
		echo $cmnt." Generation Time: ".date("d M Y H:i:s").$lf;
		echo $cmnt." PHP Version: ".phpversion().$lf;
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
								$cell = str_replace("\n", '\\n', $cell);
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