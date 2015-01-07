CREATE TABLE IF NOT EXISTS civicrm_gift_membership_codes (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT "Code Id",
  code varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT "Actual Gift Membership Code",
  contribution_id int(10) unsigned NOT NULL COMMENT "",
  membership_id int(10) unsigned NULL COMMENT "",
  membership_type int(4) unsigned NULL COMMENT "The membership type this gift is allowed to purchase",
  giver_id int(10) unsigned NULL COMMENT "Contact ID of giver",
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS civicrm_gift_membership_price_fields (
  pfid int(10) unsigned NOT NULL COMMENT "Price Field Id",
  membership_type_id int(10) unsigned NOT NULL COMMENT "Membership Type",
  PRIMARY KEY (`pfid`),
  UNIQUE KEY (`pfid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;