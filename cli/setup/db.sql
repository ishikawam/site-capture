# DB生成 初期のみ

CREATE USER capture@localhost;

GRANT CREATE ROUTINE, CREATE VIEW, ALTER, SHOW VIEW, CREATE, ALTER ROUTINE, EVENT, INSERT, SELECT, DELETE, TRIGGER, REFERENCES, UPDATE, DROP, EXECUTE, LOCK TABLES, CREATE TEMPORARY TABLES, INDEX ON `capture`.* TO 'capture'@'localhost';

CREATE DATABASE `capture`;

FLUSH PRIVILEGES;


# capture
CREATE TABLE `capture` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2083) DEFAULT NULL COMMENT 'ドメイントップだけでない。クエリも可？',
  `type` varchar(16) NOT NULL DEFAULT '' COMMENT 'phantom or slimer',
  `user_agent` varchar(256) DEFAULT NULL,
  `har` text COMMENT 'phantom only',
  `content` text COMMENT 'phantom only',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'BANするため',
  PRIMARY KEY (`id`),
  KEY `url` (`url`(255),`type`,`user_agent`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# queue_phantom
CREATE TABLE `queue_phantom` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2083) DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `user_agent` varchar(256) DEFAULT NULL,
  `zoom` tinyint(3) unsigned DEFAULT NULL,
  `resize` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'BANするため',
  `status` varchar(10) NOT NULL DEFAULT '' COMMENT 'error, none, busy',
  `priority` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `url` (`url`(255),`width`,`height`,`user_agent`(255),`zoom`,`resize`),
  KEY `status` (`status`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# queue_slimer
CREATE TABLE `queue_slimer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2083) DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `user_agent` varchar(256) DEFAULT NULL,
  `zoom` tinyint(3) unsigned DEFAULT NULL,
  `resize` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'BANするため',
  `status` varchar(10) NOT NULL DEFAULT '' COMMENT 'error, none, busy',
  `priority` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `url` (`url`(255),`width`,`height`,`user_agent`(255),`zoom`,`resize`),
  KEY `status` (`status`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
