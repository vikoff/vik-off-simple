<?

class DbAdapter_sqlite extends DbAdapter{
	
	// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ
	public function connect(){
		
		$this->_dbrs = sqlite_open($this->connDatabase)or $this->error('Невозможно подключиться к базе данных');
		$this->_connected = TRUE;
	}

	// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ
	public function setEncoding($encoding){
	
	}
	
	// ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY
	public function getLastId(){
	
		return sqlite_last_insert_rowid($this->_dbrs);
	}
	
	// ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ
	public function getAffectedNum(){
		
		return sqlite_changes($this->_dbrs);
	}

	// функция QUERY
	public function query($query){
		
		$sql = $query;
		$this->_saveQuery($sql);
		$this->_queriesNum++;
		
		$start = microtime(1);
		$rs = sqlite_query($this->_dbrs, $sql) or $this->error(sqlite_error_string(sqlite_last_error($this->_dbrs)), $sql);
		$this->_saveQueryTime(microtime(1) - $start);
		
		return $rs;
	}
	
	//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)
	public function getOne($query, $default_value = 0){
		
		$rs = $this->query($query);
		$data = sqlite_fetch_single($rs);
		if($data !== FALSE)
			return $data;
		else
			return $default_value;
	}
	
	//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)
	public function getCell($query, $row, $column, $default_value = 0){
		
		// $rs = $this->query($query);
		// if(mysql_num_rows($rs))
			// $cell = mysql_result($rs, $row, $column);
		// else
			// $cell = $default_value;
		
		// return $cell;
	}
	
	// функция GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){
		
		// $rs = $this->query($query);
		// if(mysql_num_rows($rs)){
			// $row = mysql_result($rs, 0, 0);
		// }else{
			// $this->insert($table, $fieldsvalues);
			// $row = $default_value;
		// }
		// return $row;
	}
	
	// функция GET COL возвращает единственный столбец (первый в наборе)
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

	// функция GET ROW возвращает единственную строку (первую в наборе)
	public function getRow($query, $default_value = 0){
		
		$rs = $this->query($query);
		$data = sqlite_fetch_array($rs, SQLITE_ASSOC);
		if(is_array($data))
			return $data;
		else
			return $default_value;
	}
	
	// функция GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
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
	
	// функция GET ALL формирует многомерный ассоциативный массив
	public function getAll($query, $default_value = array()){

		$rs = $this->query($query);
		$data = sqlite_fetch_all($rs, SQLITE_ASSOC);
		if(count($data))
			return $data;
		else
			return $default_value;
	}
	
	// функция GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 
	public function getAllIndexed($query, $index, $default_value = 0){
		
		// $rs = $this->query($query);
		// if(mysql_num_rows($rs))
			// for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);
		// else
			// $data = $default_value;
		// return $data;
	}
	
	// ESCAPE
	public function escape($str){
		
		if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){
			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())
				$str = stripslashes($str);
			$str = sqlite_escape_string($str);
		}
		return $str;
	}
	
	// QUOTE FIELD NAME
	public function quoteFieldName($field){
		return "'".$field."'";
	}
	
	// DESCRIBE
	public function describe($table){
		
		return $this->getAll('PRAGMA table_info('.$table.')');
	}
	
	// ПОЛУЧИТЬ СПИСОК ТАБЛИЦ
	public function showTables(){
	
		return $this->getCol('SELECT name FROM sqlite_master WHERE type = "table"');
	}
	
	// ПОКАЗАТЬ СТРОКУ CREATE TABLE
	public function showCreateTable($table){
	
		return $this->getOne('SELECT sql FROM sqlite_master WHERE type = "table" AND name= "'.$table.'"');
	}
	
	// СОЗДАТЬ ДАМП ДАННЫХ
	public function makeDump(){

		$lf = "\n";
		$cmnt = '#';
		$tables = array();
		$createtable = array();
		
		$cmnt = '--';
		
		$tables = $this->showTables();

		// get 'table create' parts for all tables
		foreach ($tables as $table){
			$createtable[$table] = $this->showCreateTable($table);
		}
		
		header('Expires: 0');
		header('Cache-Control: private');
		header('Pragma: cache');
		header('Content-type: application/download');
		header('Content-Disposition: attachment; filename='.strtolower(date("Y_m_d")).'_backup_'.$this->connDatabase.'.sql');
		
		echo $cmnt." ".$lf;
		echo $cmnt." START SQLITE DATABASE DUMP".$lf;
		echo $cmnt." dump created with Vik-Off-Dumper".$lf;
		echo $cmnt." ".$lf;
		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;
		echo $cmnt." Database : ".$this->connDatabase.$lf;
		echo $cmnt." Generation Time: ".date("d M Y H:i:s", (time() - date("Z") + 10800)).$lf;
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
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
				
					echo $cmnt.$lf;
					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;
					echo $cmnt.$lf;
					echo $lf;
						
					foreach($rows as $rowIndex => $row){
						foreach($row as &$cell){
							$cell = str_replace("\n", '\\r\\n', $cell);
							$cell = str_replace("\r", '', $cell);
							$cell = $this->qe($cell);
						}

						echo "INSERT INTO ".$table." VALUES(".implode(',', $row).");".$lf;
					}
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