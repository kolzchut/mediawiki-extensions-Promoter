-- Promoter Schema Install File
-- Last Update:
-- See documentation at [...]

CREATE TABLE IF NOT EXISTS /*_*/pr_campaigns (
  cmp_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cmp_name varchar(255) NOT NULL,
  cmp_cat_page_id int unsigned NOT NULL,
  cmp_enabled tinyint(1) NOT NULL DEFAULT '0',
  cmp_archived tinyint(1) NOT NULL DEFAULT '0',
  cmp_use_general_ads tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;


CREATE TABLE IF NOT EXISTS /*_*/pr_ads (
	ad_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	ad_name varchar(255),
  ad_title varchar(255) NOT NULL,
  ad_text text NOT NULL,
  ad_mainlink varchar(255),
  ad_display_anon tinyint(1) NOT NULL DEFAULT '1',
	ad_display_user tinyint(1) NOT NULL DEFAULT '1'
) /*$wgDBTableOptions*/;


-- Cross-reference table between campaigns and ads
CREATE TABLE IF NOT EXISTS /*_*/pr_adlinks (
	adl_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  -- Ad to attach to a campaign... (Foreign Key to pr_ads).
  ad_id int(11) NOT NULL,
  -- Campaign the ad will belong to (Foreign Key to pr_campaigns).
  cmp_id int(11) NOT NULL,
	adl_weight int(11) NOT NULL
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/pr_campaign_log (
  cmplog_id int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cmplog_timestamp binary(14) NOT NULL,
  cmplog_user_id int(10) unsigned NOT NULL,
  cmplog_action enum('created','modified','removed') NOT NULL DEFAULT 'modified',
  cmplog_cmp_id int(10) unsigned NOT NULL,
  cmplog_cmp_name varchar(255) DEFAULT NULL,
  cmplog_begin_ads text,
  cmplog_end_ads text,
  cmplog_begin_weight int DEFAULT NULL,
  cmplog_end_weight int DEFAULT NULL,
  cmplog_begin_archived tinyint DEFAULT NULL,
  cmplog_end_archived tinyint DEFAULT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/cmplog_timestamp ON /*_*/pr_campaign_log (cmplog_timestamp);
CREATE INDEX /*i*/cmplog_user_id ON /*_*/pr_campaign_log (cmplog_user_id, cmplog_timestamp);
CREATE INDEX /*i*/cmplog_cmp_id ON /*_*/pr_campaign_log (cmplog_cmp_id, cmplog_timestamp);

CREATE TABLE IF NOT EXISTS /*_*/pr_ad_log (
  adlog_id int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  adlog_timestamp binary(14) NOT NULL,
  adlog_user_id int(10) unsigned NOT NULL,
  adlog_action enum('created','modified','removed') NOT NULL DEFAULT 'modified',
  adlog_ad_id int(10) unsigned NOT NULL,
  adlog_ad_name varchar(255) DEFAULT NULL,
  adlog_begin_anon tinyint(1) DEFAULT NULL,
  adlog_end_anon tinyint(1) DEFAULT NULL,
  adlog_begin_user tinyint(1) DEFAULT NULL,
  adlog_end_user tinyint(1) DEFAULT NULL,
  adlog_content_change tinyint(1) DEFAULT '0',
  adlog_begin_archived tinyint(1) DEFAULT NULL,
  adlog_end_archived tinyint(1) DEFAULT NULL,
  adlog_begin_preview_sandbox tinyint(1) DEFAULT NULL,
  adlog_end_preview_sandbox tinyint(1) DEFAULT NULL,
  adlog_begin_controller_mixin varbinary(4096) DEFAULT NULL,
  adlog_end_controller_mixin varbinary(4096) DEFAULT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/adlog_timestamp ON /*_*/pr_ad_log (adlog_timestamp);
CREATE INDEX /*i*/adlog_user_id ON /*_*/pr_ad_log (adlog_user_id, adlog_timestamp);
CREATE INDEX /*i*/adlog_ad_id ON /*_*/pr_ad_log (adlog_ad_id, adlog_timestamp);
