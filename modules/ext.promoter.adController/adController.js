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
/* jshint jquery:true */
/* global mediaWiki */
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
		alreadyRan: false,

		/**
		 * Deferred objects that link into promises in mw.promoter.events
		 */
		deferredObjs: {},

		/** -- Functions! -- **/
		loadAd: function () {
			if ( mw.promoter.data.getVars.ad ) {
				// If we're forcing one ad
				mw.promoter.loadTestingAd( mw.promoter.data.getVars.ad, 'testing' );
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
				debug: mw.promoter.data.getVars.debug
			};

			$.ajax({
				url: mw.config.get( 'wgScriptPath' ) + '?' + $.param( adPageQuery ),
				dataType: 'script',
				cache: true
			});
		},
		loadRandomAd: function () {
			var wgCategories = mw.config.get( 'wgCategories' );
			var wrMainCategory = ( wgCategories.length > 1 ) ? wgCategories['1'] : null;
			var adDispatchQuery = {
				anonymous: mw.config.get( 'wgUserName' ) === null,
				campaign: wrMainCategory,
				debug: mw.promoter.data.getVars.debug
			};
			var scriptUrl = mw.config.get( 'wgPromoterAdDispatcher' ) + '?' + $.param( adDispatchQuery );

			$.ajax({
				url: scriptUrl,
				dataType: 'script',
				cache: true
			});
		},
		insertAd: function( adJson ) {
			if ( adJson ) {
				// Ok, we have an ad!
				// All conditions fulfilled, inject the ad
				mw.promoter.adData.adName = adJson.adName;
				$( mw.promoter.containerElement ).prepend( adJson.adHtml );

				if( mw.promoter.data.testing != true && !mw.promoter.data.getVars.ad ) {
					// not a forced preview of a specific ad, send analytics hit
					mw.promoter.trackAd( adJson.adName, adJson.campaign );
				}
				mw.promoter.adShown = true;
			}
		},
		trackAd: function( adName, campaign ) {
			// Send hit
			window._gaq = window._gaq || []; // Make sure there's a queue for GA
			window._gaq.push( ['_trackEvent', 'ad-impressions', campaign, adName, undefined, true] );

			// And bind another event to a possible click...
			$( mw.promoter.containerElement).find('.mainlink > a, a.caption').click( function( e ) {
				_gaq.push(['_set', 'hitCallback', function () {
					document.location = e.target.href;	// Navigate on hit callback
				}]);
				window._gaq.push( ['_trackEvent', 'ad-clicks', campaign, adName] );

				return !window._gat; // Prevent default nav only if GA is loaded
				//setTimeout('document.location = "' + this.href + '"', 100);
			});
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

			mw.promoter.isPreviewFrame = (mw.config.get( 'wgCanonicalSpecialPageName' ) === 'AdPreview');

			// === Do not actually load a ad on a special page ===
			//     But we keep this after the above initialization for Promoter pages
			//     that do ad previews.
			if ( mw.config.get( 'wgNamespaceNumber' ) === -1 && !mw.promoter.isPreviewFrame ) {
				return;
			}

			// === Do not load ads on main page for now (special case) ===
			if ( mw.config.get( 'wgIsMainPage') === true ) {
				mw.log( 'No ads on main page' );
				return;
			}

			// === Create Deferred and Promise Objects ===
			mw.promoter.deferredObjs.adLoaded = $.Deferred();
			mw.promoter.events.adLoaded = mw.promoter.deferredObjs.adLoaded.promise();

			// === Final prep to loading ad ===
			// Add the Promoter div so that insert ad has something to latch on to.
			//$( '#sidebar-promotion' ).prepend();

			mw.promoter.loadAd();
		}
	};

	// Initialize Promoter
	$( function() {
		mw.promoter.initialize();
	});

} )( jQuery, mediaWiki );
