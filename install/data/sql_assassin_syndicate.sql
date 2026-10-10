-- ============================================================
-- Assassin Syndicate / Brotherhood Database Tables
-- TravianZ Special Expansion
-- ============================================================

CREATE TABLE IF NOT EXISTS `%PREFIX%assassin_sanctuary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wref` int(11) NOT NULL,
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT 'Kuil Bayangan [Sanctuary]',
  `status` tinyint(2) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wref` (`wref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%PREFIX%assassin_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_uid` int(11) NOT NULL,
  `client_wid` int(11) NOT NULL,
  `target_wid` int(11) NOT NULL,
  `target_uid` int(11) NOT NULL,
  `target_name` varchar(64) NOT NULL DEFAULT '',
  `target_x` int(11) NOT NULL DEFAULT 0,
  `target_y` int(11) NOT NULL DEFAULT 0,
  `contract_type` varchar(32) NOT NULL,
  `cost_currency` varchar(16) NOT NULL,
  `cost_amount` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  `status` tinyint(2) NOT NULL DEFAULT 0,
  `result_summary` text NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_uid` (`client_uid`),
  KEY `target_wid` (`target_wid`),
  KEY `status` (`status`),
  KEY `end_time` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
