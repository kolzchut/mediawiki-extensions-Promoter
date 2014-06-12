<?php

/**
 * Special page for management of Promoter ads
 */
class SpecialPromoterAds extends Promoter {
	/** @var string Name of the ad we're currently editing */
	protected $adName = '';

	/** @var string Filter to apply to the ad search when generating the list */
	protected $adFilterString = '';

	/** @var string Language code to render preview materials in */
	protected $adLanguagePreview;

	/** @var bool If true, form execution must stop and the page will be redirected */
	protected $adFormRedirectRequired = false;

	function __construct() {
		SpecialPage::__construct( 'PromoterAds' );

		// Make sure we have a session
		wfSetupSession();

		// Load things that may have been serialized into the session
		$this->adFilterString = $this->getPRSessionVar( 'adFilterString', '' );
		$this->adLanguagePreview = $this->getPRSessionVar(
			'adLanguagePreview',
			$this->getLanguage()->getCode()
		);
	}

	function __destruct() {
		$this->setPRSessionVar( 'adFilterString', $this->adFilterString );
	}

	/**
	 * Whether this special page is listed in Special:SpecialPages
	 * @return Bool
	 */
	public function isListed() {
		return false;
	}

	/**
	 * Handle all the different types of page requests determined by $action
	 *
	 * Valid actions are:
	 *    Null      - Display a list of ads
	 *    Edit      - Edits an existing ad
	 */
	public function execute( $page ) {
		// Do all the common setup
		$this->setHeaders();
		$this->editable = $this->getUser()->isAllowed( 'promoter-admin' );

		// User settable text for some custom message, like usage instructions
		$this->getOutput()->setPageTitle( $this->msg( 'campaignad' ) );
		$this->getOutput()->addWikiMsg( 'promoter-summary' );

		// Now figure out wth to display
		$parts = explode( '/', $page );
		$action = ( isset( $parts[0] ) && $parts[0] ) ? $parts[0]: 'list';

		switch ( strtolower( $action ) ) {
			case 'list':
				// Display the list of ads
				$this->showAdList();
				break;

			case 'edit':
				// Display the ad editor form
				if ( array_key_exists( 1, $parts ) ) {
					$this->adName = $parts[1];
					$this->showAdEditor();
				} else {
					throw new ErrorPageError( 'campaigntemplate', 'promoter-generic-error' );
				}
				break;

			case 'preview':
				// Preview all available translations
				// Display the ad editor form
				if ( array_key_exists( 1, $parts ) ) {
					$this->adName = $parts[1];
					$this->showAllLanguages();
				} else {
					throw new ErrorPageError( 'campaigntemplate', 'promoter-generic-error' );
				}
				break;

			default:
				// Something went wrong; display error page
				throw new ErrorPageError( 'campaigntemplate', 'promoter-generic-error' );
				break;
		}
	}

