<?

class DbAdapter_Mysql extends DbAdapter {
	
	/** ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ */
	public function connect() {
		
		$start = microtime(1);
		
		$this->_dbrs = mysql_connect($this->connHost, $this->connUser, $this->connPass, $new_link = TRUE) or $this->_error('Невозможно подключиться к серверу MySQL');

		if ($this->connDatabase)
			$this->selectDb($this->connDatabase);
		
		$this->_saveConnectTime(microtime(1) - $start);
		
		if(!empty($this->_encoding))
			$this->query('SET NAMES '.$this->_encoding);
	
		$this->_connected = TRUE;
	}
	
	public function setEncoding($encoding) {
		
		$this->_encoding = $encoding;
		
		if($this->isConnected())
			$this->query('SET NAMES '.$this->_encoding);
	}
	
	public function selectDb($db) {
		
		$this->connDatabase = $db;
		mysql_select_db($this->connDatabase, $this->_dbrs)or $this->_error(mysql_error());
	}
	
	public function getLastId() {
	
		return mysql_insert_id($this->_dbrs);
	}
	
	public function getAffectedNum() {
		
		return mysql_affected_rows($this->_dbrs);
	}
	
	/**
	 * вставка данных в таблицу (аналогично INSERT). Но если в таблице уже присутствует PRIMARY KEY
	 * или UNIQUE с тем же значением, что и переданное, то старая запись будет удалена.
	 * @param string $table - имя таблицы
	 * @param array $fieldsValues - массив пар (поле => значение) для вставки
	 * @return integer последний вставленный id 
	 */
	public function replace($table, $fieldsValues) {
		
		$insert_arr = array();
		foreach($fieldsValues as $field => $value)
			$insert_arr[] = $field.'=\''.$value.'\'';
		$insert_str = implode(',',$insert_arr);
		
		$sql = 'REPLACE INTO '.$table.' SET '.$insert_str;
		$this->query($sql);
		$id = mysql_insert_id($this->_dbrs);

		return $id;
	}

	public function query($sql, $bind = array()) {

		$bind = $bind === null ? array(null) : (array)$bind;

		if (!empty($bind)) {
			$sqlParts = explode('?', $sql);
			for ($i = count($sqlParts) - 2; $i >= 0; $i--)
				$sqlParts[$i] .= $this->qe($bind[$i]);
			$sql = implode('', $sqlParts);
		}

		$this->_saveQuery($sql);
		$this->_queriesNum++;
		
		$start = microtime(1);
		$rs = mysql_query($sql, $this->_dbrs) or $this->_error(mysql_error($this->_dbrs), $sql);
		$this->_saveQueryTime(microtime(1) - $start);
		
		return $rs;
	}
	
	public function fetchOne($query, $bind = array(), $default_value = null) {
		
		$rs = $this->query($query, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, 0, 0);
		else
			$cell = $default_value;
		
		return $cell;
	}

	public function fetchCell($sql, $col, $bind = array(), $default = null) {

		$rs = $this->query($sql, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, 0, $col);
		else
			$cell = $default;

		return $cell;
	}

	public function fetchCol($query, $bind = array(), $default_value = array()) {
		
		$rs = $this->query($query, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($col = array(); $row = mysql_fetch_row($rs); $col[] = $row[0]);
		else
			$col = $default_value;
		
		return $col;
	}
	
	public function fetchPairs($query, $bind = array(), $default_value = array()){
		
		$rs = $this->query($query, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($col = array(); $row = mysql_fetch_row($rs); $col[$row[0]] = $row[1]);
		else
			$col = $default_value;
		
		return $col;
	}
	
	public function fetchRow($query, $bind = array(), $default_value = array()){
		
		$rs = $this->query($query, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			$row = mysql_fetch_assoc($rs);
		else
			$row = $default_value;
		
		return $row;
	}

	public function fetchAll($query, $bind = array(), $default_value = array()){
		
		$rs = $this->query($query, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[] = $row);
		else
			$data = $default_value;
		
		return $data;
	}
	
	public function fetchAssoc($sql, $index, $bind = array(), $default = array()){
		
		$rs = $this->query($sql, $bind);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);
		else
			$data = $default;
		return $data;
	}
	
	public function escape($str){
		
		return is_string($str)
			? mysql_real_escape_string($str, $this->_dbrs)
			: $str;
	}
	
	public function quoteFieldName($fieldname){
		return "`$fieldname`";
	}
	
	public function describe($table){
		
		$data = $this->fetchAll('DESCRIBE '.$table);
		foreach ($data as & $row) {
			$row['name'] = $row['Field'];
			$row['type'] = $row['Type'];
			unset($row['Field'], $row['Type']);
		}

		return $data;
	}
	
	public function showTables(){
	
		return $this->fetchCol('SHOW TABLES');
	}
	
	public function showDatabases(){
	
		return $this->fetchCol('SHOW DATABASES');
	}
	
	public function showCreateTable($table){
	
		return $this->fetchCell('SHOW CREATE TABLE '.$table, 0, 1);
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
			
			$numRows = $this->fetchOne('SELECT COUNT(1) FROM '.$table);
			
			if($numRows){
				
				// за раз из таблицы извлекается 100 строчек
				$rowsPerIteration = 100;
				$numIterations = ceil($numRows / $rowsPerIteration);
				
				// извлечение названий полей
				$fields = array();
				foreach($this->fetchAll('DESCRIBE '.$table, array()) as $f)
					$fields[] = $this->quoteFieldName($f['Field']);
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->fetchAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
					foreach($rows as $rowIndex => $row){
						foreach($row as &$cell){
							if(is_string($cell)){
								$cell = str_replace("\n", '\\n', $cell);
								$cell = str_replace("\r", '\\r', $cell);
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
