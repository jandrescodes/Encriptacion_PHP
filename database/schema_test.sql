-- Schema de tablas para la base de datos de prueba (sin CREATE DATABASE / USE).
-- Usado por tests/TestCase.php::applySchema().

CREATE TABLE IF NOT EXISTS `users` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name`  varchar(100) NOT NULL,
  `email`      varchar(100) NOT NULL DEFAULT '',
  `username`   varchar(50)  NOT NULL,
  `password`   varchar(255) NOT NULL,
  `is_admin`               tinyint(1)   NOT NULL DEFAULT 0,
  `remember_token`         varchar(64)           NULL DEFAULT NULL,
  `remember_token_expires` datetime              NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email`          (`email`),
  UNIQUE KEY `username`       (`username`),
  KEY `idx_remember_token`    (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `identifier`   varchar(100) NOT NULL,
  `attempts`     tinyint(4)   NOT NULL DEFAULT 0,
  `locked_until` datetime              NULL DEFAULT NULL,
  `last_attempt` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `email`      varchar(255) NOT NULL,
  `token`      varchar(255) NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp    NULL     DEFAULT NULL,
  `used`       tinyint(1)            DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)               NULL DEFAULT NULL,
  `event`       varchar(50)  NOT NULL,
  `description` varchar(255) NOT NULL,
  `ip_address`  varchar(45)           NULL DEFAULT NULL,
  `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_created_at` (`created_at`),
  KEY `idx_activity_logs_user_id`    (`user_id`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)      NOT NULL,
  `token_hash`    char(64)     NOT NULL,
  `ip_address`    varchar(45)           NULL DEFAULT NULL,
  `user_agent`    varchar(255)          NULL DEFAULT NULL,
  `via_remember`  tinyint(1)   NOT NULL DEFAULT 0,
  `created_at`    datetime     NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_token_hash`   (`token_hash`),
  KEY `idx_session_user`            (`user_id`),
  KEY `idx_session_last_activity`   (`last_activity`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
