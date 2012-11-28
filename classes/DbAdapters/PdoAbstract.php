<?php

abstract class DbAdapter_PdoAbstract extends DbAdapter {

	/** @var PDO */
	protected $_dbrs = null;

	protected $_isPdo = TRUE;

	/** @return PDO */
	abstract protected function _getPdoInstance();

	public function connect(){
		
		$start = microtime(1);
		try {
			$this->_dbrs = $this->_getPdoInstance();
			$this->_connected = TRUE;
		} catch (PDOException $e) {
			$this->_error('Невозможно подключиться к базе данных: '.$e->getMessage());
		}
		$this->_saveConnectTime(microtime(1) - $start);
	}

	public function setEncoding($encoding){}

	public function getLastId(){
	
		return $this->_dbrs->lastInsertId();
	}
	
	public function getAffectedNum(){
		
		trigger_error('function is not available in PDO', E_USER_ERROR);
	}

	public function query($sql, $bind = array()){

		$bind = $bind === null ? array(null) : (array)$bind;

		$sqlForLog = $sql.($bind ? '; BIND ['.implode('; ', $bind).']' : '');
		$this->_saveQuery($sqlForLog);
		$this->_queriesNum++;
		
		$start = microtime(1);

		/** @var $stmt PDOStatement */
		$stmt = $this->_dbrs->prepare($sql);
		if (!$stmt) {
			$this->_error($this->_dbrs->errorInfo(), $sqlForLog);
			return null;
		}
		try {
			$stmt->execute($bind) or $this->_error($stmt->errorInfo(), $sqlForLog);
		} catch (Exception $e) { $this->_error($e->getMessage(), $sql); }

		$this->_saveQueryTime(microtime(1) - $start);
		
		return $stmt;
	}
	
	public function fetchOne($sql, $bind = array(), $default = null){

		$data = $this->query($sql, $bind)->fetchColumn();
		return $data !== FALSE ? $data : $default;
	}

	public function fetchCell($sql, $col, $bind = array(), $default = null){

		$data = $this->query($sql, $bind)->fetchColumn($col);
		return $data !== FALSE ? $data : $default;
	}

	public function fetchRow($sql, $bind = array(), $default = null){

		$data = $this->query($sql, $bind)->fetch(PDO::FETCH_ASSOC);
		return $data ? $data : $default;
	}

	public function fetchCol($sql, $bind = array(), $default = array()){

		$data = $this->query($sql, $bind)->fetchAll(PDO::FETCH_COLUMN, 0);
		return $data ? $data : $default;
	}
	
	public function fetchPairs($sql, $bind = array(), $default = array()){

		$rs = $this->query($sql, $bind);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_NUM); $data[ $row[0] ] = $row[1]);

		return $data ? $data : $default;
	}

	public function fetchAll($sql, $bind = array(), $default = array()){

		$data = $this->query($sql, $bind)->fetchAll(PDO::FETCH_ASSOC);
		return $data !== FALSE ? $data : $default;
	}
	
	public function fetchAssoc($sql, $index, $bind = array(), $default = array()){

		$rs = $this->query($sql, $bind);
		for ($data = array(); $row = $rs->fetch(PDO::FETCH_ASSOC); $data[ $row[$index] ] = $row);

		return $data ? $data : $default;
	}

    /**
	 * UPDATE
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

		$bind_arr = array_merge($bind_arr, (array)$bind);
	
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
	 * @param string $where - SQL строка условия (без слова WHERE). Не должно быть пустой строкой.
	 * @param mixed $bind - параметры для SQL запроса
	 * @return integer количество затронутых строк
	 */
	public function delete($table, $where, $bind = array()) {
	
		if(!strlen($where))
			trigger_error('Функции delete не передано условие. Необходимо использовать truncate', E_USER_ERROR);
		
		$sql = 'DELETE FROM '.$table.' WHERE '.$where;
		$rs = $this->query($sql, $bind);

		return $rs->rowCount();
	}

	/**
	 * эскейпирование и заключение строки в ковычки
	 * замена последовательному вызову функций db::escape и db::quote
	 * @param mixed $cell - исходная строка
	 * @return string эскейпированая и заключенная в нужный тип ковычек строка
	 */
	public function qe($cell){
		
		return $this->quote($cell);
	}
	
	public function escape($str){
		
		return is_string($str)
			? $this->_dbrs->quote($str)
			: $str;
	}

	/**
	 * заключение строк в ковычки и экранирование
	 * в зависимости от типа данных
	 * @override DbAdapter method
	 * @param mixed $cell - исходная строка
	 * @return string заключенная в нужный тип ковычек строка
	 */
	public function quote($cell){
		
		switch(strtolower(gettype($cell))){
			case 'boolean':
				return $cell ? '1' : "''";
			case 'null':
				return 'NULL';
			case 'string':
				return $this->_dbrs->quote($cell);
			default:
				return $cell;
		}
	}

	protected function _error($msg, $sql = ''){
		
		parent::_error(is_array($msg) ? implode('; ', $msg) : $msg, $sql);
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
			
			$numRows = $this->fetchOne('SELECT COUNT(1) FROM '.$table);
			
			if($numRows){
				
				// за раз из таблицы извлекается 100 строчек
				$rowsPerIteration = 100;
				$numIterations = ceil($numRows / $rowsPerIteration);
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->fetchAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
				
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