	/**
	 * Process the 'ad list' form and display a new one.
	 */
	protected function showAdList() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'promoter-manage-ads' ) );
		$out->addModules( 'ext.promoter.adminUi.adManager' );

		// Process the form that we sent out
		$formDescriptor = $this->generateAdListForm( $this->adFilterString );
		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( array( $this, 'processAdList' ) );
		$htmlForm->loadData();
		$formResult = $htmlForm->trySubmit();

		if ( $this->adFormRedirectRequired ) {
			return;
		}

		// Re-generate the form in case they changed the filter string, archived something,
		// deleted something, etc...
		$formDescriptor = $this->generateAdListForm( $this->adFilterString );
		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext() );

		$htmlForm->setId('pr-ad-manager')->
			suppressDefaultSubmit()->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	/**
	 * Generates the HTMLForm entities for the 'ad list' form.
	 *
	 * @param string $filter Filter to use for the ad list
	 *
	 * @return array of HTMLForm entities
	 */
	protected function generateAdListForm( $filter = '' ) {
		// --- Create the ad search form --- //
		$formDescriptor = array(
			'adNameLike' => array(
				'section' => 'header/ad-search',
				'class' => 'HTMLTextField',
				'placeholder' => wfMessage( 'promoter-filter-ad-prompt' ),
				'filter-callback' => array( $this, 'sanitizeSearchTerms' ),
				'default' => $filter,
			),
			'filterSubmit' => array(
				'section' => 'header/ad-search',
				'class' => 'HTMLSubmitField',
				'default' => wfMessage( 'promoter-filter-ad-submit' )->text(),
			)
		);

		// --- Create the management options --- //
		$formDescriptor += array(
			'selectAllAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLCheckField',
				'disabled' => !$this->editable,
			),
			/* TODO: Actually enable this feature
			'archiveSelectedAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'promoter-remove' )->text(),
				'disabled' => !$this->editable,
			),
			'addNewAd' => array(
				'section' => 'header/one-off',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'promoter-add-ad' )->text(),
				'disabled' => !$this->editable,
			),
			'newAdName' => array(
				'section' => 'addAd',
				'class' => 'HTMLTextField',
				'disabled' => !$this->editable,
				'label' => wfMessage( 'promoter-ad-name' )->text(),
			),
			'action' => array(
				'type' => 'hidden',
			)
		);

		// --- Add all the ads via the fancy pager object ---
		$pager = new PRAdPager(
			$this->getTitle(),
			'ad-list',
			array(
				 'applyTo' => array(
					 'section' => 'ad-list',
					 'class' => 'HTMLCheckField',
					 'cssclass' => 'pr-adlist-check-applyto',
				 )
			),
			array(),
			$filter,
			$this->editable
		);
		$formDescriptor[ 'topPagerNav' ] = $pager->getNavigationBar();
		$formDescriptor += $pager->getBody();
		$formDescriptor[ 'bottomPagerNav' ] = $pager->getNavigationBar();

		return $formDescriptor;
	}

	/**
	 * Callback function from the showAdList() form that actually processes the
	 * response data.
	 *
	 * @param $formData
	 *
	 * @return null|string|array
	 */
	public function processAdList( $formData ) {
		$this->adFilterString = $formData[ 'adNameLike' ];

		if ( $formData[ 'action' ] && $this->editable ) {
			switch ( strtolower( $formData[ 'action' ] ) ) {
				case 'create':
					// Attempt to create a new ad and redirect; we validate here because it's
					// a hidden field and that doesn't work so well with the form
					if ( !Ad::isValidAdName( $formData[ 'newAdName' ] ) ) {
						return wfMessage( 'promoter-ad-name-error' );
					} else {
						$this->adName = $formData[ 'newAdName' ];
					}

					if ( Ad::fromName( $this->adName )->exists() ) {
						return wfMessage( 'promoter-ad-exists' )->text();
					} else {
						$retval = Ad::addAd(
							$this->adName,
							"<!-- Empty ad -->",
							$this->getUser(),
							false,
							false
						);

						if ( $retval ) {
							// Something failed; display error to user
							return wfMessage( $retval )->text();
						} else {
							$this->getOutput()->redirect(
								SpecialPage::getTitleFor( 'PromoterAds', "edit/{$this->adName}" )->
									getFullURL()
							);
							$this->adFormRedirectRequired = true;
						}
					}
					break;

				case 'archive':
					return ('Archiving not yet implemented!');
					break;

				case 'remove':
					$failed = array();
					foreach( $formData as $element => $value ) {
						$parts = explode( '-', $element, 2 );
						if ( ( $parts[0] === 'applyTo' ) && ( $value === true ) ) {
							try {
								Ad::removeAd( $parts[1], $this->getUser() );
							} catch ( Exception $ex ) {
								$failed[] = $parts[1];
							}
						}
					}
					if ( $failed ) {
						return 'some ads were not deleted';
					}
					break;
			}
		} elseif ( $formData[ 'action' ] ) {
			// Oh noes! The l33t hakorz are here...
			return wfMessage( 'promoter-generic-error' )->text();
		}

		return null;
	}

	/**
	 * Display the ad editor and process edits
	 */
	protected function showAdEditor() {
		$out = $this->getOutput();
		$out->addModules( 'ext.promoter.adminUi.adEditor' );

		if ( !Ad::isValidAdName( $this->adName ) ) {
			throw new ErrorPageError( 'campaignad', 'promoter-generic-error' );
		}
		$out->setPageTitle( $this->adName );
		$out->setSubtitle( Linker::link(
				SpecialPage::getTitleFor( 'Random' ),
				$this->msg( 'promoter-live-preview' ),
				array( 'class' => 'pr-ad-list-element-label-text' ),
				array(
					 'ad' => $this->adName,
					 'uselang' => $this->adLanguagePreview,
					 'force' => '1',
				)
			) );

		// Generate the form
		$formDescriptor = $this->generateAdEditForm( $this->adName );

		// Now begin form processing
		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext(), 'promoter' );
		$htmlForm->setSubmitCallback( array( $this, 'processEditAd' ) );
		$htmlForm->loadData();

		$formResult = $htmlForm->tryAuthorizedSubmit();

		if ( $this->adFormRedirectRequired ) {
			return;
		}

		// Recreate the form because something could have changed
		$formDescriptor = $this->generateAdEditForm( $this->adName );

		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext(), 'promoter' );
		$htmlForm->setSubmitCallback( array( $this, 'processEditAd' ) )->setId( 'pr-promoter-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()->
			setId( 'pr-promoter-editor' )->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	protected function generateAdEditForm() {
		global $wgNoticeMixins, $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		array_walk( $languages, function( &$val, $index ) { $val = "$index - $val"; } );
		$languages = array_flip( $languages );

		$ad = Ad::fromName( $this->adName );
		$adSettings = $ad->getAdSettings( $this->adName, true );

		$formDescriptor = array();

		/* --- Ad Preview Section --- */
		$formDescriptor[ 'preview' ] = array(
			'section' => 'preview',
			'class' => 'HTMLPromoterAd',
			'ad' => $this->adName,
			'language' => $this->adLanguagePreview,
		);

		/* --- Ad Settings --- */
		$formDescriptor['ad-class'] = array(
			'section' => 'settings',
			'type' => 'selectorother',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-class',
			'help-message' => 'promoter-ad-class-desc',
			//'options' => Ad::getAllUsedCategories(),
			'size' => 30,
			'maxlength'=> 255,
			//'default' => $ad->getCategory(),
		);

		$selected = array();
		if ( $adSettings[ 'anon' ] === 1 ) { $selected[] = 'anonymous'; }
		if ( $adSettings[ 'account' ] === 1 ) { $selected[] = 'registered'; }
		$formDescriptor[ 'display-to' ] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-display',
			'options' => array(
				$this->msg( 'promoter-ad-logged-in' )->text() => 'registered',
				$this->msg( 'promoter-ad-anonymous' )->text() => 'anonymous'
			),
			'default' => $selected,
			'cssclass' => 'separate-form-element',
		);
/*
		$mixinNames = array_keys( $wgNoticeMixins );
		$availableMixins = array_combine( $mixinNames, $mixinNames );
		$selectedMixins = array_keys( $ad->getMixins() );
		$formDescriptor['mixins'] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-mixins',
			'help-message' => 'promoter-ad-mixins-help',
			'cssclass' => 'separate-form-element',
			'options' => $availableMixins,
			'default' => $selectedMixins,
		);
*/

		/* --- Translatable Messages Section --- */
		$messages = $ad->getMessageFieldsFromCache( $ad->getBodyContent() );

		if ( $messages ) {
			// Only show this part of the form if messages exist

			$formDescriptor[ 'translate-language' ] = array(
				'section' => 'ad-messages',
				'class' => 'LanguageSelectHeaderElement',
				'label-message' => 'promoter-language',
				'options' => $languages,
				'default' => $this->adLanguagePreview,
				'cssclass' => 'separate-form-element',
			);

			$messageReadOnly = false;
			if ( $wgNoticeUseTranslateExtension && ( $this->adLanguagePreview !== $wgLanguageCode ) ) {
				$messageReadOnly = true;
			}
			foreach ( $messages as $messageName => $count ) {
				if ( $wgNoticeUseTranslateExtension ) {
					// Create per message link to the translate extension
					$title = SpecialPage::getTitleFor( 'Translate' );
					$label = Xml::tags( 'td', null,
						Linker::link( $title, htmlspecialchars( $messageName ), array(), array(
								'group' => AdMessageGroup::getTranslateGroupName( $ad->getName() ),
								'task' => 'view'
							)
						)
					);
				} else {
					$label = htmlspecialchars( $messageName );
				}

				$formDescriptor[ "message-$messageName" ] = array(
					'section' => 'ad-messages',
					'class' => 'HTMLPromoterAdMessage',
					'label-raw' => $label,
					'ad' => $this->adName,
					'message' => $messageName,
					'language' => $this->adLanguagePreview,
					'cssclass' => 'separate-form-element',
				);

				if ( !$this->editable || $messageReadOnly ) {
					$formDescriptor[ "message-$messageName" ][ 'readonly' ] = true;
				}
			}

		}

		/* -- The ad editor -- */
		$formDescriptor[ 'ad-magic-words' ] = array(
			'section' => 'edit-ad',
			'class' => 'HTMLInfoField',
			'default' => Html::rawElement(
				'div',
				array( 'class' => 'separate-form-element' ),
				$this->msg( 'promoter-edit-ad-summary' )->escaped() ),
			'rawrow' => true,
		);

		$renderer = new AdRenderer( $this->getContext(), $ad );
		$magicWords = $renderer->getMagicWords();
		foreach ( $magicWords as &$word ) {
			$word = '{{{' . $word . '}}}';
		}
		$formDescriptor[ 'ad-mixin-words' ] = array(
			'section' => 'edit-ad',
			'type' => 'info',
			'default' => $this->msg(
					'promoter-edit-ad-magicwords',
					$this->getLanguage()->listToText( $magicWords )
				)->text(),
			'rawrow' => true,
		);

		$buttons = array();
		// TODO: Fix this gawdawful method of inserting the close button
		$buttons[ ] =
			'<a href="#" onclick="mw.promoter.adminUi.adEditor.insertButton(\'close\');return false;">' .
				$this->msg( 'promoter-close-button' )->text() . '</a>';
		$formDescriptor[ 'ad-insert-button' ] = array(
			'section' => 'edit-ad',
			'class' => 'HTMLInfoField',
			'rawrow' => true,
			'default' => Html::rawElement(
				'div',
				array( 'class' => 'ad-editing-top-hint separate-form-element' ),
				$this->msg( 'promoter-insert' )->
					rawParams( $this->getLanguage()->commaList( $buttons ) )->
					escaped() ),
		);

		$formDescriptor[ 'ad-body' ] = array(
			'section' => 'edit-ad',
			'type' => 'textarea',
			'readonly' => !$this->editable,
			'hidelabel' => true,
			'placeholder' => '<!-- blank ad -->',
			'default' => $ad->getBodyContent(),
			'cssclass' => 'separate-form-element'
		);

		$links = array();
		foreach( $ad->getIncludedTemplates() as $titleObj ) {
			$links[] = Linker::link( $titleObj );
		}
		if ( $links ) {
			$formDescriptor[ 'links' ] = array(
				'section' => 'edit-ad',
				'type' => 'info',
				'label-message' => 'promoter-templates-included',
				'default' => implode( '<br />', $links ),
				'raw' => true
			);
		}

		/* --- Form bottom options --- */
		$formDescriptor[ 'save-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLSubmitField',
			'default' => $this->msg( 'promoter-save-ad' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		);


		$formDescriptor[ 'clone-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'promoter-clone' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		);

		/* TODO: Add this back in when we can actually support it
		$formDescriptor[ 'archive-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'promoter-archive-ad' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		);
		*/

		$formDescriptor[ 'delete-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'promoter-delete-ad' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		);

		/* --- Hidden fields and such --- */
		$formDescriptor[ 'cloneName' ] = array(
			'section' => 'clone-ad',
			'type' => 'text',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-clone-name',
		);

		$formDescriptor[ 'action' ] = array(
			'section' => 'form-actions',
			'type' => 'hidden',
			// The default is save so that we can still save the ad/form if the ad
			// preview has seriously borked JS. Maybe one day we'll be able to get Caja up
			// and working and not have this issue.
			'default' => 'save',
		);

		return $formDescriptor;
	}

	public function processEditAd( $formData ) {
		// First things first! Figure out what the heck we're actually doing!
		switch ( $formData[ 'action' ] ) {
			case 'update-lang':
				$newLanguage = $formData[ 'translate-language' ];
				$this->setPRSessionVar( 'adLanguagePreview', $newLanguage );
				$this->adLanguagePreview = $newLanguage;
				break;

			case 'delete':
				if ( !$this->editable ) {
					return null;
				}
				try {
					Ad::removeAd( $this->adName, $this->getUser() );
					$this->getOutput()->redirect( $this->getTitle( '' )->getCanonicalURL() );
					$this->adFormRedirectRequired = true;
				} catch ( MWException $ex ) {
					return $ex->getMessage() . " <br /> " . $this->msg( 'promoter-ad-still-bound', $this->adName );
				}
				break;

			case 'archive':
				if ( !$this->editable ) {
					return null;
				}
				return 'Archiving currently does not work';
				break;

			case 'clone':
				if ( !$this->editable ) {
					return null;
				}
				$newAdName = $formData[ 'cloneName' ];
				Ad::fromName( $this->adName )->cloneAd( $newAdName, $this->getUser() );
				$this->getOutput()->redirect(
					$this->getTitle( "Edit/$newAdName" )->getCanonicalURL()
				);
				$this->adFormRedirectRequired = true;
				break;

			case 'save':
				if ( !$this->editable ) {
					return null;
				}
				return $this->processSaveAdAction( $formData );
				break;

			default:
				// Nothing was requested, so do nothing
				break;
		}
	}

	protected function processSaveAdAction( $formData ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$ad = Ad::fromName( $this->adName );

		/* --- Update the translations --- */
		// But only if we aren't using translate or if the preview language is the content language
		if ( !$wgNoticeUseTranslateExtension || ( $this->adLanguagePreview === $wgLanguageCode ) ) {
			foreach( $formData as $key => $value ) {
				if ( strpos( $key, 'message-' ) === 0 ) {
					$messageName = substr( $key, strlen( 'message-' ) );
					$adMessage = $ad->getMessageField( $messageName );
					$adMessage->update( $value, $this->adLanguagePreview, $this->getUser() );
				}
			}
		}

		/* --- Ad settings --- */
		if ( array_key_exists( 'priority-langs', $formData ) ) {
			$prioLang = $formData[ 'priority-langs' ];
			if ( !is_array( $prioLang ) ) {
				$prioLang = array( $prioLang );
			}
		} else {
			$prioLang = array();
		}

		$ad->setAllocation(
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'registered', $formData[ 'display-to' ] )
		);
		//$ad->setCategory( $formData[ 'ad-class' ] );
		$ad->setBodyContent( $formData[ 'ad-body' ] );

		//$ad->setMixins( $formData['mixins'] );

		$ad->save( $this->getUser() );

		return null;
	}

	/**
	 * Preview all available translations of a ad
	 */
	protected function showAllLanguages() {
		$out = $this->getOutput();

		if ( !Ad::isValidAdName( $this->adName ) ) {
			$out->addHTML(
				Xml::element( 'div', array( 'class' => 'error' ), wfMessage( 'promoter-generic-error' ) )
			);
			return;
		}
		$out->setPageTitle( $this->adName );

		// Large amounts of memory apparently required to do this
		ini_set( 'memory_limit', '120M' );

		$ad = Ad::fromName( $this->adName );

		// Pull all available text for a ad
		$langs = $ad->getAvailableLanguages();
		$htmlOut = '';

		$langContext = new DerivativeContext( $this->getContext() );

		foreach ( $langs as $lang ) {
			// HACK: We need to unify these two contexts...
			$langContext->setLanguage( $lang );
			$allocContext = new AllocationContext( 'XX', $lang, 'wikipedia', true, 'desktop', 0 );
			$adRenderer = new AdRenderer( $langContext, $ad, 'test', $allocContext );

			// Link and Preview all available translations
			$htmlOut .= Xml::tags(
				'td',
				array( 'valign' => 'top' ),
				$adRenderer->previewFieldSet()
			);
		}

		$this->getOutput()->addHtml( $htmlOut );
	}
}

/**
 * Class PromoterHtmlForm
 */
class PromoterHtmlForm extends HTMLForm {
	/**
	 * Get the whole body of the form.
	 * @return string
	 */
	function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'pr-formsection-' );
	}
}

