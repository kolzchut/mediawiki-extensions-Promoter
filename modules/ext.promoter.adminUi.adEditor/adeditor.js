/**
 * Backing JS for Special:PromoterAds/edit, the form that allows
 * editing of ad content and changing of ad settings.
 *
 * @file
 */
( function () {
	mw.promoter.adminUi.adEditor = {
		/**
		 * Display the 'Create Ad' dialog
		 *
		 * @return {boolean}
		 */
		doCloneAdDialog: function () {
			OO.ui.prompt( mw.msg( 'promoter-clone-name' ), {
				title: mw.msg( 'promoter-clone' ),
				actions: [
					{ action: 'accept', label: mw.msg( 'promoter-clone' ), flags: [ 'primary', 'progressive' ] },
					{ action: 'reject', label: mw.msg( 'promoter-clone-cancel' ), flags: 'safe' }
				]
			} ).then( ( cloneName ) => {
				if ( cloneName !== null ) {
					const formobj = document.getElementById( 'pr-ad-editor' );
					formobj.wpaction.value = 'clone';
					formobj.wpcloneName.value = cloneName;
					formobj.submit();
				}
			} );

			// Do not submit the form... that's up to the dialog
			return false;
		},

		/**
		 * Validates the contents of the ad body before submission.
		 *
		 * @return {boolean}
		 */
		doSaveAd: function () {
			const adBodyField = document.getElementById( 'mw-input-wpad-body' );
			if ( adBodyField && adBodyField.value.includes( 'document.write' ) ) {
				OO.ui.alert( mw.msg( 'promoter-documentwrite-error' ) );
			} else {
				return true;
			}
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected ads and if yes will submit
		 * the form with the 'remove' action.
		 */
		doDeleteAd: function () {
			OO.ui.confirm( mw.msg( 'promoter-delete-ad-confirm' ), {
				title: mw.msg( 'promoter-delete-ad-title', 1 ),
				actions: [
					{ action: 'accept', label: mw.msg( 'promoter-delete-ad' ), flags: [ 'primary', 'destructive' ] },
					{ action: 'reject', label: mw.msg( 'promoter-delete-ad-cancel' ), flags: 'safe' }
				]
			} ).then( ( confirmed ) => {
				if ( confirmed ) {
					const formobj = document.getElementById( 'pr-ad-editor' );
					formobj.wpaction.value = 'delete';
					formobj.submit();
				}
			} );
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveAd: function () {
			OO.ui.confirm( mw.msg( 'promoter-archive-ad-confirm' ), {
				title: mw.msg( 'promoter-archive-ad-title', 1 ),
				actions: [
					{ action: 'accept', label: mw.msg( 'promoter-archive-ad' ), flags: [ 'primary' ] },
					{ action: 'reject', label: mw.msg( 'promoter-archive-ad-cancel' ), flags: 'safe' }
				]
			} ).then( ( confirmed ) => {
				if ( confirmed ) {
					const formobj = document.getElementById( 'pr-ad-editor' );
					formobj.wpaction.value = 'archive';
					formobj.submit();
				}
			} );
		},

		/**
		 * Shows or hides the landing pages edit box control based on the status of
		 * the "Automatically create landing page link" check box.
		 */
		showHideLpEditBox: function () {
			const createLinkCheckbox = document.getElementById( 'mw-input-wpcreate-landingpage-link' );
			const landingPagesInput = document.getElementById( 'mw-input-wplanding-pages' );
			if ( createLinkCheckbox && landingPagesInput ) {
				const parentContainer = landingPagesInput.parentElement.parentElement;
				if ( createLinkCheckbox.checked ) {
					parentContainer.style.display = '';
				} else {
					parentContainer.style.display = 'none';
				}
			}
		},

		/**
		 * Hook function from onclick of the translate language drop down -- will submit the
		 * form in order to update the language of the preview and the displayed translations.
		 */
		updateLanguage: function () {
			const formobj = document.getElementById( 'pr-ad-editor' );
			formobj.wpaction.value = 'update-lang';
			formobj.submit();
		},

		/**
		 * Legacy insert close button code. Happens on link click above the edit area
		 * TODO: Make this jQuery friendly...
		 *
		 * @param {string} buttonType
		 */
		insertButton: function ( buttonType ) {
			let buttonValue,
				sel,
				startPos,
				endPos;
			const adField = document.getElementById( 'mw-input-wpad-body' );

			if ( buttonType === 'close' ) {
				buttonValue = '<a href="#" title="' +
					mw.msg( 'promoter-close-title' ) +
					'" onclick="mw.promoter.hideAd();return false;">' +
					'<img border="0" src="' + mw.config.get( 'wgNoticeCloseButton' ) +
					'" alt="' + mw.msg( 'promoter-close-title' ) +
					'" /></a>';
			}
			if ( document.selection ) {
				// IE support
				adField.focus();
				sel = document.selection.createRange();
				sel.text = buttonValue;
			} else if ( adField.selectionStart || adField.selectionStart === '0' ) {
				// Mozilla support
				startPos = adField.selectionStart;
				endPos = adField.selectionEnd;
				adField.value = adField.value.slice( 0, Math.max( 0, startPos ) ) +
					buttonValue +
					adField.value.slice( endPos, adField.value.length );
			} else {
				adField.value += buttonValue;
			}
			adField.focus();
		},
		createAdPreview: function () {
			const previewContainer = document.querySelector( '#mw-htmlform-preview > div' );
			if ( previewContainer ) {
				previewContainer.innerHTML = '';
				const wrapper = document.createElement( 'div' );
				wrapper.className = 'discovery-wrapper';
				const discovery = document.createElement( 'div' );
				discovery.className = 'discovery';
				const innerDiv = document.createElement( 'div' );
				discovery.appendChild( innerDiv );
				wrapper.appendChild( discovery );
				previewContainer.appendChild( wrapper );
			}
		},
		triggerAdChange: function () {
			const linkInput = document.getElementById( 'mw-input-wpad-link' );
			const bodyInput = document.getElementById( 'mw-input-wpad-body' );
			const newCheckbox = document.getElementById( 'mw-input-wpad-tags-new' );

			if ( !linkInput || !bodyInput || !newCheckbox ) {
				return;
			}

			let url = linkInput.value,
				urlType = 'internal';
			const blogUrl = mw.discovery.config.blogUrl;

			if ( url.includes( blogUrl ) ) {
				urlType = 'blog';
			} else if ( url.indexOf( 'http' ) === 0 ) {
				urlType = 'external';
			} else {
				url = mw.util.getUrl( url );
			}

			const itemData = {
				content: bodyInput.value,
				url: url,
				urlType: urlType,
				indicators: {
					new: Number( newCheckbox.checked )
				}
			};

			const adHTML = mw.discovery.buildDiscoveryItem( itemData );
			Array.prototype.forEach.call( adHTML.querySelectorAll( 'a' ), ( a ) => a.setAttribute( 'target', '_blank' ) );

			const discoveryDiv = document.querySelector( '.discovery > div' );
			if ( discoveryDiv ) {
				discoveryDiv.innerHTML = '';
				discoveryDiv.appendChild( adHTML );
			}
		},
		createCharCounter: function () {
			const adBodyField = document.getElementById( 'mw-input-wpad-body' );
			if ( adBodyField ) {
				const counter = document.createElement( 'div' );
				counter.className = 'char-counter';
				adBodyField.parentNode.insertBefore( counter, adBodyField.nextSibling );
			}
		},
		updateCharCount: function () {
			const maxChars = mw.discovery.MAX_CHARS;
			const adBodyField = document.getElementById( 'mw-input-wpad-body' );
			const charCounter = document.querySelector( '.char-counter' );

			if ( !adBodyField || !charCounter ) {
				return;
			}

			const currentCharCount = adBodyField.value.length;

			if ( currentCharCount > maxChars ) {
				charCounter.classList.add( 'red' );
			} else {
				charCounter.classList.remove( 'red' );
			}

			charCounter.textContent = currentCharCount + '/' + maxChars;
		}
	};

	// Attach event handlers
	const deleteButton = document.getElementById( 'mw-input-wpdelete-button' );
	const archiveButton = document.getElementById( 'mw-input-wparchive-button' );
	const cloneButton = document.getElementById( 'mw-input-wpclone-button' );
	const saveButton = document.getElementById( 'mw-input-wpsave-button' );
	const translateLanguage = document.getElementById( 'mw-input-wptranslate-language' );
	const createLandingpageLink = document.getElementById( 'mw-input-wpcreate-landingpage-link' );
	const adTagsNew = document.getElementById( 'mw-input-wpad-tags-new' );
	const adBody = document.getElementById( 'mw-input-wpad-body' );
	const adLink = document.getElementById( 'mw-input-wpad-link' );
	const jsErrorWarn = document.getElementById( 'pr-js-error-warn' );

	if ( deleteButton ) {
		deleteButton.addEventListener( 'click', mw.promoter.adminUi.adEditor.doDeleteAd );
	}
	if ( archiveButton ) {
		archiveButton.addEventListener( 'click', mw.promoter.adminUi.adEditor.doArchiveAd );
	}
	if ( cloneButton ) {
		cloneButton.addEventListener( 'click', mw.promoter.adminUi.adEditor.doCloneAdDialog );
	}
	if ( saveButton ) {
		saveButton.addEventListener( 'click', mw.promoter.adminUi.adEditor.doSaveAd );
	}
	if ( translateLanguage ) {
		translateLanguage.addEventListener( 'change', mw.promoter.adminUi.adEditor.updateLanguage );
	}
	if ( createLandingpageLink ) {
		createLandingpageLink.addEventListener( 'change', mw.promoter.adminUi.adEditor.showHideLpEditBox );
	}
	if ( adTagsNew ) {
		adTagsNew.addEventListener( 'change', mw.promoter.adminUi.adEditor.triggerAdChange );
	}
	if ( adBody ) {
		adBody.addEventListener( 'keyup', mw.promoter.adminUi.adEditor.triggerAdChange );
		adBody.addEventListener( 'keyup', mw.promoter.adminUi.adEditor.updateCharCount );
	}
	if ( adLink ) {
		adLink.addEventListener( 'keyup', mw.promoter.adminUi.adEditor.triggerAdChange );
	}

	// And do some initial form work
	mw.promoter.adminUi.adEditor.showHideLpEditBox();
	mw.promoter.adminUi.adEditor.createAdPreview();
	mw.promoter.adminUi.adEditor.triggerAdChange();
	mw.promoter.adminUi.adEditor.createCharCounter();
	mw.promoter.adminUi.adEditor.updateCharCount();
	if ( jsErrorWarn ) {
		jsErrorWarn.style.display = 'none';
	}

}() );
