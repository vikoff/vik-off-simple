<?

class DbAdapter_mysqli implements DbAdapter{ 
	
	// флаг, что соединение установлено
	private $_connected = FALSE;
	
	// флаг о необходимости логирования sql в файл
	private $_fileLog = FALSE;
	
	// массив сохраненных SQL запросов
	private $_sqls = array();
	
	// время выполнения каждого запроса
	private $_queriesTime = array();

	// число выполненных запросов
	private $_queriesNum = 0;
	
	// массив сохранения сообщений об ошибках
	private $_error = array();
	
	// режим накопления сообщений об ошибках
	private $_errorHandlingMode = FALSE;
	
	// ресурс соединения с базой данных
	private $_dbrs = null;
	
	// параметры подключения к БД
	private $connHost = '';
	private $connUser = '';
	private $connPass = '';
	private $connDatabase = '';
	
	// дополнительные параметры
	private $_encoding = 'utf8';
	
	
	// КОНСТРУКТОР
	public function __construct($host, $user, $pass, $database){
		
		$this->connHost = $host;
		$this->connUser = $user;
		$this->connPass = $pass;
		$this->connDatabase = $database;
	}
	
	// ВКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК
	public function enableErrorHandlingMode(){
		$this->_errorHandlingMode = TRUE;
	}
	
	// ОТКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК
	public function disableErrorHandlingMode(){
		$this->_errorHandlingMode = TRUE;
	}
	
	// ВЫПОЛНЕНО ЛИ ПОДКЛЮЧЕНИЕ К БД
	public function isConnected(){
		
		return $this->_connected;
	}
	
	// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ
	public function connect(){
	
		$this->_dbrs = new mysqli($this->connHost, $this->connUser, $this->connPass, $this->connDatabase);
		
		if(mysqli_connect_error())
			$this->error('Невозможно подключиться к серверу MySQL: '.mysqli_connect_error());
			
		if(!empty($this->_encoding))
			$this->_dbrs->set_charset($this->_encoding) or error('Не удалось установить кодировку БД');
	
		$this->_connected = TRUE;
	}
	
	// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ
	public function setEncoding($encoding){
		
		$this->_encoding = $encoding;
		
		if($this->isConnected())
			$this->_dbrs->set_charset($this->_encoding) or error('Не удалось установить кодировку БД');
	}
	
	// SQL FILE LOG
	public function fileLog($boolEnable){
		
		$this->_fileLog = TRUE;
	}
	
	// INSERT
	public function insert($table, $fieldsValues){
		
		$insert_arr = array();
		foreach($fieldsValues as $field => $value)
			$insert_arr[] = $field.'=\''.$value.'\'';
		$insert_str = implode(',',$insert_arr);
		
		$sql = 'INSERT INTO '.$table.' SET '.$insert_str;
		$this->query($sql);
		$id = $this->_dbrs->insert_id;

		return $id;
	}

	// REPLACE
	public function replace($table, $fieldsValues){
		
		$insert_arr = array();
		foreach($fieldsValues as $field => $value)
			$insert_arr[] = $field.'=\''.$value.'\'';
		$insert_str = implode(',',$insert_arr);
		
		$sql = 'REPLACE INTO '.$table.' SET '.$insert_str;
		$this->query($sql);
		$id = $this->_dbrs->insert_id;

		return $id;
	}
	
	// UPDATE
	public function update($table, $fieldsValues, $conditions){
		
		$update_arr = array();
		foreach($fieldsValues as $field => $value)
			$update_arr[] = $field.'=\''.$value.'\'';
		$update_str = implode(',',$update_arr);
		
		$conditions = trim(str_replace('WHERE', '', $conditions));
		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';
	
		if(!strlen($conditions))
			trigger_error('Функции update не передано условие', E_USER_ERROR);
		
		$sql = 'UPDATE '.$table.' SET '.$update_str.$conditions;
		$this->query($sql);
		$affected = mysql_affected_rows($this->_dbrs);

		return $affected;
	}

