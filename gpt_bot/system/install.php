<?php
/* GPT Bot add-on for Codychat 10.1 | Author: M Kashif | MIT License */
if(!defined('BOOM')){ die(); }
/* custom1 = on (1/0), custom2 = API key (filled automatically on first question), custom3 = lowest rank that can ask,
   custom4 = answers in rooms (1/0), custom5 = answers in private chat (1/0), custom6 = questions per member per minute,
   custom7 = site name the bot mentions, custom8 = extra trigger words, comma separated */
$ad = array(
	'name' => 'gpt_bot',
	'access' => 0,
	'bot_name' => 'GPT',
	'bot_type' => 1,
	'custom1' => '1', 'custom2' => '', 'custom3' => '0', 'custom4' => '1', 'custom5' => '1', 'custom6' => '4', 'custom7' => '', 'custom8' => '',
);
$mysqli->query("CREATE TABLE IF NOT EXISTS `gpt_bot_log` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`kind` TINYINT(1) NOT NULL,
	`src_id` INT(11) NOT NULL,
	`user_id` INT(11) NOT NULL,
	`room_id` INT(11) NOT NULL DEFAULT 0,
	`question` TEXT NOT NULL,
	`answer` TEXT NOT NULL,
	`status` TINYINT(1) NOT NULL DEFAULT 0,
	`err` VARCHAR(40) NOT NULL DEFAULT '',
	`created` INT(11) NOT NULL,
	PRIMARY KEY (`id`), UNIQUE KEY `uniq_src` (`kind`, `src_id`), KEY `idx_user` (`user_id`, `created`), KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$mysqli->query("CREATE TABLE IF NOT EXISTS `gpt_bot_brain` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`title` VARCHAR(100) NOT NULL DEFAULT '',
	`body` TEXT NOT NULL,
	`active` TINYINT(1) NOT NULL DEFAULT 1,
	`created` INT(11) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
?>
