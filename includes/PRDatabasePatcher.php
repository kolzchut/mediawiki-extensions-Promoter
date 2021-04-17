<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Promoter;

use DatabaseUpdater;

/**
 * Maintenance helper class that updates the database schema when required.
 *
 * Apply patches with /maintenance/update.php
 */
class PRDatabasePatcher {
	/**
	 * LoadExtensionSchemaUpdates hook handler
	 * This function makes sure that the database schema is up to date.
	 *
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function applyUpdates( $updater = null ) {
		$base = __DIR__ . '../patches';

		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate(
				[
					 'addTable', 'pr_campaigns',
					 $base . '/Promoter.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ads', 'ad_tag_new',
					$base . '/pr_ads.patch.ad_tag_new.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ad_log', 'adlog_begin_new',
					$base . '/pr_ad_log.patch.adlog_begin_new.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ad_log', 'adlog_end_new',
					$base . '/pr_ad_log.patch.adlog_end_new.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ads', 'ad_date_start',
					$base . '/pr_ads.patch.ad_date_start.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ads', 'ad_date_end',
					$base . '/pr_ads.patch.ad_date_end.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ads', 'ad_active',
					$base . '/pr_ads.patch.ad_active.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ad_log', 'adlog_begin_active',
					$base . '/pr_ad_log.patch.adlog_begin_active.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'pr_ad_log', 'adlog_end_active',
					$base . '/pr_ad_log.patch.adlog_end_active.sql', true
				]
			);
		}
		return true;
	}
}
