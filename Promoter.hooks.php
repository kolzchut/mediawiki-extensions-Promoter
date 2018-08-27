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

class PromoterHooks {

	/**
	 * ResourceLoaderGetConfigVars hook handler
	 * Send php config vars to js via ResourceLoader
	 *
	 * @param &$vars : variables to be added to the output of the startup module
	 *
	 * @return bool
	 * @throws MWException
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgPromoterTrackAds, $wgPromoterAdDispatcher;

		$trackAdsDefault = $wgPromoterTrackAds === true ? true : false;
		$vars['wgPromoter'] = [
			'trackAdClicks' => $trackAdsDefault,
			'trackAdViews' => $trackAdsDefault
		];

		if ( isset( $wgPromoterTrackAds ) && is_array( $wgPromoterTrackAds ) ) {
			if ( isset( $wgPromoterTrackAds['click'] ) && ( $wgPromoterTrackAds['click'] === true ) ) {
				$vars['wgPromoter']['trackAdClicks'] = true;
			}
			if ( isset( $wgPromoterTrackAds['view'] ) && ( $wgPromoterTrackAds['view'] === true ) ) {
				$vars['wgPromoter']['trackAdViews'] = true;
			}
		}

		// Making these calls too soon will causes issues with the namespace localisation cache.
		// This seems to be just right. We require them at all because MW will 302 page requests
		// made to non localised  namespaces which results in wasteful extra calls.
		if ( !$wgPromoterAdDispatcher ) {
			$wgPromoterAdDispatcher = SpecialPage::getTitleFor( 'AdRandom' )->getFullURL();
		}
		$vars[ 'wgPromoterAdDispatcher' ] = $wgPromoterAdDispatcher;

		return true;
	}

	/**
	 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
	 */
	static function efWikiRightsPromoterSetup() {
		global $wgHooks, $wgAutoloadClasses, $wgSpecialPages,
		       $wgSpecialPageGroups;

		$dir = __DIR__ . '/';
		$specialDir = $dir . 'special/';
		$includeDir = $dir . 'includes/';
		$htmlFormDir = $includeDir . '/HtmlFormElements/';

		// Register files
		$wgAutoloadClasses[ 'Promoter' ] = $specialDir . 'SpecialPromoter.php';
		$wgAutoloadClasses[ 'Ad' ] = $includeDir . 'Ad.php';
		$wgAutoloadClasses[ 'AdDataException' ] = $includeDir . 'Ad.php';
		$wgAutoloadClasses[ 'AdExistenceException' ] = $includeDir . 'Ad.php';
		$wgAutoloadClasses[ 'AdMessage' ] = $includeDir . 'AdMessage.php';
		$wgAutoloadClasses[ 'AdChooser' ] = $includeDir . 'AdChooser.php';
		$wgAutoloadClasses[ 'AdRenderer' ] = $includeDir . 'AdRenderer.php';

		$wgAutoloadClasses[ 'SpecialPromoterAds' ] = $specialDir . 'SpecialPromoterAds.php';
		$wgAutoloadClasses[ 'SpecialAdLoader' ] = $specialDir . 'SpecialAdLoader.php';
		$wgAutoloadClasses[ 'SpecialAdRandom' ] = $specialDir . 'SpecialAdRandom.php';
		$wgAutoloadClasses[ 'SpecialCampaignAdsLoader' ] = $specialDir . 'SpecialCampaignAdsLoader.php';

		$wgAutoloadClasses[ 'AdLoaderException' ] = $specialDir . 'SpecialAdLoader.php';
		$wgAutoloadClasses[ 'EmptyAdException' ]  = $specialDir . 'SpecialAdLoader.php';
		$wgAutoloadClasses[ 'AdLoaderMissingRequiredParamsException' ] =
			$specialDir . 'SpecialAdLoader.php';

		$wgAutoloadClasses[ 'PRAdPager' ] = $includeDir . 'PRAdPager.php';

		$wgAutoloadClasses[ 'HTMLPromoterAd' ] = $htmlFormDir . 'HTMLPromoterAd.php';
		$wgAutoloadClasses[ 'HTMLPromoterAdMessage' ] = $htmlFormDir . 'HTMLPromoterAdMessage.php';

		$wgAutoloadClasses[ 'AdCampaign' ] = $includeDir . 'AdCampaign.php';
		$wgAutoloadClasses[ 'AllocationContext' ] = $includeDir . 'AllocationContext.php';

		$wgAutoloadClasses[ 'PRDatabasePatcher' ] = $dir . 'patches/PRDatabasePatcher.php';
		$wgAutoloadClasses[ 'PRDatabase' ] = $includeDir . 'PRDatabase.php';

		$wgAutoloadClasses[ 'AdPager' ] = $dir . 'AdPager.php';
		$wgAutoloadClasses[ 'PromoterPager' ] = $dir . 'PromoterPager.php';

		$wgAutoloadClasses[ 'PromoterGallery' ] = $dir . 'PromoterGallery.php';

		// Register hooks
		// $wgHooks[ 'MakeGlobalVariablesScript' ][ ] = 'efPromoterDefaults';
		$wgHooks[ 'BeforePageDisplay' ][ ] = 'PromoterHooks::efPromoterLoader';
		$wgHooks[ 'SkinHelenaSidebar::End' ][ ] = 'PromoterHooks::efPromoterDisplay';
		$wgHooks['ParserFirstCallInit'][] = 'PromoterGallery::onParserFirstCallInit';
		$wgHooks['ResourceLoaderGetConfigVars'][] = 'PromoterHooks::onResourceLoaderGetConfigVars';
		$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'PRDatabasePatcher::applyUpdates';
		$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = [ 'Promoter::addNavigationTabs' ];

		// Register special pages
		$wgSpecialPages[ 'AdLoader' ] = 'SpecialAdLoader';
		$wgSpecialPages[ 'AdRandom' ] = 'SpecialAdRandom';
		$wgSpecialPages[ 'CampaignAdsLoader' ] = 'SpecialCampaignAdsLoader';

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
	static function efPromoterLoader( $out, $skin ) {
		// Insert the ad controller
		$out->addModules( 'ext.promoter.adController' );
		return true;
	}

	/**
	 * SkinHelenaSidebar::End hook handler
	 * This function outputs the ad wrapper div.
	 *
	 * @param $skin
	 * @return bool
	 */
	static function efPromoterDisplay( &$skin ) {
		echo Html::element( 'li', [ 'id' => 'sidebar-promotion' ] );
		return true;
	}
}




