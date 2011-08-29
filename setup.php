<?

if(!defined('WWW_ROOT'))
	die('access denided (setup file)');

require_once('func.php');
require_once('classes/Controller.class.php');
require_once('classes/FrontController.class.php');
require_once('classes/Layout.class.php');
require_once('classes/Db.class.php');

require_once('classes/DbAdapters/mysql.php');
require_once('classes/DbAdapters/postgres.php');
require_once('classes/DbAdapters/sqlite.php');

require_once('config.php');

// код для отсеивания дублирующихся форм
define('FORMCODE', getFormCodeInput());