	/**
	 * UPDATE INSERT
	 * обновляет информацию в таблице.
	 * Если не было обновлено ни одной строки, создает новую строку.
	 * Возвращает 0, если было произведено обновление существующей строки,
	 * Возвращает id, если была произведена вставка новой строки
	 * ВАЖНО: при создании новой строки, функция заполнит ее данными из $fieldsValues и $conditionArr
	**/
	public function updateInsert($table, $fieldsValues, $conditionFieldsValues){
		
		$update_arr = array();
		foreach($fieldsValues as $field => $value)
			$update_arr[] = $field.'=\''.$value.'\'';
		
		if(!is_array($conditionFieldsValues) || !count($conditionFieldsValues)){
			$this->error('функция updateInsert получила неверное условие conditionFieldsValues');
			return false;
		}
		$conditionArr = array();
		foreach($conditionFieldsValues as $field => $value)
			$conditionArr[] = $field.'=\''.$value.'\'';
		
		$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.implode(' AND ',$conditionArr);
		$this->query($sql);
		$affected = (int)mysql_affected_rows($this->_dbrs);
		
		// если не было изменено ни одной строки, смотрим внимательно
		if($affected == 0){
			// если такая запись присутствует в таблице, то все хорошо
			if($this->getOne('SELECT COUNT(*) FROM '.$table.' WHERE '.implode(' AND ',$conditionArr), 0))
				return 0;
			// если же нет, то создаем ее
			else
				return $this->insert($table, array_merge($fieldsValues, $conditionFieldsValues));
		}
		// если строки были изменены, значит такая запись уже присутствует в таблице
		elseif($affected > 0){
			return 0;
		}
	}

	// DELETE
	public function delete($table, $conditions){
		
		$conditions = trim(str_replace('WHERE', '', $conditions));
		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';
		
		$sql = 'DELETE FROM '.$table.$conditions;
		$this->query($sql);
		$affected = mysql_affected_rows($this->_dbrs);

		return $affected;
	}

	// QUERY
	public function query($query){
		
		$sql = $query;
		$this->_saveQuery($sql);
		$this->_queriesNum++;
		
		$start = microtime(1);
		$rs = mysql_query($sql, $this->_dbrs) or $this->error(mysql_error($this->_dbrs), $sql);
		$this->_saveQueryTime(microtime(1) - $start);
		
		return $rs;
	}
	
	//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)
	public function getOne($query, $default_value = null){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, 0, 0);
		else
			$cell = $default_value;
		
