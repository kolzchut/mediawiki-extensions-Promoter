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

	protected $allCampaigns = [];

	function __construct() {
		SpecialPage::__construct( 'PromoterAds' );

		// Make sure we have a session
		$this->getRequest()->getSession()->persist();

		$this->allCampaigns = AdCampaign::getAllCampaignNames();
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
	 *
	 * @param $page
	 *
	 * @throws ErrorPageError
	 */
	public function execute( $page ) {
		// Do all the common setup
		$this->setHeaders();
		$this->editable = $this->getUser()->isAllowed( 'promoter-admin' );

		// User settable text for some custom message, like usage instructions
		$this->getOutput()->setPageTitle( $this->msg( 'campaignad' ) );
		$this->getOutput()->addWikiMsg( 'promoter-summary' );
		$this->getOutput()->addModules( 'ext.discovery' );

		// Now figure out wth to display
		$parts = explode( '/', $page );
		$action = ( isset( $parts[0] ) && $parts[0] ) ? $parts[0] : 'list';

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
		$htmlForm->setSubmitCallback( [ $this, 'processAdList' ] );
		$htmlForm->loadData();
		$formResult = $htmlForm->trySubmit();

		if ( $this->adFormRedirectRequired ) {
			return;
		}

		// Re-generate the form in case they changed the filter string, archived something,
		// deleted something, etc...
		$formDescriptor = $this->generateAdListForm( $this->adFilterString );
		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext() );

		$htmlForm->setId( 'pr-ad-manager' )->
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
		$formDescriptor = [
			'adNameFilter' => [
				'section' => 'header/ad-search',
				'class' => 'HTMLTextField',
				// 'cssclass' => 'form-control',
				'placeholder' => wfMessage( 'promoter-filter-ad-prompt' ),
				'filter-callback' => [ $this, 'sanitizeSearchTerms' ],
				'default' => $filter,
			],
			'filterApply' => [
				'section' => 'header/ad-search',
				'class' => 'HTMLSubmitField',
				// 'cssclass' => 'btn',
				'default' => wfMessage( 'promoter-filter-ad-submit' )->text(),
			]
		];

		// --- Create the management options --- //
		$formDescriptor += [
			'selectAllAds' => [
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLCheckField',
				// 'cssclass' => 'checkbox',
				'disabled' => !$this->editable,
			],
			/* TODO: Actually enable this feature
			'archiveSelectedAds' => array(
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				'cssclass' => 'btn',
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedAds' => [
				'section' => 'header/ad-bulk-manage',
				'class' => 'HTMLButtonField',
				// 'cssclass' => 'btn danger ',
				'default' => wfMessage( 'promoter-remove' )->text(),
				'disabled' => !$this->editable,
			],
			'addNewAd' => [
				'section' => 'header/one-off',
				'class' => 'HTMLButtonField',
				// 'cssclass' => 'btn',
				'default' => wfMessage( 'promoter-add-ad' )->text(),
				'disabled' => !$this->editable,
			],
			'newAdName' => [
				'section' => 'addAd',
				'class' => 'HTMLTextField',
				// 'cssclass' => 'form-control',
				'disabled' => !$this->editable,
				'label' => wfMessage( 'promoter-ad-name' )->text(),
			],
			'action' => [
				'type' => 'hidden',
			]
		];

		// --- Add all the ads via the fancy pager object ---
		$pager = new PRAdPager(
			$this->getPageTitle(),
			'ad-list',
			[
				 'applyTo' => [
					 'section' => 'ad-list',
					 'class' => 'HTMLCheckField',
					 'cssclass' => 'pr-adlist-check-applyto',
				 ]
			],
			[],
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
	 * @throws AdDataException
	 * @throws MWException
	 */
	public function processAdList( $formData ) {
		$this->setFilterFromUrl();

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
						return wfMessage( 'promoter-ad-already-exists', $this->adName )->text();
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
					return ( 'Archiving not yet implemented!' );
					break;

				case 'remove':
					$failed = [];
					foreach ( $formData as $element => $value ) {
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
			throw new ErrorPageError( 'campaignad', 'promoter-ad-name-error' );
		}
		$out->setPageTitle( $this->adName );

		// Generate the form
		$formDescriptor = $this->generateAdEditForm();

		// Now begin form processing
		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext(), 'promoter' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditAd' ] );
		$htmlForm->loadData();

		$formResult = $htmlForm->tryAuthorizedSubmit();

		if ( $this->adFormRedirectRequired ) {
			return;
		}

		// Recreate the form because something could have changed
		$formDescriptor = $this->generateAdEditForm();

		$htmlForm = new PromoterHtmlForm( $formDescriptor, $this->getContext(), 'promoter' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditAd' ] )->setId( 'pr-ad-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()->
			setId( 'pr-ad-editor' )->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	protected function generateAdEditForm() {
		$ad = Ad::fromName( $this->adName );
		try {
			$adSettings = $ad->getAdSettings();
		} catch ( MWException $e ) {
			throw new ErrorPageError( 'promoter', 'promoter-ad-doesnt-exists', $this->adName );
		}
		$formDescriptor = [];

		$formDescriptor['ad-active'] = [
			'section' => 'settings',
			'type' => 'check',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-active',
			'default' => $adSettings['active'],
			'cssclass' => 'separate-form-element',
		];

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

		$selected = [];
		if ( $adSettings[ 'anon' ] === 1 ) {
			$selected[] = 'anonymous';
		}
		if ( $adSettings[ 'user' ] === 1 ) {
			$selected[] = 'user';
		}
		$formDescriptor[ 'display-to' ] = [
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-display',
			'options' => [
				$this->msg( 'promoter-ad-user' )->text() => 'user',
				$this->msg( 'promoter-ad-anonymous' )->text() => 'anonymous'
			],
			'default' => $selected,
			'cssclass' => 'separate-form-element',
		];

		$selectedTags = [];
		if ( $adSettings[ 'new' ] === 1 ) {
			$selectedTags[] = 'new';
		}
		$formDescriptor[ 'ad-tags' ] = [
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-tags-label',
			'options' => [
				$this->msg( 'promoter-ad-tag-new' )->text() => 'new'
			],
			'default' => $selectedTags,
			'cssclass' => 'separate-form-element',
		];

		$formDescriptor[ 'ad-date-start' ] = [
			'cssclass' => 'separate-form-element',
			'section' => 'settings',
			'type' => 'date',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-date-start',
			'default' => $ad->getStartDate() ? $ad->getStartDate()->format( 'Y-m-d' ) : ''
		];

		$formDescriptor['ad-date-end'] = [
			'cssclass' => 'separate-form-element',
			'section' => 'settings',
			'type' => 'date',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-ad-date-end',
			'default' => $ad->getEndDate() ? $ad->getEndDate()->format( 'Y-m-d' ) : ''
		];

		/* -- The ad editor -- */

		$formDescriptor[ 'ad-title' ] = [
			'section' => 'edit-ad',
			'type' => 'text',
			'required' => true,
			'default' => $ad->getCaption(),
			'label-message' => 'promoter-ad-title',
			'cssclass' => 'separate-form-element'
		];

		$formDescriptor[ 'ad-link' ] = [
			'section' => 'edit-ad',
			'type' => 'text',
			// 'required' => true,
			'placeholder' => 'שם העמוד',
			'default' => $ad->getMainLink(),
			'label-message' => 'promoter-ad-link',
			'cssclass' => 'separate-form-element'
		];

		if ( !$this->editable ) {
			$formDescriptor[ 'ad-title' ][ 'readonly' ] = true;
			$formDescriptor[ 'ad-link' ][ 'readonly' ] = true;
		}

		$formDescriptor[ 'ad-body' ] = [
			'section' => 'edit-ad',
			'type' => 'textarea',
			'rows' => 5,
			'cols' => 45, // Same as the regular inputs
			'required' => true,
			'label-message' => 'promoter-ad-body',
			'placeholder' => '<!-- blank ad -->',
			'default' => $ad->getBodyContent(),
			'cssclass' => 'separate-form-element'
		];

		if ( !$this->editable ) {
			foreach ( $formDescriptor as $item ) {
				$item['readonly'] = 'readonly';
			}
		}

		$links = [];
		foreach ( $ad->getIncludedTemplates() as $titleObj ) {
			$links[] = $this->getLinkRenderer()->makeLink( $titleObj );
		}
		if ( $links ) {
			$formDescriptor[ 'links' ] = [
				'section' => 'edit-ad',
				'type' => 'info',
				'label-message' => 'promoter-templates-included',
				'default' => implode( '<br />', $links ),
				'raw' => true
			];
		}

		/* --- Ad Preview Section --- */
		$formDescriptor[ 'preview' ] = [
			'section' => 'preview',
			'type' => 'info',
		];

		$campaignList    = [];
		$linkedCampaigns = [];

		foreach ( $ad->getLinkedCampaignNames() as $key => $campaignName ) {
			$linkedCampaigns[] = $campaignName;
		}

		foreach ( $this->allCampaigns as $key => $campaignName ) {
			$campaignList[$campaignName] = $campaignName;
		}

		$formDescriptor['ad-linked-campaigns'] = [
			'section'  => 'ad-linked-campaigns',
			'type'     => 'multiselect',
			'options'  => $campaignList,
			'default'  => $linkedCampaigns,
			'cssclass' => 'separate-form-element'
		];

		/* --- Form bottom options --- */
		$formDescriptor[ 'save-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLSubmitField',
			'default' => $this->msg( 'promoter-save-ad' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		];

		$formDescriptor[ 'clone-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'promoter-clone' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		];

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

		$formDescriptor[ 'delete-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'promoter-delete-ad' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'pr-formbutton',
			'hidelabel' => true,
		];

		/* --- Hidden fields and such --- */
		$formDescriptor[ 'cloneName' ] = [
			'section' => 'clone-ad',
			'type' => 'text',
			'disabled' => !$this->editable,
			'label-message' => 'promoter-clone-name',
		];

		$formDescriptor[ 'action' ] = [
			'section' => 'form-actions',
			'type' => 'hidden',
			// The default is save so that we can still save the ad/form if the ad
			// preview has seriously borked JS. Maybe one day we'll be able to get Caja up
			// and working and not have this issue.
			'default' => 'save',
		];

		return $formDescriptor;
	}

	/**
	 * Use a URL parameter to set the filter string for the banner list.
	 */
	protected function setFilterFromUrl() {
		// This is the normal param on visible URLs.
		$filterParam = $this->getRequest()->getVal( 'filter', null );
		// If the form was posted the filter parameter'll have a different name.
		if ( $filterParam === null ) {
			$filterParam =
				$this->getRequest()->getVal( 'wpadNameFilter', null );
		}
		// Clean, clean...
		if ( $filterParam !== null ) {
			$this->adFilterString
				= static::sanitizeSearchTerms( $filterParam );
		}
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
					$this->getOutput()->redirect( $this->getPageTitle( '' )->getCanonicalURL() );
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
				try {
					Ad::fromName( $this->adName )->cloneAd( $newAdName, $this->getUser() );
				} catch ( AdExistenceException $e ) {
					throw new ErrorPageError( 'promoter', 'promoter-ad-already-exists', $newAdName );
				} catch ( AdDataException $e ) {
					throw new ErrorPageError( 'promoter', 'promoter-ad-name-error' );
				}
				$this->getOutput()->redirect(
					$this->getPageTitle( "Edit/$newAdName" )->getCanonicalURL()
				);
				$this->adFormRedirectRequired = true;
				break;

			case 'save':
				// If only one of the date fields was filled, return error
				if (
					( $formData[ 'ad-date-end' ] && !$formData[ 'ad-date-start' ] )
					|| ( !$formData[ 'ad-date-end' ] && $formData[ 'ad-date-start' ] )
				) {
					return wfMessage( 'promoter-ad-inconsistent-dates-error' )->text();
				}

				if ( strtotime( $formData['ad-date-start'] ) > strtotime( $formData['ad-date-end'] ) ) {
					return wfMessage( 'promoter-ad-date-end-bigger-than-date-start' )->text();
				}

				if ( !$this->editable ) {
					return null;
				}
				return $this->processSaveAdAction( $formData );
				break;

			default:
				// Nothing was requested, so do nothing
				break;
		}

		return null;
	}

	protected function processSaveAdAction( $formData ) {
		$startDate = null;
		$endDate = null;

		if ( $formData[ 'ad-date-start' ] ) {
			$startDate = DateTime::createFromFormat(
				'Y-m-d H:i:s', $formData['ad-date-start'] . ' 00:30:00'
			);
			$startDate = new MWTimestamp( $startDate );
		}

		if ( $formData[ 'ad-date-end' ] ) {
			$endDate = DateTime::createFromFormat( 'Y-m-d H:i:s', $formData['ad-date-end'] . ' 23:59:59' );
			$endDate = new MWTimestamp( $endDate );
		}

		$ad = Ad::fromName( $this->adName );

		$activeStatus = $formData['ad-active'];
		$ad->setActiveStatus( $activeStatus );

		$linkedCampaigns       = $ad->getLinkedCampaignNames();
		$campaignsToAddTo      = $formData['ad-linked-campaigns'];
		$campaignsToRemoveFrom = array_diff( $linkedCampaigns, $campaignsToAddTo );

		// Differentiate between added campaigns and linked campaigns to determine which ones
		// should stay intact
		$campaignsToAddTo = array_diff( $campaignsToAddTo, $linkedCampaigns );

		// Get campaign IDs
		$campaignsToAddTo = array_map( function ( $campaign ) {
			return AdCampaign::getCampaignId( $campaign );
		}, $campaignsToAddTo );

		$campaignsToRemoveFrom = array_map( function ( $campaign ) {
			return AdCampaign::getCampaignId( $campaign );
		}, $campaignsToRemoveFrom );

		// Add/remove ad from said campaigns
		AdCampaign::addAdToCampaigns( $campaignsToAddTo, $ad->getId(), 25 );
		AdCampaign::removeAdForCampaigns( $campaignsToRemoveFrom, $ad->getId() );

		/* --- Ad settings --- */
		$ad->setAllocation(
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'user', $formData[ 'display-to' ] )
		);

		$ad->setTags( $formData[ 'ad-tags' ] );

		$ad->setStartDate( $startDate );
		$ad->setEndDate( $endDate );

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
