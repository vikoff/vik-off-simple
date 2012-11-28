<?php

class DbAdapter_PdoMysql extends DbAdapter_PdoAbstract {

	protected function _getPdoInstance() {

		$options = array();

		if ($this->_encoding)
			$options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$this->_encoding}";

		$dsn = "mysql:host={$this->connHost}";
		if ($this->connDatabase)
			$dsn .= ";dbname={$this->connDatabase}";

		return new PDO($dsn, $this->connUser, $this->connPass, $options);
	}

	/** выбрать базу данных */
	public function selectDb($db) {

		$this->query('USE '.$db);
	}

    public function quoteFieldName($field){
        return "`$field`";
    }

	public function describe($table){

		return $this->fetchAll('DESCRIBE '.$table);
	}

	public function showTables(){

		return $this->fetchCol('SHOW TABLES');
	}

	public function showDatabases(){

		return $this->fetchCol('SHOW DATABASES');
	}

	public function showCreateTable($table){

		return $this->fetchCell("SHOW CREATE TABLE `$table`", 1);
	}

}
