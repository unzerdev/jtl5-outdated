CREATE TABLE IF NOT EXISTS xplugin_unzercw_transactions (
	`transaction_id` bigint(20) NOT NULL AUTO_INCREMENT,
	`transaction_number` varchar(255) default NULL,
	`order_id` bigint(20) default NULL,
	`order_number` varchar(255) default NULL,
	`alias_for_display` varchar(255) default NULL,
	`alias_active` char(1) default 'y',
	`payment_method` varchar(255) NOT NULL,
	`order_status` char(2) default NULL,
	`transaction_object` MEDIUMTEXT,
	`authorization_type` varchar(255) NOT NULL,
	`customer_id` varchar(255) default NULL,
	`updated_on` datetime NOT NULL,
	`created_on` datetime NOT NULL,
	`payment_id` varchar(255) NOT NULL,
	`updatable` char(1) default 'n',
	`next_update_on` datetime NULL default NULL,
	`session_data` MEDIUMTEXT,
	PRIMARY KEY  (`transaction_id`)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE IF NOT EXISTS `xplugin_unzercw_storage` (
  `keyId` bigint(20) NOT NULL AUTO_INCREMENT,
  `keyName` varchar(165) DEFAULT NULL,
  `keySpace` varchar(165) DEFAULT NULL,
  `keyValue` longtext,
  PRIMARY KEY (`keyId`),
  UNIQUE KEY `keyName_keySpace` (`keyName`,`keySpace`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ;

CREATE TABLE IF NOT EXISTS xplugin_unzercw_customer_contexts (
	`context_id` bigint(20) NOT NULL AUTO_INCREMENT,
	`customer_id` bigint(20) NOT NULL,
	`context_object` text,
	`updated_on` datetime NOT NULL,
	`created_on` datetime NOT NULL,
	PRIMARY KEY  (`context_id`),
	UNIQUE (`customer_id`)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE IF NOT EXISTS xplugin_unzercw_constants (
	`constant_id` bigint(20) NOT NULL AUTO_INCREMENT,
	`constant_name` varchar(255) default NULL,
	`constant_value` text default NULL,
	`updated_on` datetime NULL default NULL,
	UNIQUE KEY (`constant_name`),
	PRIMARY KEY  (`constant_id`)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;