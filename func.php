<?php

if(!defined('FS_ROOT'))
	die('access denided (func file)');

/** ФУНКЦИЯ GETVAR */
function getVar(&$varname, $defaultVal = '', $type = ''){

	if(!isset($varname))
		return $defaultVal;
	
	if(strlen($type))
		settype($varname, $type);
	
	return $varname;
}

function href($href){
	$href = str_replace('?', '&', $href);
	return 'index.php'.(!empty($href) ? '?r='.$href : '');
}

/** RELOAD */
function reload(){

	$url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	header('location: '.$url);
	exit();
}

/** REDIRECT */
function redirect($href){
	
	header('location: '.href($href));
	exit();
}

// ПОЛУЧИТЬ HTML INPUT СОДЕРЖАЩИЙ FORMCODE
function getFormCodeInput(){

	if(!isset($_SESSION['su']['userFormChecker']))
		$_SESSION['su']['userFormChecker'] = array('current' => 0, 'used' => array());
	
	$_SESSION['su']['userFormChecker']['current']++;
	return '<input type="hidden" name="formCode" value="'.$_SESSION['su']['userFormChecker']['current'].'" />';
}

// ПРОВЕРКА ВАЛИДНОСТИ ФОРМЫ
function checkFormDuplication(){
	
	if(isset($_POST['allowDuplication']))
		return TRUE;
		
	if(!isset($_POST['formCode'])){
		trigger_error('formCode не передан', E_USER_ERROR);
		return FALSE;
	}
	$formcode = (int)$_POST['formCode'];
	
	if(!Config::get('check_form_duplication'))
		return TRUE;
	
	if(!$formcode)
		return FALSE;
		
	if(!isset($_SESSION['userFormChecker']['used']))
		return TRUE;
		
	return !isset($_SESSION['userFormChecker']['used'][$formcode]);
}

// ПОМЕТИТЬ FORMCODE ИСПОЛЬЗОВАННЫМ
function lockFormCode(&$code){

	if(Config::get('check_form_duplication') && !empty($code))
		$_SESSION['userFormChecker']['used'][$code] = 1;
}
