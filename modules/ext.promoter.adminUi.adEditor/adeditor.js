/**
 * Backing JS for Special:PromoterAds/edit, the form that allows
 * editing of ad content and changing of ad settings.
 *
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
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
	mw.promoter.adminUi.adEditor = {
		/**
		 * Display the 'Create Ad' dialog
		 * @returns {boolean}
		 */
		doCloneAdDialog: function() {
			var buttons = {},
				okButtonText = mw.message('promoter-clone').text(),
				cancelButtonText = mw.message('promoter-clone-cancel').text(),
				dialogObj = $('<form></form>');

			// Implement the functionality
			buttons[ cancelButtonText ] = function() { $(this).dialog("close"); };
			buttons[ okButtonText ] = function() {
				var formobj = $('#pr-ad-editor')[0];
				formobj.wpaction.value = 'clone';
				formobj.wpcloneName.value = $(this)[0].wpcloneName.value;
				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			dialogObj[0].name = 'addAdDialog';
			dialogObj.append( $( '#pr-formsection-clone-ad' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message('promoter-clone-notice' ).text(),
					modal: true,
					buttons: buttons,
					width: 'auto'
				});

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Validates the contents of the ad body before submission.
		 * @returns {boolean}
		 */
		doSaveAd: function() {
			if ( $( '#mw-input-wpad-body' ).prop( 'value' ).indexOf( 'document.write' ) > -1 ) {
				window.alert( mediaWiki.msg( 'promoter-documentwrite-error' ) );
			} else {
				return true;
			}
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected ads and if yes will submit
		 * the form with the 'remove' action.
		 */
		doDeleteAd: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				deleteText = mw.message( 'promoter-delete-ad' ).text(),
				cancelButtonText = mw.message('promoter-delete-ad-cancel').text();

			buttons[ deleteText ] = function() {
				var formobj = $('#pr-ad-editor')[0];
				formobj.wpaction.value = 'delete';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'promoter-delete-ad-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message( 'promoter-delete-ad-title', 1 ).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveAd: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'promoter-archive-ad' ).text(),
				cancelButtonText = mw.message('promoter-archive-ad-cancel').text();

			buttons[ archiveText ] = function() {
				var formobj = $('#pr-ad-editor')[0];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'promoter-archive-ad-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message( 'promoter-archive-ad-title', 1 ).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Shows or hides the landing pages edit box control based on the status of
		 * the "Automatically create landing page link" check box.
		 */
		showHideLpEditBox: function() {
			if ( $( '#mw-input-wpcreate-landingpage-link' ).prop( 'checked' ) ) {
				$( '#mw-input-wplanding-pages' ).parent().parent().show();
			} else {
				$( '#mw-input-wplanding-pages' ).parent().parent().hide();
			}
		},

		/**
		 * Hook function from onclick of the translate language drop down -- will submit the
		 * form in order to update the language of the preview and the displayed translations.
		 */
		updateLanguage: function() {
			var formobj = $('#pr-ad-editor')[0];
			formobj.wpaction.value = 'update-lang';
			formobj.submit();
		},

		/**
		 * Legacy insert close button code. Happens on link click above the edit area
		 * TODO: Make this jQuery friendly...
		 *
		 * @param buttonType
		 */
		insertButton: function( buttonType ) {
			var buttonValue, sel;
			var adField = document.getElementById( 'mw-input-wpad-body' );
			if ( buttonType === 'close' ) {
				buttonValue = '<a href="#" title="'
					+ mediaWiki.msg( 'promoter-close-title' )
					+ '" onclick="mw.promoter.hideAd();return false;">'
					+ '<img border="0" src="' + mediaWiki.config.get( 'wgNoticeCloseButton' )
					+ '" alt="' + mediaWiki.msg( 'promoter-close-title' )
					+ '" /></a>';
			}
			if ( document.selection ) {
				// IE support
				adField.focus();
				sel = document.selection.createRange();
				sel.text = buttonValue;
			} else if ( adField.selectionStart || adField.selectionStart == '0' ) {
				// Mozilla support
				var startPos = adField.selectionStart;
				var endPos = adField.selectionEnd;
				adField.value = adField.value.substring(0, startPos)
					+ buttonValue
					+ adField.value.substring(endPos, adField.value.length);
			} else {
				adField.value += buttonValue;
			}
			adField.focus();
		},
		createAdPreview: function () {
			var adPreviewDOM = $( '' +
			'<div class="discovery-wrapper">' +
				'<div class="discovery">' +
					'<div></div>' +
				'</div>' +
			'</div>' );

			$( '#mw-htmlform-preview > div' ).empty().append( adPreviewDOM );
		},
		triggerAdChange: function () {
			var url = $( '#mw-input-wpad-link' ).val();

			if ( url.indexOf( 'www' ) === 0 ) {
				url = '//' + url;
			}
			else if ( url.indexOf( 'http' ) === -1 ) {
				url = mw.util.getUrl( url );
			}

			var itemData = {
				content: $( '#mw-input-wpad-body' ).val(),
				url: url,
				indicators: {
					"new": Number( $( '#mw-input-wpad-tags-new' ).prop( 'checked' ) )
				}
			};

			var adHTML = mw.discovery.buildDiscoveryItem( itemData );
			adHTML.find( 'a' ).attr( 'target', '_blank' );

			$( '.discovery > div' ).html( adHTML );
		}
	};

	// Attach event handlers
	$( '#mw-input-wpdelete-button' ).click( mw.promoter.adminUi.adEditor.doDeleteAd );
	$( '#mw-input-wparchive-button' ).click( mw.promoter.adminUi.adEditor.doArchiveAd );
	$( '#mw-input-wpclone-button' ).click( mw.promoter.adminUi.adEditor.doCloneAdDialog );
	$( '#mw-input-wpsave-button' ).click( mw.promoter.adminUi.adEditor.doSaveAd );
	$( '#mw-input-wptranslate-language' ).change( mw.promoter.adminUi.adEditor.updateLanguage );
	$( '#mw-input-wpcreate-landingpage-link' ).change( mw.promoter.adminUi.adEditor.showHideLpEditBox );

	$('#mw-input-wpad-tags-new').change( mw.promoter.adminUi.adEditor.triggerAdChange );
	$('#mw-input-wpad-body, #mw-input-wpad-link').keyup( mw.promoter.adminUi.adEditor.triggerAdChange );

	// And do some initial form work
	mw.promoter.adminUi.adEditor.showHideLpEditBox();
	mw.promoter.adminUi.adEditor.createAdPreview();
	mw.promoter.adminUi.adEditor.triggerAdChange();
	$( '#pr-js-error-warn' ).hide();

} ( jQuery, mediaWiki ));