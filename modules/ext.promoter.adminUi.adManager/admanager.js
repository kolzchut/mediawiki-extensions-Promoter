/**
 * Backing JS for Special:PromoterAds, the ad list view form.
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
	mw.promoter.adminUi.adManagement = {
		/**
		 * State tracking variable for the number of items currently selected
		 * @protected
		 */
		selectedItemCount: 0,

		/**
		 * State tracking variable for the number of items available to be selected
		 * @protected
		 */
		totalSelectableItems: 0,

		/**
		 * Display the 'Create Ad' dialog
		 * @returns {boolean}
		 */
		doAddAdDialog: function() {
			var buttons = {},
				okButtonText = mw.message('promoter-add-ad-button').text(),
				cancelButtonText = mw.message('promoter-add-ad-cancel-button').text(),
				dialogObj = $('<form></form>');

			// Implement the functionality
			buttons[ cancelButtonText ] = function() { $(this).dialog("close"); };
			buttons[ okButtonText ] = function() {
				var formobj = $('#pr-ad-manager')[0];
				formobj.wpaction.value = 'create';
				formobj.wpnewAdName.value = $(this)[0].wpnewAdName.value;
				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			dialogObj[0].name = 'addAdDialog';
			dialogObj.append( $( '#pr-formsection-addAd' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'promoter-add-new-ad-title' ).text(),
					modal: true,
					buttons: buttons,
					width: 400
				});

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected ads and if yes will submit
		 * the form with the 'remove' action.
		 */
		doRemoveAds: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				deleteText = mw.message( 'promoter-delete-ad' ).text(),
				cancelButtonText = mw.message( 'promoter-delete-ad-cancel' ).text();

			buttons[ deleteText ] = function() {
				var formobj = $( '#pr-ad-manager' )[0];
				formobj.wpaction.value = 'remove';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'promoter-delete-ad-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message(
					'promoter-delete-ad-title',
					mw.promoter.adminUi.adManagement.selectedItemCount
				).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveAds: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'promoter-archive-ad' ).text(),
				cancelButtonText = mw.message('promoter-archive-ad-cancel').text();

			buttons[ archiveText ] = function() {
				var formobj = $('#pr-ad-manager')[0];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'promoter-archive-ad-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message(
					'promoter-archive-ad-title',
					mw.promoter.adminUi.adManagement.selectedItemCount
				).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Updates all the ad check boxes when the 'checkAll' check box is clicked
		 */
		checkAllStateAltered: function() {
			var checkBoxes = $( 'input.pr-adlist-check-applyto' );
			if ( $( '#mw-input-wpselectAllAds' ).prop( 'checked' ) ) {
				mw.promoter.adminUi.adManagement.selectedItemCount =
					mw.promoter.adminUi.adManagement.totalSelectableItems;
				checkBoxes.each( function() { $( this ).prop( 'checked', true ); } );
			} else {
				mw.promoter.adminUi.adManagement.selectedItemCount = 0;
				checkBoxes.each( function() { $( this ).prop( 'checked', false ); } );
			}
			mw.promoter.adminUi.adManagement.checkedCountUpdated();
		},

		/**
		 * Updates the 'checkAll' check box if any of the ad check boxes are checked
		 */
		selectCheckStateAltered: function() {
			if ( $( this ).prop( 'checked' ) === true ) {
				mw.promoter.adminUi.adManagement.selectedItemCount++;
			} else {
				mw.promoter.adminUi.adManagement.selectedItemCount--;
			}
			mw.promoter.adminUi.adManagement.checkedCountUpdated();
		},

		/**
		 *
		 */
		checkedCountUpdated: function () {
			var selectAllCheck = $( '#mw-input-wpselectAllAds' ),
				deleteButton = $(' #mw-input-wpdeleteSelectedAds' );

			if ( mw.promoter.adminUi.adManagement.selectedItemCount ===
				mw.promoter.adminUi.adManagement.totalSelectableItems
			) {
				// Everything selected
				selectAllCheck.prop( 'checked', true );
				selectAllCheck.prop( 'indeterminate', false );
				deleteButton.prop( 'disabled', false );
			} else if ( mw.promoter.adminUi.adManagement.selectedItemCount === 0 ) {
				// Nothing selected
				selectAllCheck.prop( 'checked', false );
				selectAllCheck.prop( 'indeterminate', false );
				deleteButton.prop( 'disabled', true );
			} else {
				// Some things selected
				selectAllCheck.prop( 'checked', true );
				selectAllCheck.prop( 'indeterminate', true );
				deleteButton.prop( 'disabled', false );
			}
		}
	};

	// Attach event handlers
	$( '#mw-input-wpaddNewAd' ).click( mw.promoter.adminUi.adManagement.doAddAdDialog );
	$( '#mw-input-wpdeleteSelectedAds' ).click( mw.promoter.adminUi.adManagement.doRemoveAds );
	$( '#mw-input-wparchiveSelectedAds' ).click( mw.promoter.adminUi.adManagement.doArchiveAds );
	$( '#mw-input-wpselectAllAds' ).click( mw.promoter.adminUi.adManagement.checkAllStateAltered );
	$( 'input.pr-adlist-check-applyto' ).each( function() {
		$( this ).click( mw.promoter.adminUi.adManagement.selectCheckStateAltered );
		mw.promoter.adminUi.adManagement.totalSelectableItems++;
	} );

	// Some initial display work
	mw.promoter.adminUi.adManagement.checkAllStateAltered();
	$( '#pr-js-error-warn' ).hide();

} )( jQuery, mediaWiki );
