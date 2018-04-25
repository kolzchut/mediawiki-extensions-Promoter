-- Promoter Schema Install File
-- Last Update:
-- See documentation at [...]

CREATE TABLE IF NOT EXISTS /*_*/pr_campaigns (
  cmp_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cmp_name varchar(255) NOT NULL,
  cmp_enabled tinyint(1) NOT NULL DEFAULT '0',
  cmp_archived tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;


CREATE TABLE IF NOT EXISTS /*_*/pr_ads (
	ad_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	ad_name varchar(255),
  ad_title varchar(255) NOT NULL,
  ad_text text NOT NULL,
  ad_mainlink varchar(255),
  ad_display_anon tinyint(1) NOT NULL DEFAULT '1',
	ad_display_user tinyint(1) NOT NULL DEFAULT '1',
	ad_tag_new tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;


-- Cross-reference table between campaigns and ads
CREATE TABLE IF NOT EXISTS /*_*/pr_adlinks (
	adl_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  -- Ad to attach to a campaign... (Foreign Key to pr_ads).
  ad_id int(11) NOT NULL,
  -- AdCampaign the ad will belong to (Foreign Key to pr_campaigns).
  cmp_id int(11) NOT NULL,
	adl_weight int(11) NOT NULL
) /*$wgDBTableOptions*/;
