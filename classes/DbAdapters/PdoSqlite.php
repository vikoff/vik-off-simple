<?php

class DbAdapter_PdoSqlite extends DbAdapter_PdoAbstract {

	protected function _getPdoInstance() {
		return new PDO("sqlite:{$this->connDatabase}");
	}

	public function selectDb($db){}

    public function quoteFieldName($field){
        return '"'.$field.'"';
    }

	public function insertMulti($table, $fields, $valuesRows){

		foreach ($valuesRows as $row)
			$this->insert($table, array_combine($fields, $row));
	}

	public function truncate($table){

		$this->query('DELETE FROM '.$table);
	}

	public function describe($table){

		return $this->fetchAll('PRAGMA table_info('.$table.')');
	}

	public function showTables(){

		return $this->fetchCol('SELECT name FROM sqlite_master WHERE type = "table"');
	}

	public function showDatabases(){

		return array($this->connDatabase);
	}

	public function showCreateTable($table){

		return $this->fetchOne('SELECT sql FROM sqlite_master WHERE type = "table" AND name= "'.$table.'"');
	}

}
