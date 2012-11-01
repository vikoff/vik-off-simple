<?php

class Cmd {
	
	/**
	 * Метод разбирает переданные скрипту параметры в массив.
	 * @param  array $scheme массив именованых параметров
	 * @return array разобранный массив параметров
	 */
	public static function parseArgs($scheme) {
		global $argv;

		$argsParsed = array();
		$lastKey = null;
		$lastIndex = 0;
		foreach (array_slice($argv, 1) as $v) {
			if ($lastKey) {
				$argsParsed[$lastKey] = $v;
				$lastKey = null;
			} else {
				if ($v{0} === '-')
					$lastKey = $v;
				else
					$argsParsed[++$lastIndex] = $v;
			}
		}

		foreach ($scheme as $key) {
			$trgKey = null;
			if (isset($argsParsed['--'.$key]))
				$trgKey = '--'.$key;
			elseif (isset($argsParsed['-'.$key{0}]))
				$trgKey = '-'.$key{0};
			if ($trgKey) {
				$argsParsed[$key] = $argsParsed[$trgKey];
				unset($argsParsed[$trgKey]);
			}
		}

		return $argsParsed;
	}

	public static function printLn($text) {
		echo date('Y-m-d H:i:s').' '.$text."\n";
	}
	
	public static function confirm($text, $default = null) {

		$y = $default === true ? 'Y' : 'y';
		$n = $default === false ? 'N' : 'n';
		$result = strtolower(self::readLn("$text [$y/$n]"));
		if (!strlen($result)) 
			return !is_null($default) ? $default : false;
		else
			return in_array($result, array('y', 'yes', 'д', 'да'));
	}
	
	public static function readLn($text) {
		echo  $text.': ';
		return trim(fgets(STDIN));
	}
}
 
