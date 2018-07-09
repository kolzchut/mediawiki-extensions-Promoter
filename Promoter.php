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

$wgExtensionCredits[ 'other' ][] = [
	'path'           => __FILE__,
	'name'           => 'WikiRights Promoter',
	'author'         => [
		'Dror S. [FFS] ([http://www.kolzchut.org.il Kol-Zchut])',
		'based on [https://mediawiki.org/wiki/Extension:CentralNotice Extension:CentralNotice]'
	],
	'version'        => '2017-08-07',
	'url'            => 'https://github.com/kolzchut/mediawiki-extensions-Promoter',
	'descriptionmsg' => 'promoter-desc',
	'license-name' => 'GPL-2.0+',
];

/* Configuration */

// Server-side banner cache timeout in seconds
$wgPromoterAdMaxAge = 600;

$wgPromoterTrackAds = [
	'view' => true,
	'click' => true
];

// Name of the fallback campaign
$wgPromoterFallbackCampaign = 'general';

/** @var $wgPromoterTabifyPages array Declare all pages that should be tabified as PR pages */
$wgPromoterTabifyPages = [
	/* Left side 'namespace' tabs */
	'Promoter' => [
		'type' => 'views',
		'message' => 'promoter-campaigns',
	],
	'PromoterAds' => [
		'type' => 'views',
		'message' => 'promoter-ads',
	],
];

/* Setup */
require_once __DIR__ . '/Promoter.hooks.php';
require_once __DIR__ . '/Promoter.modules.php';

// Register message files
$wgMessagesDirs['Promoter'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles[ 'PromoterAliases' ] = __DIR__ . '/Promoter.alias.php';

// Register user rights
$wgAvailableRights[] = 'promoter-admin';
$wgGroupPermissions[ 'sysop' ][ 'promoter-admin' ] = true; // Only sysops can make changes

/* Hooks */
$wgExtensionFunctions[] = 'PromoterHooks::efWikiRightsPromoterSetup';
$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'PRDatabasePatcher::applyUpdates';
$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = [ 'Promoter::addNavigationTabs' ];

