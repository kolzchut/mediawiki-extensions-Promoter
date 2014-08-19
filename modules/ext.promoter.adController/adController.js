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
( function ( $, mw ) {
	"use strict";

	var rPlus = /\+/g;
	function decode( s ) {
		try {
			// decodeURIComponent can throw an exception for unknown char encodings.
			return decodeURIComponent( s.replace( rPlus, ' ' ) );
		} catch ( e ) {
			return '';
		}
	}

	mw.promoter = {
		/**
		 * Promoter Required Data
		 */
		data: {
			getVars: {},
			testing: false
		},

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
		alreadyRan: false,

		/**
		 * Deferred objects that link into promises in mw.promoter.events
		 */
		deferredObjs: {},

		/** -- Functions! -- **/
		loadAd: function () {
			if ( mw.promoter.data.getVars.ad ) {
				// If we're forcing one ad
				mw.promoter.loadTestingAd( mw.promoter.data.getVars.ad, 'none', 'testing' );
			} else {
				mw.promoter.loadRandomAd();
			}
		},
		loadTestingAd: function ( adName, campaign ) {
			var adPageQuery;

			mw.promoter.data.testing = true;

			// Get the requested ad
			adPageQuery = {
				title: 'Special:AdLoader',
				ad: adName,
				campaign: campaign,
				//uselang: mw.config.get( 'wgUserLanguage' ),
				//db: mw.config.get( 'wgDBname' ),
				//project: mw.config.get( 'wgNoticeProject' ),
				//country: mw.promoter.data.country,
				//device: mw.promoter.data.device,
				debug: mw.promoter.data.getVars.debug
			};

			//DS: FIXME - wgCentralPagePath???
			$.ajax({
				url: mw.config.get( 'wgCentralPagePath' ) + '?' + $.param( adPageQuery ),
				dataType: 'script',
				cache: true
			});
		},
		loadRandomAd: function () {
			var RAND_MAX = 30;
			var adDispatchQuery = {
				//uselang: mw.config.get( 'wgUserLanguage' ),
				//sitename: mw.config.get( 'wgSiteName' ),
				//project: mw.config.get( 'wgNoticeProject' ),
				anonymous: mw.config.get( 'wgUserName' ) === null,
				//country: mw.promoter.data.country,
				//device: mw.promoter.data.device,
				slot: Math.floor( Math.random() * RAND_MAX ) + 1,
				debug: mw.promoter.data.getVars.debug
			};
			//DS: FIXME
			var scriptUrl = mw.config.get( 'wgCentralBannerDispatcher' ) + '?' + $.param( adDispatchQuery );

			$.ajax({
				url: scriptUrl,
				dataType: 'script',
				cache: true
			});
		},
		// TODO: move function definitions once controller cache has cleared
		insertAd: function( adJson ) {
			window.insertAd( adJson );
		},
		loadQueryStringVariables: function () {
			document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
				mw.promoter.data.getVars[decode( p1 )] = decode( p2 );
			} );
		},
		initialize: function () {
			// === Do not allow Promoter to be re-initialized. ===
			if ( mw.promoter.alreadyRan ) {
				return;
			}
			mw.promoter.alreadyRan = true;

			// === Attempt to load parameters from the query string ===
			mw.promoter.loadQueryStringVariables();

			// === Initialize things that don't come from MW itself ===
			//mw.promoter.data.country = mw.promoter.data.getVars.country || window.Geo.country || 'XX';
			//mw.promoter.data.addressFamily = ( window.Geo.IPv6 || window.Geo.af === 'v6' ) ? 'IPv6' : 'IPv4';
			mw.promoter.isPreviewFrame = (mw.config.get( 'wgCanonicalSpecialPageName' ) === 'AdPreview');
			//mw.promoter.data.device = mw.promoter.data.getVars.device || mw.config.get( 'wgMobileDeviceName', 'desktop' );

			// === Do not actually load a ad on a special page ===
			//     But we keep this after the above initialization for Promoter pages
			//     that do ad previews.
			if ( mw.config.get( 'wgNamespaceNumber' ) === -1 && !mw.promoter.isPreviewFrame ) {
				return;
			}

			// === Create Deferred and Promise Objects ===
			mw.promoter.deferredObjs.adLoaded = $.Deferred();
			mw.promoter.events.adLoaded = mw.promoter.deferredObjs.adLoaded.promise();

			// === Final prep to loading ad ===
			// Add the Promoter div so that insert ad has something to latch on to.
			$( '#sidebarPromotion' ).prepend(
				'<div id="promoter101"></div>'
			);

			mw.promoter.loadAd();
		}
	};

	// Function that actually inserts the ad into the page after it is retrieved
	// Has to be global because of compatibility with legacy code.
	//
	// Will query the DOM to see if mw.promoter.adData.alterImpressionData()
	// exists in the ad. If it does it is expected to return true if the ad was
	// shown, The alterImpressionData function is called with the impressionData variable
	// filled below which can be altered at will by the function (thought it is recommended
	// to only add variables, not remove/alter them as this may have effects on upstream
	// analytics.)
	//
	// Regardless of impression state however, if this is a testing call, ie: the
	// ad was specifically requested via ad= the record impression call
	// will NOT be made.
	//
	// TODO: Migrate away from global functions
	window.insertAd = function ( adJson ) {
		var url, targets;

		var impressionData = {
			country: mw.promoter.data.country,
			uselang: mw.config.get( 'wgUserLanguage' ),
			project: mw.config.get( 'wgNoticeProject' ),
			db: mw.config.get( 'wgDBname' ),
			anonymous: mw.config.get( 'wgUserName' ) === null,
			device: mw.promoter.data.device
		};

		// This gets prepended to the impressionData at the end
		var impressionResultData = null;

		if ( !adJson ) {
			// There was no ad returned from the server
			impressionResultData = {
				result: 'hide',
				reason: 'empty'
			};
		} else {
			// Ok, we have a ad! Get the ad type for more queryness
			// All conditions fulfilled, inject the ad
			mw.promoter.adData.adName = adJson.adName;
			$( 'div#promoter' )
				.prepend( adJson.adHtml );

			/*
			// Create landing page links if required
			if ( adJson.autolink ) {
				url = mw.config.get( 'wgNoticeFundraisingUrl' );
				if ( ( adJson.landingPages !== null ) && adJson.landingPages.length ) {
					targets = String( adJson.landingPages ).split( ',' );
					if ( $.inArray( mw.promoter.data.country, mw.config.get( 'wgNoticeXXCountries' ) ) !== -1 ) {
						mw.promoter.data.country = 'XX';
					}
					url += "?" + $.param( {
						landing_page: targets[Math.floor( Math.random() * targets.length )].replace( /^\s+|\s+$/, '' ),
						utm_medium: 'sitenotice',
						utm_campaign: adJson.campaign,
						utm_source: adJson.adName,
						language: mw.config.get( 'wgUserLanguage' ),
						country: mw.promoter.data.country
					} );
					$( '#pr-landingpage-link' ).attr( 'href', url );
				}
			}
			*/

			// Query the initial impression state if the ad callback exists
			var adShown = true;
			if ( typeof mw.promoter.adData.alterImpressionData === 'function' ) {
				adShown = mw.promoter.adData.alterImpressionData( impressionData );
			}

			// eventually we want to unify the ordering here and always return
			// the result, ad, campaign in that order. presently this is not
			// possible without some rework of how the analytics scripts work.
			// ~~ as of 2012-11-27
			if ( adShown ) {
				impressionResultData = {
					ad: adJson.adName,
					campaign: adJson.campaign,
					result: 'show'
				};
			} else {
				impressionResultData = {
					result: 'hide'
				};
			}
		}


		// Record whatever impression we made
		impressionResultData = $.extend( impressionResultData, impressionData );
		if ( !mw.promoter.data.testing ) {
			mw.promoter.recordImpression( impressionResultData );
		}
		mw.promoter.deferredObjs.adLoaded.resolve( impressionResultData );
	};

	// Initialize Promoter
	$( function() {
		mw.promoter.initialize();
	});
} )( jQuery, mediaWiki );
