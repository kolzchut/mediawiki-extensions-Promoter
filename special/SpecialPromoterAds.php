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
		$this->getRequest()->getSession()->persist();

		// Load things that may have been serialized into the session
		$this->adFilterString = $this->getPRSessionVar( 'adFilterString', '' );

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
					throw new ErrorPageError( 'campaignad', 'promoter-generic-error' );
				}
				break;

			/*
			case 'preview':

				break;
			*/
			default:
				// Something went wrong; display error page
				throw new ErrorPageError( 'campaignad', 'promoter-generic-error' );
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
				//'cssclass' => 'form-control',
				'placeholder' => wfMessage( 'promoter-filter-ad-prompt' ),
				'filter-callback' => array( $this, 'sanitizeSearchTerms' ),
				'default' => $filter,
			),
			'filterSubmit' => array(
				'section' => 'header/ad-search',
				'class' => 'HTMLSubmitField',
				//'cssclass' => 'btn',
				'default' => wfMessage( 'promoter-filter-ad-submit' )->text(),
			)
		);

		// --- Create the management options --- //
		$formDescriptor += array(
			'selectAllAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLCheckField',
				//'cssclass' => 'checkbox',
				'disabled' => !$this->editable,
			),
			/* TODO: Actually enable this feature
			'archiveSelectedAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				'cssclass' => 'btn',
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				//'cssclass' => 'btn danger ',
				'default' => wfMessage( 'promoter-remove' )->text(),
				'disabled' => !$this->editable,
			),
			'addNewAd' => array(
				'section' => 'header/one-off',
				'class' => 'HTMLButtonField',
				//'cssclass' => 'btn',
				'default' => wfMessage( 'promoter-add-ad' )->text(),
				'disabled' => !$this->editable,
			),
			'newAdName' => array(
				'section' => 'addAd',
				'class' => 'HTMLTextField',
				//'cssclass' => 'form-control',
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
							"",
							null,
							null,
							$this->getUser()
						);

						if ( $retval && $retval !== true ) {
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
				SpecialPage::getTitleFor( 'Randompage' ),
				$this->msg( 'promoter-live-preview' ),
				array( 'class' => 'pr-ad-list-element-label-text' ),
				array(
					 'ad' => $this->adName,
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
		$htmlForm->setSubmitCallback( array( $this, 'processEditAd' ) )->setId( 'pr-ad-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()->
			setId( 'pr-ad-editor' )->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );

		$ad = Ad::fromName( $this->adName );
		$linkedCampaigns = $ad->getLinkedCampaignNames();
		$htmlList = "<h2>" . wfMessage( 'promoter-ad-linked-campaigns' )->text() . "</h2>";
		if( empty( $linkedCampaigns ) ) {
			$htmlList .= wfMessage( 'promoter-ad-linked-campaigns-empty' )->text();
		} else {
			$htmlList .= "<ul>";
			foreach ( $linkedCampaigns as $linkedCampaign ) {
				$htmlList .= "<li>{$linkedCampaign}</li>";
			}
			$htmlList .= "</ul>";
		}

		$this->getOutput()->addHTML( $htmlList );
	}

	protected function generateAdEditForm() {
		$ad = Ad::fromName( $this->adName );
		$adSettings = $ad->getAdSettings( $this->adName, true );

		$formDescriptor = array();

		/* --- Ad Preview Section --- */
		$formDescriptor[ 'preview' ] = array(
			'section' => 'preview',
			'class' => 'HTMLPromoterAd',
			'ad' => $this->adName,
		);

		/* --- Ad Settings --- */
		/*
		$formDescriptor['ad-class'] = array(
			'section' => 'settings',
			'type' => 'selectorother',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-class',
			'help-message' => 'promoter-ad-class-desc',
			'options' => Ad::getAllUsedCategories(),
			'size' => 30,
			'maxlength'=> 255,
			//'default' => $ad->getCategory(),
		);
		*/

		$selected = array();
		if ( $adSettings[ 'anon' ] === 1 ) { $selected[] = 'anonymous'; }
		if ( $adSettings[ 'user' ] === 1 ) { $selected[] = 'user'; }
		$formDescriptor[ 'display-to' ] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-display',
			'options' => array(
				$this->msg( 'promoter-ad-user' )->text() => 'user',
				$this->msg( 'promoter-ad-anonymous' )->text() => 'anonymous'
			),
			'default' => $selected,
			'cssclass' => 'separate-form-element',
		);

		$formDescriptor['ad-active'] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-active-label',
			'options' => array(
				$this->msg( 'promoter-ad-active' )->text() => 'active'
			),
			'default' => $adSettings['active'] === 1 ? [ 'active' ] : [],
			'cssclass' => 'separate-form-element',
		);

		/* -- The ad editor -- */

		$formDescriptor[ 'ad-title' ] = array(
			'section' => 'edit-ad',
			'type' => 'text',
			'required' => true,
			//'placeholder' => '<!-- ad heading -->',
			'default' => $ad->getCaption(),
			'label-message' => 'promoter-ad-title',
			'cssclass' => 'separate-form-element'
		);

		$formDescriptor[ 'ad-link' ] = array(
			'section' => 'edit-ad',
			'type' => 'text',
			//'required' => true,
			'placeholder' => 'שם העמוד',
			'default' => $ad->getMainLink(),
			'label-message' => 'promoter-ad-link',
			'cssclass' => 'separate-form-element'
		);

		if ( !$this->editable ) {
			$formDescriptor[ 'ad-title' ][ 'readonly' ] = true;
			$formDescriptor[ 'ad-link' ][ 'readonly' ] = true;
		}

		$formDescriptor[ 'ad-body' ] = array(
			'section' => 'edit-ad',
			'type' => 'textarea',
			'rows' => 5,
			'cols' => 45, // Same as the regular inputs
			'required' => true,
			'label-message' => 'promoter-ad-body',
			'placeholder' => '<!-- blank ad -->',
			'default' => $ad->getBodyContent(),
			'cssclass' => 'separate-form-element'
		);

		if( !$this->editable ) {
			foreach( $formDescriptor as $item ) {
				$item['readonly'] = 'readonly';
			}
		}

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
		$ad = Ad::fromName( $this->adName );

		$activeStatus = !empty( $formData['ad-active'] );
		$ad->setActive( $activeStatus );

		/* --- Ad settings --- */
		$ad->setAllocation(
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'user', $formData[ 'display-to' ] )
		);

		$ad->setCaption( $formData['ad-title'] );
		$ad->setMainLink( $formData['ad-link'] );
		$ad->setBodyContent( $formData[ 'ad-body' ] );

		$ad->save( $this->getUser() );

		return null;
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
