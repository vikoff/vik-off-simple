-- <generation-skip>

/* ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ */
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
	`id` 			INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	`login`			VARCHAR(100) NOT NULL,
	`password`		VARCHAR(100) NOT NULL,
	`surname`		VARCHAR(255),
	`name`			VARCHAR(255),
	`patronymic`	VARCHAR(255),
	`gender`		CHAR(1),
	`birthdate` 	DATE,
	`country` 		VARCHAR(255),
	`city`		 	VARCHAR(255),
	`role_id`		SMALLINT,
	`profile`		TEXT,
	`last_login`	INT(10) UNSIGNED,
	`regdate`		INT(10) UNSIGNED
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* ТАБЛИЦА РОЛЕЙ */
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
	`id` 			INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	`title`			VARCHAR(255) NOT NULL,
	`level`			SMALLINT NOT NULL,
	`flag`			INT,
	`data`			TEXT
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* ТАБЛИЦА УПРАВЛЕНИЯ ДОСТУПОМ */
DROP TABLE IF EXISTS `user_acl`;
CREATE TABLE `user_acl` (
	`role_id`		INTEGER UNSIGNED NOT NULL,
	`module` 		CHAR(30),
	`resource` 		CHAR(30)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* СТАТИЧЕСКИЕ СТРАНИЦЫ */
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` 				INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` 			TEXT NOT NULL,
  `alias_id`		INT(10) UNSIGNED,
  `body` 			TEXT,
  `author` 			INT(10) UNSIGNED NOT NULL,
  `published` 		BOOLEAN DEFAULT FALSE,
  `type`			TINYINT,
  `format`			CHAR(10),
  `is_snippet`		BOOLEAN DEFAULT FALSE,
  `stored_in_file`	BOOLEAN DEFAULT FALSE,
  `meta_description` TEXT,
  `meta_keywords`	TEXT,
  `modif_date`		INT(10) UNSIGNED DEFAULT '0',
  `create_date`		INT(10) UNSIGNED DEFAULT '0',
  PRIMARY KEY (`id`),
  INDEX(`alias`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* СОХРАНЕНИЕ ОШИБОК */
DROP TABLE IF EXISTS `error_log`;
CREATE TABLE `error_log` (
  `id` 			INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uid`			INT(10) UNSIGNED,
  `url`			TEXT,
  `description` TEXT,
  `session_dump` TEXT,
  `hash`		CHAR(32),
  `occur_num`	INT(10) UNSIGNED DEFAULT 1,
  `firstdate` 	INT(10) UNSIGNED DEFAULT NULL,
  `lastdate` 	INT(10) UNSIGNED DEFAULT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* ПОЛЬЗОВАТЕЛЬСКАЯ СТАТИСТИКА */
DROP TABLE IF EXISTS `user_stat`;
CREATE TABLE `user_stat` (
  `id`              INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `uid`             INT(10) UNSIGNED DEFAULT 0,
  `user_ip`         VARCHAR(255),
  `referer`         VARCHAR(255),
  `user_agent_raw`  VARCHAR(255),
  `has_js`          BOOLEAN,
  `browser_name`    VARCHAR(50),
  `browser_version` VARCHAR(50),
  `screen_width`    SMALLINT UNSIGNED,
  `screen_height`   SMALLINT UNSIGNED,
  `date`            INT(10) UNSIGNED
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* СТРАНИЦЫ, ПОСЕЩЕННЫЕ ПОЛЬЗОВАТЕЛЯМИ */
DROP TABLE IF EXISTS `user_stat_pages`;
CREATE TABLE `user_stat_pages` (
  `id`           INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `session_id`   INT(10) UNSIGNED,
  `url`          TEXT,
  `is_ajax`      BOOLEAN,
  `is_post`      BOOLEAN,
  `post_data`    TEXT,
  `post_action`  TEXT,
  `first_date`   INT(10) UNSIGNED,
  `last_date`    INT(10) UNSIGNED,
  `num_requests` INT(10) UNSIGNED
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

/* ПСЕВДОНИМЫ */
DROP TABLE IF EXISTS `aliases`;
CREATE TABLE `aliases` (
  `id`				INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `alias`			VARCHAR(255),
  `path`			VARCHAR(255),
  `is_bound`		BOOLEAN /* флаг о том, что псевдоним связан с элементом по alias.id */
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- </generation-skip>

/*
 * ____
 * таблица зависит от: 
 * от таблицы зависят: 
 */
DROP TABLE IF EXISTS `___`;
CREATE TABLE `___` (
	`id` 			INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	`date`			INT(10) UNSIGNED
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
