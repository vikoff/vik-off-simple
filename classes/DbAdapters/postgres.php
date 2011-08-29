<?

class DbAdapter_postgres extends DbAdapter{ 

	// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ
	public function connect(){
		
		$connString = 'host='.$this->connHost.' port='.$this->connPort.' user='.$this->connUser.' password='.$this->connPass.' dbname='.$this->connDatabase;
		$this->_dbrs = pg_connect($connString) or $this->error('Невозможно подключиться к серверу PgSQL');
		
		// if(!empty($this->_encoding))
			// mysql_query('SET NAMES '.$this->_params['encoding'], $this->_dbrs)or $this->error('Невозможно установить кодировку соединения с БД: '.mysql_error());
	
		$this->_connected = TRUE;
	}
	
	// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ
	public function setEncoding($encoding){
		
		$this->_encoding = $encoding;
		
		// if($this->isConnected())
			// $this->query('SET NAMES '.$this->_encoding);
	}
	
	// ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY
	public function getLastId($tablename = null, $fieldname = null){
		
		if(is_null($tablename) || is_null($fieldname))
			trigger_error('Имя таблицы и поля обязательны для заполнения', E_USER_ERROR);
			
		return $this->getOne('SELECT last_value FROM '.$tablename.'_'.$fieldname.'_seq');
	}
	
	// ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ
	public function getAffectedNum(){
		
		return pg_affected_rows($this->_dbrs);
	}

	// INSERT
	public function insert($table, $fieldsValues, $autoIncrementField = null){
		
		$insert_arr = array();
		foreach($fieldsValues as $field => $value)
			$insert_arr[] = $field.'=\''.$value.'\'';
		$insert_str = implode(',',$insert_arr);
		
		$sql = 'INSERT INTO '.$table.' SET '.$insert_str.(!is_null($autoIncrement) ? 'RETURNING '.$autoIncrementField : '');
		
		if(!is_null($autoIncrement)){
			return $this->getOne($sql);
		}else{
			$this->query($sql);
			return null;
		}
	}

	// QUERY
	public function query($sql){
		
		$this->saveQuery($sql);
		$rs = @pg_query($this->_dbrs, $sql) or $this->error(pg_last_error($this->_dbrs), $sql);
		return $rs;

	}
	
	// QUERY PARAMS
	public function query_prepared($sql, $params){
		
		// замена '?' на '$1', '$2' и т.д.
		if(strpos($sql, '?')){
			$sqlArr = explode('?', $sql);
			for($i = 0, $l = count($sqlArr) - 1; $i < $l; $i++)
				$sqlArr[$i] .= '$'.($i + 1);
			$sql = implode('', $sqlArr);
		}
		$this->saveQuery($sql);
		$rs = @pg_query_params($this->_dbrs, $sql, $params) or $this->error(pg_last_error($this->_dbrs), $sql);
		return $rs;

	}
	
	/** FREE RESULT */
	public function freeResult($rs){
		
		if(is_resource($rs))
			pg_free_result($rs);
	}

	//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)
	public function getOne($query, $default_value = null){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			$cell = pg_fetch_result($rs, 0, 0);
		else
			$cell = $default_value;

		$this->freeResult($rs);
		return $cell;
	}
	
	//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)
	public function getCell($query, $row, $column, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			$cell = pg_fetch_result($rs, $row, $column);
		else
			$cell = $default_value;
		
		$this->freeResult($rs);
		return $cell;
	}
	
	// GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs)){
			$row = pg_fetch_result($rs, 0, 0);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}

		$this->freeResult($rs);
		return $row;
	}
	
	// GET COL возвращает единственный столбец (первый в наборе)
	public function getCol($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			for($col = array(); $row = pg_fetch_row($rs); $col[] = $row[0]);
		else
			$col = $default_value;
		
		$this->freeResult($rs);
		return $col;
	}
	
	/**
	 * GET COL INDEXED
	 * возвращает одномерный ассоциативный массив.
	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД
	 * значение массива - значение второго столбца, извлекаемого из БД
	 *
	 * @param string $query
	 * @param string $index
	 * @param mixed $default_value
	 * @return array
	 */
	public function getColIndexed($query, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			for($col = array(); $row = pg_fetch_row($rs); $col[$row[0]] = $row[1]);
		else
			$col = $default_value;
		
		$this->freeResult($rs);
		return $col;
	}
	
	// GET ROW возвращает единственную строку (первую в наборе)
	public function getRow($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			$row = pg_fetch_assoc($rs);
		else
			$row = $default_value;
		
		$this->freeResult($rs);
		return $row;
	}
	
	// GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs)){
			$row = pg_fetch_assoc($rs);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}
		$this->freeResult($rs);
		return $row;
	}
	
	// GET ALL формирует многомерный ассоциативный массив
	public function getAll($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			for($data = array(); $row = pg_fetch_assoc($rs); $data[] = $row);
		else
			$data = $default_value;
		
		$this->freeResult($rs);
		return $data;
	}
	
	// GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 
	public function getAllIndexed($query, $index, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && pg_num_rows($rs))
			for($data = array(); $row = pg_fetch_assoc($rs); $data[$row[$index]] = $row);
		else
			$data = $default_value;

		$this->freeResult($rs);
		return $data;
	}
	
	public function fetchOne($sql, $bind = array(), $default = null){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs))
			$cell = pg_fetch_result($rs, 0, 0);
		else
			$cell = $default;

		$this->freeResult($rs);
		return $cell;
	}
	
	public function fetchRow($sql, $bind = array(), $default = null){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs))
			$row = pg_fetch_assoc($rs);
		else
			$row = $default;
		
		$this->freeResult($rs);
		return $row;
	}
	
	public function fetchPairs($sql, $bind = array(), $default = array()){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs))
			for($col = array(); $row = pg_fetch_row($rs); $col[$row[0]] = $row[1]);
		else
			$col = $default;
		
		$this->freeResult($rs);
		return $col;
	}
	
	public function fetchCol($sql, $bind = array(), $default = array()){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs))
			for($col = array(); $row = pg_fetch_row($rs); $col[] = $row[0]);
		else
			$col = $default;
		
		$this->freeResult($rs);
		return $col;
	}
	
	public function fetchAssoc($sql, $bind = array(), $default = array()){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs)){
			// извлечение первой строки для определения ключа
			$data = array();
			$firstRow = pg_fetch_assoc($rs);
			$key = key($firstRow);
			$data[$firstRow[$key]] = $firstRow;
			// извлечение остальных строк
			while($row = pg_fetch_assoc($rs))
				$data[$row[$key]] = $row;
		}
		else
			$data = $default;

		$this->freeResult($rs);
		return $data;
	}
	
	public function fetchAll($sql, $bind = array(), $default = array()){
		
		$rs = $this->query_prepared($sql, $bind);
		if(is_resource($rs) && pg_num_rows($rs))
			for($data = array(); $row = pg_fetch_assoc($rs); $data[] = $row);
		else
			$data = $default;
		
		$this->freeResult($rs);
		return $data;
	}
	
	// ESCAPE
	public function escape($str){
		
		if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){
			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())
				$str = stripslashes($str);
			$str = pg_escape_string($this->_dbrs, $str);
		}
		return $str;
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК ТАБЛИЦ
	 * в текущей базе данных
	 * @return array - массив-список таблиц
	 */
	public function showTables(){
	
		return $this->getCol("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
	}
	
	/**
	 * ПОЛУЧИТЬ СПИСОК БД
	 * @return array - массив-список баз данных
	 */
	public function showDatabases(){
		
		trigger_error('showDatabases not implemented', E_USER_ERROR);
	}
	
	// DESCRIBE
	public function describe($table){
		
		return $this->getAll('DESCRIBE '.$table);
	}
	
	/**
	 * ПОКАЗАТЬ СТРОКУ CREATE TABLE
	 * @param string $table - имя таблицы
	 * @return string - строка CREATE TABLE
	 */
	public function showCreateTable($table){
	
		trigger_error('showCreateTable not implemented', E_USER_ERROR);
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
		header('Content-Disposition: attachment; filename='.strtolower(date("d_M_Y")).'_db_'.$this->connDatabase.'_backup.sql');
		
		echo $cmnt." ".$lf;
		echo $cmnt." START DATABASE DUMP".$lf;
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
			
			$numRows = $this->getOne('SELECT COUNT(*) FROM '.$table);
			
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
							$cell = str_replace("\n", '\\r\\n', $cell);
							$cell = str_replace("\r", '', $cell);
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