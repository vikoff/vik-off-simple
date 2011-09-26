<?

class DbAdapter_mysql extends DbAdapter{ 
	
	/** ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ */
	public function connect(){
	
		$this->_dbrs = mysql_connect($this->connHost, $this->connUser, $this->connPass, $new_link = TRUE) or $this->error('Невозможно подключиться к серверу MySQL');
		$this->selectDb($this->connDatabase);
		
		if(!empty($this->_encoding))
			$this->query('SET NAMES '.$this->_encoding);
	
		$this->_connected = TRUE;
	}
	
	/** УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ */
	public function setEncoding($encoding){
		
		$this->_encoding = $encoding;
		
		if($this->isConnected())
			$this->query('SET NAMES '.$this->_encoding);
	}
	
	/** ВЫБРАТЬ БАЗУ ДАННЫХ */
	public function selectDb($db){
		
		$this->connDatabase = $db;
		mysql_select_db($this->connDatabase, $this->_dbrs)or $this->error('Невозможно выбрать базу данных');
	}
	
	/** ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY */
	public function getLastId(){
	
		return mysql_insert_id($this->_dbrs);
	}
	
	/** ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ */
	public function getAffectedNum(){
		
		return mysql_affected_rows($this->_dbrs);
	}
	
	/**
	 * REPLACE
	 * вставка данных в таблицу (аналогично INSERT). Но если в таблице уже присутствует PRIMARY KEY
	 * или UNIQUE с тем же значением, что и переданное, то старая запись будет удалена.
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для вставки
	 * @return integer последний вставленный id 
	 */
	public function replace($table, $fieldsValues){
		
		$insert_arr = array();
		foreach($fieldsValues as $field => $value)
			$insert_arr[] = $field.'=\''.$value.'\'';
		$insert_str = implode(',',$insert_arr);
		
		$sql = 'REPLACE INTO '.$table.' SET '.$insert_str;
		$this->query($sql);
		$id = mysql_insert_id($this->_dbrs);

		return $id;
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
		$rs = mysql_query($sql, $this->_dbrs) or $this->error(mysql_error($this->_dbrs), $sql);
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
	public function getOne($query, $default_value = null){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, 0, 0);
		else
			$cell = $default_value;
		
		return $cell;
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
	public function getCell($query, $row, $column, $default_value = null){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, $row, $column);
		else
			$cell = $default_value;
		
		return $cell;
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
	public function getStaticOne($query, $table, $fieldsvalues, $default_value = null){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs)){
			$row = mysql_result($rs, 0, 0);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}
		return $row;
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
		if(is_resource($rs) && mysql_num_rows($rs))
			for($col = array(); $row = mysql_fetch_row($rs); $col[] = $row[0]);
		else
			$col = $default_value;
		
		return $col;
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
	public function getColIndexed($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($col = array(); $row = mysql_fetch_row($rs); $col[$row[0]] = $row[1]);
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
	public function getRow($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$row = mysql_fetch_assoc($rs);
		else
			$row = $default_value;
		
		return $row;
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
	public function getStaticRow($query, $table, $fieldsvalues){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs)){
			$row = mysql_fetch_assoc($rs);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $fieldsvalues;
		}
		$this->freeResult($rs);
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
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[] = $row);
		else
			$data = $default_value;
		
		return $data;
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
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);
		else
			$data = $default_value;
		return $data;
	}
	
	/**
	 * ЭКРАНИРОВАНИЕ ДАННЫХ
	 * выполняется с учетом типа данных для предотвращения SQL-инъекций
	 * @param mixed строка для экранирования
	 * @param mixed - безопасная строка
	 */
	public function escape($str){
		
		if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){
			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())
				$str = stripslashes($str);
			$str = mysql_real_escape_string($str, $this->_dbrs);
		}
		return $str;
	}
	
	/**
	 * ЗАКЛЮЧЕНИЕ ИМЕНИ ПОЛЯ В КАВЫЧКИ
	 * для полей, имена которых совпадают с ключевыми словами
	 * @param string $fieldname - имя поля
	 * @param string - имя поля, заключенное в кавычки
	 */
	public function quoteFieldName($fieldname){
		return "`".$fieldname."`";
	}
	
	/**
	 * DESCRIBE
	 * получить массив, описывающий структуру таблицы
	 * @param string $table - имя таблицы
	 * @return array - структура таблицы
	 */
	public function describe($table){
		
		return $this->getAll('DESCRIBE '.$table);
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК ТАБЛИЦ
	 * в текущей базе данных
	 * @return array - массив-список таблиц
	 */
	public function showTables(){
	
		return $this->getCol('SHOW TABLES');
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК БД
	 * @return array - массив-список баз данных
	 */
	public function showDatabases(){
	
		return $this->getCol('SHOW DATABASES');
	}
	
	/**
	 * ПОКАЗАТЬ СТРОКУ CREATE TABLE
	 * @param string $table - имя таблицы
	 * @return string - строка CREATE TABLE
	 */
	public function showCreateTable($table){
	
		return $this->getCell('SHOW CREATE TABLE '.$table, 0, 1);
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
		echo $cmnt." START MYSQL DATABASE DUMP".$lf;
		echo $cmnt." dump created with Vik-Off-Dumper".$lf;
		echo $cmnt." ".$lf;
		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;
		echo $cmnt." Database : ".$this->connDatabase.$lf;
		echo $cmnt." Encoding : ".$this->_encoding.$lf;
		echo $cmnt." Generation Time: ".date("d M Y H:i:s").$lf;
		echo $cmnt." MySQL Server version: ".mysql_get_server_info().$lf;
		echo $cmnt." PHP Version: ".phpversion().$lf;
		echo $cmnt."";

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
				
				// извлечение названий полей
				$fields = array();
				foreach($this->getAll('DESCRIBE '.$table, array()) as $f)
					$fields[] = $this->quoteFieldName($f['Field']);
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
					foreach($rows as $rowIndex => $row){
						foreach($row as &$cell){
							if(is_string($cell)){
								$cell = str_replace("\n", '\\r\\n', $cell);
								$cell = str_replace("\r", '', $cell);
							}
							$cell = $this->qe($cell);
						}
						$rows[$rowIndex] = $lf."\t(".implode(',', $row).")";
					}
				
					echo $cmnt.$lf;
					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;
					echo $cmnt.$lf;
					echo $lf;
						
					echo "INSERT INTO ".$table." (".implode(', ', $fields).") VALUES ".implode(',', $rows).';'.$lf;
						
					echo $lf;
				}
			}
		}
		echo $cmnt." ".$lf;
		echo $cmnt." END DATABASE DUMP".$lf;
		echo $cmnt." ".$lf;
		
		exit();
	}
	
}

?>