/**
 * Acts as a header to the translatable ad message list
 *
 * Class LanguageSelectHeaderElement
 */
class LanguageSelectHeaderElement extends HTMLSelectField {
	public function getInputHTML( $value ) {
		global $wgContLang;

		$html = Xml::openElement( 'table', array( 'class' => 'pr-message-table' ) );
		$html .= Xml::openElement( 'tr' );

		$html .= Xml::element( 'td', array( 'class' => 'pr-message-text-origin-header' ),
			$wgContLang->fetchLanguageName( $wgContLang->getCode() )
		);

		$html .= Xml::openElement( 'td', array( 'class' => 'pr-message-text-native-header' ) );
		$html .= parent::getInputHTML( $value );
		$html .= Xml::closeElement( 'td' );

		$html .= Xml::closeElement( 'tr' );
		$html .= Xml::closeElement( 'table' );

		return $html;
	}
}

class HTMLLargeMultiSelectField extends HTMLMultiSelectField {
	public function getInputHTML( $value ) {
		if ( !is_array( $value ) ) {
			$value = array( $value );
		}

		$options = "\n";
		foreach ( $this->mParams[ 'options' ] as $name => $optvalue ) {
			$options .= Xml::option(
				$name,
				$optvalue,
				in_array( $optvalue, $value )
			) . "\n";
		}

		$properties = array(
			'multiple' => 'multiple',
			'id' => $this->mID,
			'name' => "$this->mName[]",
		);

		if ( !empty( $this->mParams[ 'disabled' ] ) ) {
			$properties[ 'disabled' ] = 'disabled';
		}

		if ( !empty( $this->mParams[ 'cssclass' ] ) ) {
			$properties[ 'class' ] = $this->mParams[ 'cssclass' ];
		}

		return Xml::tags( 'select', $properties, $options );
	}
}
