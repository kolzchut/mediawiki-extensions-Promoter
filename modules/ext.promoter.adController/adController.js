/**
 * JS that is loaded onto every MW page to load ads.
 *
 * This file is part of the Promoter Extension to MediaWiki, based on CentralNotice
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
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
 *
 * @file
 */
( function () {
	var rPlus = /\+/g,
		adController;
	function decode( s ) {
		try {
			// decodeURIComponent can throw an exception for unknown char encodings.
			return decodeURIComponent( s.replace( rPlus, ' ' ) );
		} catch ( e ) {
			return '';
		}
	}

	mw.promoter = mw.promoter || {};
	adController = mw.promoter.adController = {
		/**
		 * Promoter Required Data
		 */
		data: {
			getVars: {},
			testing: false
		},
		config: {},

		containerElement: '#sidebar-promotion',

		/**
		 * Custom data that the ad can play with
		 */
		adData: {},

		/**
		 * Contains promise objects that other things can hook into
		 */
		events: {},

		/**
		 * State variable used in initialize() to prevent it from running more than once
		 * @private
		 */
		isInitialized: false,

		/**
		 * Deferred objects that link into promises in mw.promoter.adController.events
		 */
		deferredObjs: {},

		/** -- Functions! -- **/
		loadAd: function () {
			if ( adController.data.getVars.ad ) {
				// If we're forcing one ad
				adController.loadTestingAd( adController.data.getVars.ad, 'testing' );
			} else {
				adController.loadRandomAd();
			}
		},
		loadTestingAd: function ( adName, campaign ) {
			var adPageQuery;

			adController.data.testing = true;

			// Get the requested ad
			adPageQuery = {
				title: 'Special:AdLoader',
				ad: adName,
				campaign: campaign,
				debug: adController.data.getVars.debug
			};

			$.ajax( {
				url: mw.config.get( 'wgScriptPath' ) + '?' + $.param( adPageQuery ),
				dataType: 'script',
				cache: true
			} );
		},
		loadRandomAd: function () {
			var wgCategories = mw.config.get( 'wgCategories' ),
				wrMainCategory = ( wgCategories.length > 1 ) ? wgCategories[ '1' ] : null,
				adDispatchQuery = {
					anonymous: mw.config.get( 'wgUserName' ) === null,
					campaign: wrMainCategory,
					debug: adController.data.getVars.debug
				},
				scriptUrl = mw.config.get( 'wgPromoterAdDispatcher' ) + '?' + $.param( adDispatchQuery );

			$.ajax( {
				url: scriptUrl,
				dataType: 'script',
				cache: true
			} );
		},
		insertAd: function ( adJson ) {
			if ( adJson ) {
				// Ok, we have an ad!
				// All conditions fulfilled, inject the ad
				adController.adData.adName = adJson.adName;
				$( adController.containerElement ).prepend( adJson.adHtml );

				if (
					adController.data.testing !== true &&
					!adController.data.getVars.ad
				) {
					// not a forced preview of a specific ad, send analytics hit
					adController.trackAd( adJson.adName, adJson.campaign );
				}
				adController.adShown = true;
			}
		},
		trackAd: function ( adName, campaign ) {
			if ( mw.loader.getState( 'ext.googleUniversalAnalytics.utils' ) === null ) {
				return;
			}
			mw.loader.using( 'ext.googleUniversalAnalytics.utils' ).then( function () {
				if ( adController.config.trackAdViews ) {
					// Send view hit
					mw.googleAnalytics.utils.recordEvent( {
						eventCategory: 'ad-impressions',
						eventAction: campaign,
						eventLabel: adName,
						nonInteraction: true
					} );
				}

				if ( adController.config.trackAdClicks ) {
					// And bind another event to a possible click...
					$( adController.containerElement ).find( '.mainlink > a, a.caption' ).click( function ( e ) {
						mw.googleAnalytics.utils.recordClickEvent( e, {
							eventCategory: 'ad-clicks',
							eventAction: campaign,
							eventLabel: adName,
							nonInteraction: false
						} );
					} );
				}
			} );
		},

		loadQueryStringVariables: function () {
			document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
				adController.data.getVars[ decode( p1 ) ] = decode( p2 );
			} );
		},
		initialize: function () {
			// === Do not allow Promoter to be re-initialized. ===
			if ( adController.isInitialized ) {
				return;
			}
			adController.isInitialized = true;

			// Load configuration that comes from PHP side
			adController.config = mw.config.get( 'wgPromoter' );

			// === Attempt to load parameters from the query string ===
			adController.loadQueryStringVariables();

			adController.isPreviewFrame = ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'AdPreview' );

			// === Do not actually load a ad on a special page ===
			//     But we keep this after the above initialization for Promoter pages
			//     that do ad previews.
			if ( mw.config.get( 'wgNamespaceNumber' ) === -1 && !adController.isPreviewFrame ) {
				return;
			}

			// === Do not load ads on main page for now (special case) ===
			if ( mw.config.get( 'wgIsMainPage' ) === true ) {
				mw.log( 'No ads on main page' );
				return;
			}

			// === Create Deferred and Promise Objects ===
			adController.deferredObjs.adLoaded = $.Deferred();
			adController.events.adLoaded = adController.deferredObjs.adLoaded.promise();

			// === Final prep to loading ad ===
			// Add the Promoter div so that insert ad has something to latch on to.
			// $( '#sidebar-promotion' ).prepend();

			adController.loadAd();
		}
	};

	// Initialize Promoter
	$( function () {
		adController.initialize();
	} );

}() );
