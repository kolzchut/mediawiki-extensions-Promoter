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
		'Dror S. ([http://www.kolzchut.org.il Kol-Zchut]), based on [https://mediawiki.org/wiki/Extension:CentralNotice Extension:CentralNotice]',
	),
	'version'        => '15-Sep-2014',
	//'url'            => 'http://www.kolzchut.org.il/he/Project:Extensions/Promoter',
	'descriptionmsg' => 'promoter-desc',
	'license-name' => 'GPLv2',
);

$dir = __DIR__;

/* Configuration */

// Server-side banner cache timeout in seconds
$wgPromoterAdMaxAge = 600;

// Name of the fallback campaign
$wgPromoterFallbackCampaign = 'general';

/** @var $wgPromoterTabifyPages array Declare all pages that should be tabified as PR pages */
$wgPromoterTabifyPages = array(
	/* Left side 'namespace' tabs */
	'Promoter' => array(
		'type' => 'views',
		'message' => 'promoter-campaigns',
	),
	'PromoterAds' => array(
		'type' => 'views',
		'message' => 'promoter-ads',
	),
);


/* Setup */
require_once $dir . '/Promoter.hooks.php';
require_once $dir . '/Promoter.modules.php';

// Register message files
$wgMessagesDirs['Promoter'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Promoter'] = __DIR__ . "/Promoter.i18n.php";
$wgExtensionMessagesFiles[ 'PromoterAliases' ] = $dir . '/Promoter.alias.php';

// Register user rights
$wgAvailableRights[] = 'promoter-admin';
$wgGroupPermissions[ 'sysop' ][ 'promoter-admin' ] = true; // Only sysops can make changes
