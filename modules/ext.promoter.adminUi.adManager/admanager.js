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

	let am,
		// Cache DOM references to avoid repeated queries
		domRefs = {};

	am = mw.promoter.adminUi.adManager = {
		/**
		 * State tracking variable for the number of items currently selected
		 *
		 * @protected
		 */
		selectedItemCount: 0,

		/**
		 * State tracking variable for the number of items available to be selected
		 *
		 * @protected
		 */
		totalSelectableItems: 0,

		/**
		 * Display the 'Create Ad' dialog
		 *
		 * @return {boolean}
		 */
		doAddAdDialog: function () {
			const buttons = {},
				okButtonText = mw.message( 'promoter-add-ad-button' ).text(),
				cancelButtonText = mw.message( 'promoter-add-ad-cancel-button' ).text(),
				$dialogObj = $( '<form>' ),
				$addAdSection = $( domRefs.formSectionAddAd ).children( 'div' ).clone().show();

			// Implement the functionality
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ okButtonText ] = function () {
				domRefs.adManagerForm.wpaction.value = 'create';
				domRefs.adManagerForm.wpnewAdName.value = $( this )[ 0 ].wpnewAdName.value;

				domRefs.adManagerForm.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			$dialogObj[ 0 ].name = $dialogObj[ 0 ].id = 'addAdDialog';
			$dialogObj.append( $addAdSection )
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
			const $dialogObj = $( '<form>' ),
				$dialogMessage = $( document.createElement( 'div' ) ).addClass( 'pr-dialog-message' ),
				$removeAdSection = $( domRefs.formSectionRemoveAd ).children( 'div' ).clone().show(),
				buttons = {},
				deleteText = mw.message( 'promoter-delete-ad' ).text(),
				cancelButtonText = mw.message( 'promoter-delete-ad-cancel' ).text();

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ deleteText ] = function () {
				domRefs.adManagerForm.wpaction.value = 'remove';

				domRefs.adManagerForm.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			$dialogObj.append( $dialogMessage );
			$dialogMessage.text( mw.message( 'promoter-delete-ad-confirm' ).text() );

			$dialogObj.append( $removeAdSection )
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
			const $dialogObj = $( document.createElement( 'div' ) ),
				buttons = {},
				archiveText = mw.message( 'promoter-archive-ad' ).text(),
				cancelButtonText = mw.message( 'promoter-archive-ad-cancel' ).text();

			buttons[ archiveText ] = function () {
				domRefs.adManagerForm.wpaction.value = 'archive';
				domRefs.adManagerForm.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			$dialogObj.text( mw.message( 'promoter-archive-ad-confirm' ).text() );
			$dialogObj.dialog( {
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
			const checkBoxes = domRefs.adCheckboxes;
			if ( domRefs.selectAllCheckbox.checked ) {
				am.selectedItemCount = am.totalSelectableItems;
				checkBoxes.forEach( ( checkbox ) => {
					checkbox.checked = true;
				} );
			} else {
				am.selectedItemCount = 0;
				checkBoxes.forEach( ( checkbox ) => {
					checkbox.checked = false;
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
		 * Update UI elements based on checked count
		 */
		checkedCountUpdated: function () {
			const selectAllCheck = domRefs.selectAllCheckbox,
				deleteButton = domRefs.deleteButton;

			if ( am.selectedItemCount === am.totalSelectableItems ) {
				// Everything selected
				selectAllCheck.checked = true;
				selectAllCheck.indeterminate = false;
				deleteButton.disabled = false;
			} else if ( am.selectedItemCount === 0 ) {
				// Nothing selected
				selectAllCheck.checked = false;
				selectAllCheck.indeterminate = false;
				deleteButton.disabled = true;
			} else {
				// Some things selected
				selectAllCheck.checked = true;
				selectAllCheck.indeterminate = true;
				deleteButton.disabled = false;
			}
		},

		/**
		 * Reload the page with a URL query for the requested ad name
		 * filter (or lack thereof).
		 */
		applyFilter: function () {
			let newUri, filterStr;

			filterStr = domRefs.filterInput.value;
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
		 *
		 * @param {Event} e
		 * @return {boolean}
		 */
		filterTextBoxKeypress: function ( e ) {
			if ( e.which === 13 ) {
				am.applyFilter();
				return false;
			}
			return true;
		},

		/**
		 * Remove characters not allowed in ad names. See server-side
		 * Ad::isValidAdName() and
		 * SpecialPromoter::sanitizeSearchTerms().
		 *
		 * @param {string} origFilterStr
		 * @return {string}
		 */
		sanitizeFilterStr: function ( origFilterStr ) {
			return origFilterStr.replace( /[^0-9a-zA-Zא-ת_-]/g, '' );
		}
	};

	// Cache DOM references once on initialization
	domRefs.adManagerForm = document.getElementById( 'pr-ad-manager' );
	domRefs.formSectionAddAd = document.getElementById( 'pr-formsection-addAd' );
	domRefs.formSectionRemoveAd = document.getElementById( 'pr-formsection-removeAd' );
	domRefs.selectAllCheckbox = document.getElementById( 'mw-input-wpselectAllAds' );
	domRefs.deleteButton = document.getElementById( 'mw-input-wpdeleteSelectedAds' );
	domRefs.filterInput = document.getElementById( 'mw-input-wpadNameFilter' );
	domRefs.adCheckboxes = [].slice.call( document.querySelectorAll( 'input.pr-adlist-check-applyto' ) );

	// Attach event handlers using vanilla JS
	document.getElementById( 'mw-input-wpaddNewAd' ).addEventListener( 'click', am.doAddAdDialog );
	domRefs.deleteButton.addEventListener( 'click', am.doRemoveAds );
	document.getElementById( 'mw-input-wparchiveSelectedAds' ).addEventListener( 'click', am.doArchiveAds );
	domRefs.selectAllCheckbox.addEventListener( 'click', am.checkAllStateAltered );
	document.getElementById( 'mw-input-wpfilterApply' ).addEventListener( 'click', am.applyFilter );
	domRefs.filterInput.addEventListener( 'keypress', am.filterTextBoxKeypress );

	domRefs.adCheckboxes.forEach( ( checkbox ) => {
		checkbox.addEventListener( 'click', am.selectCheckStateAltered );
		am.totalSelectableItems++;
	} );

	// Some initial display work
	am.checkAllStateAltered();

	// Hide error warning using vanilla JS
	( function () {
		const errorWarn = document.getElementById( 'pr-js-error-warn' );
		if ( errorWarn ) {
			errorWarn.style.display = 'none';
		}
	}() );

}() );
