<?php
session_start();

// обозначение корня ресурса
define('CLI_MODE', PHP_SAPI == 'cli');
if (CLI_MODE) {
	define('WWW_ROOT', '');
	define('WWW_URI', '');
} else {
	$_url = dirname($_SERVER['SCRIPT_NAME']);
	define('WWW_ROOT', 'http://'.$_SERVER['SERVER_NAME'].(strlen($_url) > 1 ? $_url : '').'/');
	define('WWW_URI', 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
}
define('FS_ROOT', dirname(__FILE__).'/');

// определение ajax-запроса
define('AJAX_MODE', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// отправка Content-type заголовка
header('Content-Type: text/html; charset=utf-8');

chdir(FS_ROOT);

function __autoload($name) {

	$filename = FS_ROOT.'classes/'.$name.'.class.php';
	require($filename);
}

require_once('func.php');

require_once('classes/Db.class.php');
require_once('classes/DbAdapters/PdoAbstract.php');
require_once('classes/DbAdapters/PdoMysql.php');

Config::init();

// создание подключения к БД
db::create(Config::get('db'));

// код для отсеивания дублирующихся форм
define('FORMCODE', getFormCodeInput());

// выполнение приложения
if (CLI_MODE)
	FrontController::get()->run_cli();
elseif(AJAX_MODE)
	FrontController::get()->run_ajax();
else
	FrontController::get()->run();
