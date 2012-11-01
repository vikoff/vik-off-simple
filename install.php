<?php

if (PHP_SAPI != 'cli')
	exit('command line run only!');

define('FS_ROOT', dirname(__FILE__).'/');
require_once(FS_ROOT.'setup.php');
require_once(FS_ROOT.'classes/Cmd.class.php');

function skipExcess($filename) {

	$newContent = array();
	$skipping = false;

	foreach (file($filename) as $row) {
		if (strpos($row, '<generation-skip>') !== FALSE)
			$skipping = TRUE;
		elseif (strpos($row, '</generation-skip>') !== FALSE)
			$skipping = FALSE;
		elseif (!$skipping)
			$newContent[] = $row;
	}

	file_put_contents($filename, implode('', $newContent));
}

echo "This script helps you to create new application skeleton\n";

$args = Cmd::parseArgs(array('dir'));

if (!isset($args['dir']))
	$args['dir'] = Cmd::readLn('Enter target directory');

if (empty($args['dir']))
	exit("ERROR: target dir is not specified\n");
elseif (!is_dir($args['dir']))
	exit("ERROR: target dir is not exists\n");
elseif (!is_writeable($args['dir']))
	exit("ERROR: target dir is not writeable\n");
elseif (count(scandir($args['dir'])) > 2)
	exit("ERROR: target dir is not empty\n");

$trgDir = rtrim($args['dir'], '/').'/';

echo "copy root files\n";
foreach (array('config.php', 'func.php', 'index.php', 'setup.php') as $tpl)
	copy(FS_ROOT.$tpl, $trgDir.$tpl);

echo "copy classes\n";
exec('cp -r '.FS_ROOT.'classes '.escapeshellarg($trgDir));

echo "copy templates\n";
mkdir($trgDir.'templates');
foreach (array('layout.php', 'index.php') as $tpl)
	copy(FS_ROOT.'templates/'.$tpl, $trgDir.'templates/'.$tpl);

echo "copy _sql\n";
exec('cp -r '.FS_ROOT.'_sql '.escapeshellarg($trgDir));

echo "clear excess content\n";
foreach (array('classes/FrontController.class.php', 'templates/index.php', '_sql/structure.sql') as $file)
	skipExcess($trgDir.$file);

echo "\nCOMPLETE!\n";


