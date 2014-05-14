<?php
/**
 * WikiRights Promoter extension
 * Loosely based on https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * This file loads everything needed for the Promoter extension to function.
 *
 * @file
 * @ingroup Extensions
 * @license GNU General Public Licence 2.0 or later
 */

$wgExtensionCredits[ 'other' ][] = array(
	'path'           => __FILE__,
	'name'           => 'WikiRights Promoter',
	'author'         => array(
		'Dror S. ([http://www.kolzchut.org.il Kol-Zchut])',
		'...',
	),
	'version'        => '1.0.0',
	'url'            => '',
	'descriptionmsg' => 'wrpromoter-desc',
	'license-name' => 'GPLv2',
);

$dir = __DIR__;

/* Configuration */


/* Setup */
require_once $dir . '/Promoter.hooks.php';
//require_once $dir . '/Promoter.modules.php';

// Register message files
$wgMessagesDirs['Promoter'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Promoter'] = __DIR__ . "/Promoter.i18n.php";
$wgExtensionMessagesFiles[ 'WRPromoterAliases' ] = $dir . '/Promoter.alias.php';

// Register user rights
$wgAvailableRights[] = 'promoter-admin';
$wgGroupPermissions[ 'sysop' ][ 'promoter-admin' ] = true; // Only sysops can make changes
