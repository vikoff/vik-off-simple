<?
session_start();

// обозначение корня ресурса
$_url = dirname($_SERVER['SCRIPT_NAME']);
define('WWW_ROOT', 'http://'.$_SERVER['SERVER_NAME'].(strlen($_url) > 1 ? $_url : '').'/');
define('WWW_URI', 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
define('FS_ROOT', realpath('.').DIRECTORY_SEPARATOR);

// определение ajax-запроса
define('AJAX_MODE', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// отправка Content-type заголовка
header('Content-Type: text/html; charset=utf-8');

// подключение файлов CMF
require_once(FS_ROOT.'setup.php');

// выполнение приложения
if(AJAX_MODE)
	FrontController::get()->ajax();
else
	FrontController::get()->run();
