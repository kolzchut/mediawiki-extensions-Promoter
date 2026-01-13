<?php

namespace MediaWiki\Extension\Promoter;

/**
 * Utility functions for Promoter that don't belong elsewhere
 */
class PRDatabase {
	/**
	 * Gets a database object. Will be the primary if the user is logged in.
	 *
	 * @param int|bool $force If false will return a DB primary/replica based on users permissions.
	 *                             Set to DB_PRIMARY or DB_REPLICA to force that type.
	 * @param string|bool $wiki Wiki database to connect to, if false will be the Infrastructure DB
	 *
	 * @return \DatabaseBase
	 */
	public static function getDb( $force = false, $wiki = false ) {
		global $wgUser;

		if ( $wgUser->isAllowed( 'promoter-admin' ) ) {
			$dbmode = DB_PRIMARY;
		} elseif ( $force === false ) {
			$dbmode = DB_REPLICA;
		} else {
			$dbmode = $force;
		}

		// $db = ( $wiki === false ) ? $wgCentralDBname : $wiki;
		// return wfGetDB( $dbmode, [], $db );

		return wfGetDB( $dbmode );
	}
}
