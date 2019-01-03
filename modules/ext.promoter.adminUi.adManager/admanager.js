/**
 * Backing JS for Special:PromoterAds, the ad list view form.
 *
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
 *
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
( function () {

	var am;

	am = mw.promoter.adminUi.adManager = {
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
		 *
		 * @return {boolean}
		 */
		doAddAdDialog: function () {
			var buttons = {},
				okButtonText = mw.message( 'promoter-add-ad-button' ).text(),
				cancelButtonText = mw.message( 'promoter-add-ad-cancel-button' ).text(),
				dialogObj = $( '<form></form>' );

			// Implement the functionality
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ okButtonText ] = function () {
				var formobj = $( '#pr-ad-manager' )[ 0 ];
				formobj.wpaction.value = 'create';
				formobj.wpnewAdName.value = $( this )[ 0 ].wpnewAdName.value;

				formobj.wpnewAdEditSummary.value =
					$( this )[ 0 ].wpnewAdEditSummary.value;

				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			dialogObj[ 0 ].name = dialogObj[ 0 ].id = 'addAdDialog';
			dialogObj.append( $( '#pr-formsection-addAd' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'promoter-add-new-ad-title' ).escaped(),
					modal: true,
					buttons: buttons,
					width: 400
				} );

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected ads and if yes will submit
		 * the form with the 'remove' action.
		 */
		doRemoveAds: function () {
			var dialogObj = $( '<form></form>' ),
				dialogMessage = $( '<div class="pr-dialog-message" />' ),
				buttons = {},
				deleteText = mw.message( 'promoter-delete-ad' ).text(),
				cancelButtonText = mw.message( 'promoter-delete-ad-cancel' ).text();

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ deleteText ] = function () {
				var formobj = $( '#pr-ad-manager' )[ 0 ];
				formobj.wpaction.value = 'remove';

				formobj.wpremoveAdEditSummary.value =
					$( this )[ 0 ].wpremoveAdEditSummary.value;

				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			dialogObj.append( dialogMessage );
			dialogMessage.text( mw.message( 'promoter-delete-ad-confirm' ).text() );

			dialogObj.append( $( '#pr-formsection-removeAd' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message(
						'promoter-delete-ad-title',
						am.selectedItemCount
					).escaped(),
					width: '35em',
					modal: true,
					buttons: buttons
				} );
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveAds: function () {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'promoter-archive-ad' ).text(),
				cancelButtonText = mw.message( 'promoter-archive-ad-cancel' ).text();

			buttons[ archiveText ] = function () {
				var formobj = $( '#pr-ad-manager' )[ 0 ];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			dialogObj.text( mw.message( 'promoter-archive-ad-confirm' ).text() );
			dialogObj.dialog( {
				title: mw.message(
					'promoter-archive-ad-title',
					am.selectedItemCount
				).escaped(),
				resizable: false,
				modal: true,
				buttons: buttons
			} );
		},

		/**
		 * Updates all the ad check boxes when the 'checkAll' check box is clicked
		 */
		checkAllStateAltered: function () {
			var checkBoxes = $( 'input.pr-adlist-check-applyto' );
			if ( $( '#mw-input-wpselectAllAds' ).prop( 'checked' ) ) {
				am.selectedItemCount = am.totalSelectableItems;
				checkBoxes.each( function () {
					$( this ).prop( 'checked', true );
				} );
			} else {
				am.selectedItemCount = 0;
				checkBoxes.each( function () {
					$( this ).prop( 'checked', false );
				} );
			}
			am.checkedCountUpdated();
		},

		/**
		 * Updates the 'checkAll' check box if any of the ad check boxes are checked
		 */
		selectCheckStateAltered: function () {
			if ( $( this ).prop( 'checked' ) === true ) {
				am.selectedItemCount++;
			} else {
				am.selectedItemCount--;
			}
			am.checkedCountUpdated();
		},

		/**
		 *
		 */
		checkedCountUpdated: function () {
			var selectAllCheck = $( '#mw-input-wpselectAllAds' ),
				deleteButton = $( ' #mw-input-wpdeleteSelectedAds' );

			if ( am.selectedItemCount === am.totalSelectableItems ) {
				// Everything selected
				selectAllCheck.prop( 'checked', true );
				selectAllCheck.prop( 'indeterminate', false );
				deleteButton.prop( 'disabled', false );
			} else if ( am.selectedItemCount === 0 ) {
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
		},

		/**
		 * Reload the page with a URL query for the requested ad name
		 * filter (or lack thereof).
		 */
		applyFilter: function () {
			var newUri, filterStr;

			filterStr = $( '#mw-input-wpadNameFilter' ).val();
			newUri = new mw.Uri();

			// If there's a filter, reload with a filter query param.
			// If there's no filter, reload with no such param.
			if ( filterStr.length > 0 ) {
				filterStr = am.sanitizeFilterStr( filterStr );
				newUri.extend( { filter: filterStr } );
			} else {
				delete newUri.query.filter;
			}

			location.replace( newUri.toString() );
		},

		/**
		 * Filter text box keypress handler; applies the filter when enter is
		 * pressed.
		 */
		filterTextBoxKeypress: function ( e ) {
			if ( e.which === 13 ) {
				am.applyFilter();
				return false;
			}
		},

		/**
		 * Remove characters not allowed in ad names. See server-side
		 * Ad::isValidAdName() and
		 * SpecialPromoter::sanitizeSearchTerms().
		 */
		sanitizeFilterStr: function ( $origFilterStr ) {
			return $origFilterStr.replace( /[^0-9a-zA-Zא-ת_-]/g, '' );
		}
	};

	// Attach event handlers
	$( '#mw-input-wpaddNewAd' ).click( am.doAddAdDialog );
	$( '#mw-input-wpdeleteSelectedAds' ).click( am.doRemoveAds );
	$( '#mw-input-wparchiveSelectedAds' ).click( am.doArchiveAds );
	$( '#mw-input-wpselectAllAds' ).click( am.checkAllStateAltered );
	$( '#mw-input-wpfilterApply' ).click( am.applyFilter );
	$( '#mw-input-wpadNameFilter' ).keypress( am.filterTextBoxKeypress );

	$( 'input.pr-adlist-check-applyto' ).each( function () {
		$( this ).click( am.selectCheckStateAltered );
		am.totalSelectableItems++;
	} );

	// Some initial display work
	am.checkAllStateAltered();
	$( '#pr-js-error-warn' ).hide();

}() );
