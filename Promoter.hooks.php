<?php
/**
 * General hook definitions
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
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

/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efWikiRightsPromoterSetup() {
	global $wgHooks, $wgAutoloadClasses, $wgSpecialPages,
		   $wgSpecialPageGroups, $wgScript;

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$includeDir = $dir . 'includes/';
	$htmlFormDir = $includeDir . '/HtmlFormElements/';

	// Register files
	$wgAutoloadClasses[ 'Promoter' ] = $specialDir . 'SpecialPromoter.php';
	/*
	$wgAutoloadClasses[ 'Banner' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerDataException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerContentException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerExistenceException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerMessage' ] = $includeDir . 'BannerMessage.php';
	$wgAutoloadClasses[ 'BannerChooser' ] = $includeDir . 'BannerChooser.php';
	$wgAutoloadClasses[ 'BannerRenderer' ] = $includeDir . 'BannerRenderer.php';
	*/
	$wgAutoloadClasses[ 'Campaign' ] = $includeDir . 'Campaign.php';

	$wgAutoloadClasses[ 'PRDatabasePatcher' ] = $dir . 'patches/PRDatabasePatcher.php';
	$wgAutoloadClasses[ 'PRDatabase' ] = $includeDir . 'PRDatabase.php';


	$wgAutoloadClasses[ 'TemplatePager' ] = $dir . 'TemplatePager.php';
	$wgAutoloadClasses[ 'PromoterPager' ] = $dir . 'PromoterPager.php';

	// Register special pages
	$wgSpecialPages[ 'Promoter' ] = 'Promoter';
	$wgSpecialPageGroups[ 'Promoter' ] = 'wiki'; // Wiki data and tools

}
