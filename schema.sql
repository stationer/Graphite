--
-- Current Schema CREATE statements go here
-- 

 -- \Stationer\Graphite\models\Login
DROP TABLE IF EXISTS `Login`;
CREATE TABLE IF NOT EXISTS `Login` (
    `login_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `created_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `updated_dts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `edited_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `active_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `login_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `logout_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `loginname` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `realname` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `comment` varchar(255) NOT NULL,
    `UA` varchar(255) NOT NULL,
    `lastIP` int(10) unsigned NOT NULL DEFAULT 0,
    `referrer_id` int(10) unsigned NOT NULL DEFAULT 0,
    `disabled` bit(1) NOT NULL DEFAULT b'0',
    `flagChangePass` bit(1) NOT NULL DEFAULT b'1',
    KEY (`updated_dts`),
    PRIMARY KEY(`login_id`)
);


 -- \Stationer\Graphite\models\LoginLog
DROP TABLE IF EXISTS `LoginLog`;
CREATE TABLE IF NOT EXISTS `LoginLog` (
    `loginlog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `created_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `updated_dts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `login_id` int(10) unsigned NOT NULL DEFAULT 0,
    `ip` int(10) unsigned NOT NULL DEFAULT 0,
    `ua` varchar(255) NOT NULL,
    `login_uts` int(10) unsigned NOT NULL DEFAULT 0,
    KEY (`updated_dts`),
    PRIMARY KEY(`loginlog_id`)
);


 -- \Stationer\Graphite\models\Role
DROP TABLE IF EXISTS `Role`;
CREATE TABLE IF NOT EXISTS `Role` (
    `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `created_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `updated_dts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `creator_id` int(10) unsigned NOT NULL DEFAULT 0,
    `label` varchar(255) NOT NULL,
    `description` varchar(255) NOT NULL,
    `disabled` bit(1) NOT NULL DEFAULT b'0',
    KEY (`updated_dts`),
    PRIMARY KEY(`role_id`)
);

DROP TABLE IF EXISTS `Role_Login`;
CREATE TABLE `Role_Login` (
    `role_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
    `login_id` smallint(5) unsigned NOT NULL DEFAULT '0',
    `created_uts` int(10) unsigned NOT NULL DEFAULT 0,
    `updated_dts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `grantor_id` smallint(5) unsigned NOT NULL DEFAULT '0',
    KEY `updated_dts` (`updated_dts`),
    UNIQUE KEY `login_id` (`login_id`,`role_id`),
    PRIMARY KEY (`role_id`,`login_id`)
)
