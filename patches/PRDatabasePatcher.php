<?php
/**
 * @file
 * @license GNU General Public Licence 2.0 or later
 */

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
	 * @param $updater DatabaseUpdater|null
	 * @return bool
	 */
	public static function applyUpdates( 	$updater = null ) {
		$base = __DIR__;

		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'pr_campaigns',
					 $base . '/../Promoter.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					'addField', 'pr_ads', 'ad_active',
					$base . '/pr_ads.patch.ad_active.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					'addField', 'pr_ad_log', 'adlog_begin_active',
					$base . '/pr_ad_log.patch.adlog_begin_active.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					'addField', 'pr_ad_log', 'adlog_end_active',
					$base . '/pr_ad_log.patch.adlog_end_active.sql', true
				)
			);
		}
		return true;
	}
}
