<?
if(!defined('FS_ROOT'))
	die('access denided (setup file)');

require_once('func.php');
require_once('classes/Controller.class.php');
require_once('classes/FrontController.class.php');
require_once('classes/Layout.class.php');
require_once('classes/Db.class.php');

require_once('classes/DbAdapters/PdoAbstract.php');
require_once('classes/DbAdapters/PdoSqlite.php');
require_once('classes/DbAdapters/PdoMysql.php');
require_once('classes/DbAdapters/Mysql.php');

require_once('config.php');

// код для отсеивания дублирующихся форм
define('FORMCODE', getFormCodeInput());
