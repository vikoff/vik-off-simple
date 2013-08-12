<?php

class Config {

	/** @var Config */
	private static $_instance;

	protected $_data = array();

	/** инициализация экземпляра класса */
	public static function init()
	{
		if(self::$_instance)
			trigger_error('Объект класса Config уже инициализирован', E_USER_ERROR);

		self::$_instance = new Config();
	}

	/**
	 * получение экземпляра класса
	 * @param string|null $key - ключ конфигурации или null чтобы получить объект Config
	 * @return string|Config
	 */
	public static function get($key = null){

		return $key
			? self::$_instance->$key
			: self::$_instance;
	}

	protected function __construct()
	{
		$this->_loadFiles();
	}

	public function __get($key)
	{
		return isset($this->_data[$key])
			? $this->_data[$key]
			: null;
	}

	public function getAll()
	{
		return $this->_data;
	}

	protected function _loadFiles()
	{
		$globalConfig = require(FS_ROOT.'config.php');
		$localConfig = file_exists(FS_ROOT.'config.local.php')
			? require(FS_ROOT.'config.local.php')
			: array();

		if (!is_array($localConfig))
			$localConfig = array();

		$this->_data = array_replace_recursive($globalConfig, $localConfig);
//		$this->_data = $this->_arr2obj($this->_data);
	}


	protected function _arr2obj($arr) {
		foreach ($arr as $k => $v) {
			if (is_array($v)) {
				$arr[$k] = $this->_arr2obj($v);
			}
		}
		return (object)$arr;
	}


}