		return $cell;
	}
	
	//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)
	public function getCell($query, $row, $column, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$cell = mysql_result($rs, $row, $column);
		else
			$cell = $default_value;
		
		return $cell;
	}
	
	// GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs)){
			$row = mysql_result($rs, 0, 0);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}
		return $row;
	}
	
	// GET COL возвращает единственный столбец (первый в наборе)
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
	public function getColIndexed($query, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($col = array(); $row = mysql_fetch_row($rs); $col[$row[0]] = $row[1]);
		else
			$col = $default_value;
		
		return $col;
	}
	
	// GET ROW возвращает единственную строку (первую в наборе)
	public function getRow($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			$row = mysql_fetch_assoc($rs);
		else
			$row = $default_value;
		
		return $row;
	}
	
	// GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу
	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs)){
			$row = mysql_fetch_assoc($rs);
		}else{
			$this->insert($table, $fieldsvalues);
			$row = $default_value;
		}
		return $row;
	}
	
	// GET ALL формирует многомерный ассоциативный массив
	public function getAll($query, $default_value = array()){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[] = $row);
		else
			$data = $default_value;
		
		return $data;
	}
	
	// GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 
	public function getAllIndexed($query, $index, $default_value = 0){
		
		$rs = $this->query($query);
		if(is_resource($rs) && mysql_num_rows($rs))
			for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);
		else
			$data = $default_value;
		return $data;
	}
	
	// ESCAPE
	public function escape($str){
		
		return is_string($str)
			? mysql_real_escape_string($str, $this->_dbrs)
			: $str;
	}
	
	// QUOTE FIELD NAME
	public function quoteFieldName($field){
		return '`'.$field.'`';
	}
	
	// DESCRIBE
	public function describe($table){
		
		return $this->getAll('DESCRIBE '.$table);
	}

	// СОХРАНИТЬ ЗАПРОС
	private function _saveQuery($sql){
		
		$this->_sqls[] = $sql;
	}
	
	// СОХРАНИТЬ ВРЕМЯ ИСПОЛНЕНИЯ ЗАПРОСА
	private function _saveQueryTime($t){
	
		$this->_queriesTime[] = $t;
	}
	
	// ПОЛУЧИТЬ ЧИСЛО ВЫПОЛНЕННЫХ SQL ЗАПРОСОВ
	public function getQueriesNum(){
		
		return $this->_queriesNum;
	}
	
	// ПОЛУЧИТЬ ВЫПОЛНЕННЫЕ SQL ЗАПРОСЫ
	public function getQueries(){
		
		return $this->_sqls;
	}
	
	// ПОЛУЧИТЬ ОБЩЕЕ ВРЕМЯ ВЫПОЛНЕНИЯ SQL ЗАПРОСОВ
	public function getQueriesTime(){
		
		return array_sum($this->_queriesTime);
	}
	
	// ПОЛУЧИТЬ АССОЦИАТИВНЫЙ МАССИВ ЗАПРОС + ВРЕМЯ
	public function getQueriesWithTime(){
		
		$output = array();
		foreach($this->_sqls as $index => $sql)
			$output[] = array(
				'sql' => $sql,
				'time' => isset($this->_queriesTime[$index]) ? $this->_queriesTime[$index] : '-'
			);
		return $output;
	}
	
	// ПЕРЕХВАТ ОШИБОК ПРИ ВЫПОЛНЕНИИ SQL ЗАПРОСОВ
	private function error($msg, $sql = ''){

		if($this->_errorHandlingMode)
			$this->setError('"'.$sql.'"<br />'.$msg);
		else
			trigger_error('<hr />'.$sql.'<hr /><br />'.$msg.'<br />', E_USER_ERROR);
	}
	
	public function showTables(){
	
		return $this->getCol('SHOW TABLES');
	}
	
	public function makeDump(){

		$lf = "\n";
		$cmnt = '#';
		$tables = array();
		$createtable = array();
		
		$PHP_EVAL_MODE = FALSE;
		$cmnt = $PHP_EVAL_MODE ? '//' : '#';
		
		$tables = $this->getCol('SHOW TABLES');

		// get 'table create' parts for all tables
		foreach ($tables as $table){
			$createtable[$table] = $this->getCell('SHOW CREATE TABLE '.$table, 0, 1);
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
		echo $cmnt." Generation Time: ".date("d M Y H:i:s").$lf;
		echo $cmnt." MySQL Server version: ".mysql_get_server_info().$lf;
		echo $cmnt." PHP Version: ".phpversion().$lf;
		echo $cmnt."";

		foreach($tables as $table){

			echo $lf;
			echo $cmnt." --------------------------------------------------------".$lf;
			echo $lf;
			echo $cmnt."".$lf;
			echo $cmnt.' TABLE '.$table.' STRUCTURE'.$lf;
			echo $cmnt."".$lf;
			echo $lf;
			
			if($PHP_EVAL_MODE)
				echo '$this->query("'.$lf;
				
			echo "DROP TABLE IF EXISTS ".$table.';'.$lf;
			
			if($PHP_EVAL_MODE)
				echo '");'.$lf;
				
			echo $lf;

			if($PHP_EVAL_MODE)
				echo '$this->query("'.$lf;
				
			echo $createtable[$table].';'.$lf;
			
			if($PHP_EVAL_MODE)
				echo '");'.$lf;
				
			echo $lf;
			
			$numRows = $this->getOne('SELECT COUNT(*) FROM '.$table);
			
			if($numRows){
				
				// за раз из таблицы извлекается 100 строчек
				$rowsPerIteration = 100;
				$numIterations = ceil($numRows / $rowsPerIteration);
				
				// извлечение названий полей
				$fields = array();
				foreach($this->getAll('DESCRIBE '.$table, array()) as $f)
					$fields[] = $f['Field'];
					
				for($i = 0; $i < $numIterations; $i++){
				
					$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());
					foreach($rows as $rowIndex => $row){
						foreach($row as $field => $cell){
							$cell = addslashes($cell);
							$cell = str_replace("\n", '\\r\\n', $cell);
							$cell = str_replace("\r", '', $cell);
							$row[$field] = "'".$cell."'";
						}
						$rows[$rowIndex] = $lf."\t(".implode(',', $row).")";
					}
				
					echo $cmnt.$lf;
					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;
					echo $cmnt.$lf;
					echo $lf;

					if($PHP_EVAL_MODE)
						echo '$this->query("'.$lf;
						
					echo "INSERT INTO ".$table." (".implode(', ', $fields).") VALUES ".implode(',', $rows).';'.$lf;
					
					if($PHP_EVAL_MODE)
						echo '");'.$lf;
						
					echo $lf;
				}
			}
		}
		echo $cmnt." ".$lf;
		echo $cmnt." END DATABASE DUMP".$lf;
		echo $cmnt." ".$lf;
		
		exit();
	}
	
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
	
	public function setError($error){
		$this->_error[] = $error;
	}
	
	public function getError(){
		return implode('<br />', $this->_error);
	}
	
	public function hasError(){
		return (bool)count($this->_error);
	}
	
	// ДЕСТРУКОТР
	public function __destruct(){
		
		if($this->_fileLog){
			$f = fopen(FS_ROOT.'logs/mysql.log','a');
			fwrite($f, '-------- '.date('Y-m-d H:i:s')." --------\n");
			fwrite($f, implode("\n", $this->_sqls));
			fwrite($f, "\n\n");
			fclose($f);
		}
	}
	
}

?>