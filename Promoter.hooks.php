<?php
/**
 * General hook definitions
 *
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
 *
 * @file
 * @ingroup Extensions
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

global $wgExtensionFunctions, $wgHooks;

$wgExtensionFunctions[] = 'efWikiRightsPromoterSetup';
$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'PRDatabasePatcher::applyUpdates';
$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = array( 'Promoter::addNavigationTabs' );

/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efWikiRightsPromoterSetup() {
	global $wgHooks, $wgAutoloadClasses, $wgSpecialPages,
		   $wgSpecialPageGroups, $wgAPIModules, $wgAPIListModules,
		   $wgScript;

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$apiDir = $dir . 'api/';
	$includeDir = $dir . 'includes/';
	$htmlFormDir = $includeDir . '/HtmlFormElements/';

	// Register files
	$wgAutoloadClasses[ 'Promoter' ] = $specialDir . 'SpecialPromoter.php';
	$wgAutoloadClasses[ 'Ad' ] = $includeDir . 'Ad.php';
	$wgAutoloadClasses[ 'AdDataException' ] = $includeDir . 'Ad.php';
	$wgAutoloadClasses[ 'AdContentException' ] = $includeDir . 'Ad.php';
	$wgAutoloadClasses[ 'AdExistenceException' ] = $includeDir . 'Ad.php';
	$wgAutoloadClasses[ 'AdMessage' ] = $includeDir . 'AdMessage.php';
	$wgAutoloadClasses[ 'AdChooser' ] = $includeDir . 'AdChooser.php';
	$wgAutoloadClasses[ 'AdRenderer' ] = $includeDir . 'AdRenderer.php';

	$wgAutoloadClasses[ 'SpecialPromoterAds' ] = $specialDir . 'SpecialPromoterAds.php';
	$wgAutoloadClasses[ 'SpecialAdLoader' ] = $specialDir . 'SpecialAdLoader.php';
	$wgAutoloadClasses[ 'SpecialAdRandom' ] = $specialDir . 'SpecialAdRandom.php';
	$wgAutoloadClasses[ 'PRAdPager' ] = $includeDir . 'PRAdPager.php';

	$wgAutoloadClasses[ 'HTMLPromoterAd' ] = $htmlFormDir . 'HTMLPromoterAd.php';
	$wgAutoloadClasses[ 'HTMLPromoterAdMessage' ] = $htmlFormDir . 'HTMLPromoterAdMessage.php';


	$wgAutoloadClasses[ 'Campaign' ] = $includeDir . 'Campaign.php';
	$wgAutoloadClasses[ 'AllocationContext' ] = $includeDir . 'AllocationContext.php';

	$wgAutoloadClasses[ 'PRDatabasePatcher' ] = $dir . 'patches/PRDatabasePatcher.php';
	$wgAutoloadClasses[ 'PRDatabase' ] = $includeDir . 'PRDatabase.php';

	$wgAutoloadClasses[ 'ApiPromoterAllocations' ] = $apiDir . 'ApiPromoterAllocations.php';
	$wgAutoloadClasses[ 'ApiPromoterQueryCampaign' ] = $apiDir . 'ApiPromoterQueryCampaign.php';
	//$wgAutoloadClasses[ 'ApiPromoterLogs' ] = $apiDir . 'ApiPromoterLogs.php';

	$wgAPIModules[ 'promoterallocations' ] = 'ApiPromoterAllocations';
	$wgAPIModules[ 'promoterquerycampaign' ] = 'ApiPromoterQueryCampaign';

	$wgAutoloadClasses[ 'AdPager' ] = $dir . 'AdPager.php';
	$wgAutoloadClasses[ 'PromoterPager' ] = $dir . 'PromoterPager.php';

	// Register hooks
		//$wgHooks[ 'MakeGlobalVariablesScript' ][ ] = 'efPromoterDefaults';
		$wgHooks[ 'BeforePageDisplay' ][ ] = 'efPromoterLoader';
		$wgHooks[ 'SkinHelenaSidebarButtons' ][ ] = 'efPromoterDisplay';
		//$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'efResourceLoaderGetConfigVars';

	// Register special pages
	$wgSpecialPages[ 'AdLoader' ] = 'SpecialAdLoader';
	$wgSpecialPages[ 'AdRandom' ] = 'SpecialAdRandom';

	$wgSpecialPages[ 'Promoter' ] = 'Promoter';
	$wgSpecialPageGroups[ 'Promoter' ] = 'wiki'; // Wiki data and tools
	$wgSpecialPages[ 'PromoterAds'] = 'SpecialPromoterAds';
}

/**
 * BeforePageDisplay hook handler
 * This function adds the banner controller
 *
 * @param $out  OutputPage
 * @param $skin Skin
 * @return bool
 */
function efPromoterLoader( $out, $skin ) {
	// Insert the ad controller
	$out->addModules( 'ext.promoter.adController' );
	return true;
}

/**
 * SkinHelenaSidebarButtons hook handler
 * This function outputs the ad div.
 *
 * @param $notice string
 * @return bool
 */
function efPromoterDisplay( &$skin, &$buttons ) {
	$buttons .= HTML::openElement( 'div', array( 'id' => 'sidebarPromotion' ) );
	$buttons .= '<div id="promoter"><header class="header"><span class="icon pull-right"></span>שאלה לגבי הפנסיה?</header>'
		. '<div class="content">באתר משרד הכלכלה תמצאו שאלות ותשובות בנושא חובת הפנסיה לעובדים במשק בית.<div class="mainlink">לפרטים נוספים...</div></div></div>';
	$buttons .= HTML::closeElement( 'div' );
	return true;
}
