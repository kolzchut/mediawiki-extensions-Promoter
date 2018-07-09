<?php

class Promoter extends SpecialPage {

	public $editable, $promoterError;

	function __construct() {
		// Register special page
		parent::__construct( 'Promoter' );
	}

	/**
	 * Handle different types of page requests
	 *
	 * @param $sub
	 *
	 * @throws ErrorPageError
	 * @throws MWException
	 */
	function execute( $sub ) {
		// Begin output
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$request = $this->getRequest();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( 'ext.promoter.adminUi.campaignManager' );

		// Check permissions
		$this->editable = $this->getUser()->isAllowed( 'promoter-admin' );

		// Initialize error variable
		$this->promoterError = false;

		// Begin Campaigns tab content
		$out->addHTML( Xml::openElement( 'div', [ 'id' => 'pr-preferences' ] ) );

		$method = $request->getVal( 'method' );

		// Switch to campaign detail interface if requested
		if ( $method == 'listCampaignDetail' ) {
			$campaign = $request->getVal( 'campaign' );
			$this->listCampaignDetail( $campaign );
			$out->addHTML( Xml::closeElement( 'div' ) );
			return;
		}

		// Handle form submissions from "Manage campaigns" or "Add a campaign" interface
		if ( $this->editable && $request->wasPosted() ) {
			// Check authentication token
			if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {
				// Handle adding a campaign
				if ( $method == 'addCampaign' ) {
					$campaignName = $request->getVal( 'campaignName' );
					if ( $campaignName == '' ) {
						$this->showError( 'promoter-null-string' );
					} else {
						$result = AdCampaign::addCampaign( $campaignName, '0', $this->getUser() );
						if ( is_string( $result ) ) {
							$this->showError( $result );
						}
					}
				// Handle changing settings to existing campaigns
				} else {
					// Handle archiving campaigns
					$toArchive = $request->getArray( 'archiveCampaigns' );
					if ( $toArchive ) {
						// Archive campaigns in list
						foreach ( $toArchive as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'archived', 1 );
						}
					}

					// Get all the initial campaign settings for logging
					$allCampaignNames = AdCampaign::getAllCampaignNames();
					$allInitialCampaignSettings = [];
					foreach ( $allCampaignNames as $campaignName ) {
						$settings = AdCampaign::getCampaignSettings( $campaignName );
						$allInitialCampaignSettings[ $campaignName ] = $settings;
					}

					/*
					// Handle locking/unlocking campaigns

					$lockedCampaigns = $request->getArray( 'locked' );
					if ( $lockedCampaigns ) {
						// Build list of campaigns to lock
						$unlockedCampaigns = array_diff( AdCampaign::getAllCampaignNames(), $lockedCampaigns );

						// Set locked/unlocked flag accordingly
						foreach ( $lockedCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'locked', 1 );
						}
						foreach ( $unlockedCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'locked', 0 );
						}
					// Handle updates if no post content came through (all checkboxes unchecked)
					} else {
						$allCampaigns = AdCampaign::getAllCampaignNames();
						foreach ( $allCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'locked', 0 );
						}

					}
					*/

					// Handle enabling/disabling campaigns
					$enabledCampaigns = $request->getArray( 'enabled' );
					if ( $enabledCampaigns ) {
						// Build list of campaigns to disable
						$disabledCampaigns = array_diff( AdCampaign::getAllCampaignNames(), $enabledCampaigns );

						// Set enabled/disabled flag accordingly
						foreach ( $enabledCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'enabled', 1 );
						}
						foreach ( $disabledCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
						}
					// Handle updates if no post content came through (all checkboxes unchecked)
					} else {
						$allCampaigns = AdCampaign::getAllCampaignNames();
						foreach ( $allCampaigns as $campaign ) {
							AdCampaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
						}
					}

					// Handle setting priority on campaigns
					/*
					$preferredCampaigns = $request->getArray( 'priority' );
					if ( $preferredCampaigns ) {
						foreach ( $preferredCampaigns as $campaign => $value ) {
							AdCampaign::setNumericCampaignSetting(
								$campaign,
								'preferred',
								$value,
								Promoter::EMERGENCY_PRIORITY,
								Promoter::LOW_PRIORITY
							);
						}
					}
					*/

					// Get all the final campaign settings for potential logging
					foreach ( $allCampaignNames as $campaignName ) {
						$finalCampaignSettings = AdCampaign::getCampaignSettings( $campaignName );
						if ( !$allInitialCampaignSettings[ $campaignName ] || !$finalCampaignSettings ) {
							// Condition where the campaign has apparently disappeared mid operations
							// -- possibly a delete call
							$diffs = false;
						} else {
							$diffs = array_diff_assoc(
								$allInitialCampaignSettings[ $campaignName ], $finalCampaignSettings
							);
						}
						// If there are changes, log them
						if ( $diffs ) {
							/*
							$campaignId = AdCampaign::getCampaignId( $campaignName );
							AdCampaign::logCampaignChange(
								'modified',
								$campaignId,
								$this->getUser(),
								$allInitialCampaignSettings[ $campaignName ],
								$finalCampaignSettings
							);
							*/
						}
					}
				}

				// If there were no errors, reload the page to prevent duplicate form submission
				if ( !$this->promoterError ) {
					$out->redirect( $this->getPageTitle()->getLocalURL() );
					return;
				}
			} else {
				$this->showError( 'sessionfailure' );
			}
		}

		// Show list of campaigns
		$this->listCampaigns();

		// End Campaigns tab content
		$out->addHTML( Xml::closeElement( 'div' ) );
	}

	/**
	 * Build a table row. Needed since Xml::buildTableRow escapes all HTML.
	 *
	 * @param $fields
	 * @param string $element
	 * @param array $attribs
	 *
	 * @return string
	 */
	function tableRow( $fields, $element = 'td', $attribs = [] ) {
		$cells = [];
		foreach ( $fields as $field ) {
			$cells[ ] = Xml::tags( $element, [], $field );
		}
		return Xml::tags( 'tr', $attribs, implode( "\n", $cells ) ) . "\n";
	}

	/**
	 * Show all campaigns found in the database, show "Add a campaign" form
	 */
	function listCampaigns() {
		// Cache these commonly used properties
		$readonly = [ 'disabled' => 'disabled' ];

		// TODO: refactor to use AdCampaign::getCampaigns
		// Get all campaigns from the database
		$dbr = PRDatabase::getDb();
		$res = $dbr->select( 'pr_campaigns',
			[
				'cmp_name',
				'cmp_enabled',
				'cmp_archived'
			],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'cmp_id DESC' ]
		);

		// Begin building HTML
		$htmlOut = '';

		// Begin Manage campaigns fieldset
		$htmlOut .= Xml::openElement( 'div', [ 'class' => 'prefsection' ] );

		// If there are campaigns to show...
		if ( $res->numRows() >= 1 ) {
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form', [ 'method' => 'post' ] );
				$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );
			}
			$htmlOut .= Xml::element( 'h2', null, $this->msg( 'promoter-manage' )->text() );

			// Filters
			$htmlOut .= Xml::openElement( 'div', [ 'class' => 'pr-formsection' ] );
			$htmlOut .= Xml::checkLabel(
				$this->msg( 'promoter-archive-show' )->text(),
				'promoter-showarchived',
				'promoter-showarchived',
				false
			);
			$htmlOut .= Xml::closeElement( 'div' );

			// Begin table of campaigns
			$htmlOut .= Xml::openElement( 'table',
				[
					'cellpadding' => 9,
					'width'       => '100%',
					'class'       => 'table table-striped table-hover sortable'
				]
			);

			// Table headers
			$headers = [
				$this->msg( 'promoter-campaign-name' )->escaped(),
				// $this->msg( 'promoter-campaign-linked-to' )->escaped(),
				$this->msg( 'promoter-enabled' )->escaped(),
				$this->msg( 'promoter-archive-campaign' )->escaped()
			];
			$htmlOut .= $this->tableRow( $headers, 'th' );

			// Table rows
			foreach ( $res as $row ) {
				$rowIsEnabled = ( $row->cmp_enabled == '1' );
				$rowIsArchived = ( $row->cmp_archived == '1' );

				$rowCells = '';

				// Name
				$rowCells .= Html::rawElement( 'td', [],
					Linker::link(
						$this->getTitle(),
						htmlspecialchars( $row->cmp_name ),
						[],
						[
							'method' => 'listCampaignDetail',
							'campaign' => $row->cmp_name
						]
					)
				);

				// Enabled
				$rowCells .= Html::rawElement( 'td', [ 'data-sort-value' => (int)$rowIsEnabled ],
					Xml::check(
						'enabled[]',
						$rowIsEnabled,
						array_replace(
							( !$this->editable || $rowIsArchived ) ? $readonly : [],
							[ 'value' => $row->cmp_name, 'class' => 'noshiftselect mw-pr-input-check-sort' ]
						)
					)
				);

				// Archive
				$rowCells .= Html::rawElement( 'td', [ 'data-sort-value' => (int)$rowIsArchived ],
					Xml::check(
						'archiveCampaigns[]',
						$rowIsArchived,
						array_replace(
							( !$this->editable || $rowIsEnabled ) ? $readonly : [],
							[ 'value' => $row->cmp_name, 'class' => 'noshiftselect mw-pr-input-check-sort' ]
						)
					)
				);

				// If campaign is currently active, set special class on table row.
				$classes = [];
				if ( $rowIsEnabled ) {
					$classes[] = 'pr-active-campaign';
				}
				if ( $rowIsArchived ) {
					$classes[] = 'pr-archived-item';
				}

				$htmlOut .= Html::rawElement( 'tr', [ 'class' => $classes ], $rowCells );
			}
			// End table of campaigns
			$htmlOut .= Xml::closeElement( 'table' );

			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'div', [ 'class' => 'pr-buttons pr-formsection' ] );
				$htmlOut .= Xml::submitButton( $this->msg( 'promoter-modify' )->text(),
					[
						'id'   => 'promotersubmit',
						'name' => 'promotersubmit',
						'class' => 'btn'
					]
				);
				$htmlOut .= Xml::closeElement( 'div' );
				$htmlOut .= Xml::closeElement( 'form' );
			}

		// No campaigns to show
		} else {
			$htmlOut .= $this->msg( 'promoter-no-campaigns-exist' )->escaped();
		}

		// End Manage Campaigns form
		$htmlOut .= Xml::closeElement( 'div' );

		if ( $this->editable ) {
			$request = $this->getRequest();
			// If there was an error, we'll need to restore the state of the form
			if ( $request->wasPosted() && ( $request->getVal( 'method' ) == 'addCampaign' ) ) {
				// Used to have projects & languages
			} else { // Defaults
				/*
				$start = null;
				$campaignProjects = [];
				$campaignLanguages = [];
				*/
			}

			// Begin Add a campaign form
			// Form for adding a campaign
			$htmlOut .= Xml::element( 'h2', null, $this->msg( 'promoter-add-campaign' )->text() );
			$htmlOut .= Xml::openElement( 'form', [
					'method' => 'post',
					'class' => 'form-inline',
					'role' => 'form'
				]
			);
			$htmlOut .= Html::hidden( 'title', $this->getTitle()->getPrefixedText() );
			$htmlOut .= Html::hidden( 'method', 'addCampaign' );

			// Name
			$htmlOut .= Xml::openElement( 'div', [ 'class' => 'form-group' ] );
			$htmlOut .= Xml::label( $this->msg( 'promoter-campaign-name' )->escaped(), 'campaignName', [
					'class' => 'sr-only'
				]
			);
			$htmlOut .= Xml::input( 'campaignName', 25, $request->getVal( 'campaignName' ), [
					'id' => 'campaignName',
					'placeholder' => $this->msg( 'promoter-campaign-name' )->escaped(),
					'class' => 'form-control'
				]
			);
			$htmlOut .= Xml::closeElement( 'div' );

			$htmlOut .= Html::hidden( 'change', 'weight' );
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

			// Submit button
			$htmlOut .= Xml::submitButton( $this->msg( 'promoter-modify' )->text(), [ 'class' => 'btn' ] );
			// End Add a campaign form
			$htmlOut .= Xml::closeElement( 'form' );
		}

		// Output HTML
		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Show the interface for viewing/editing an individual campaign
	 *
	 * @param $campaign string The name of the campaign to view
	 *
	 * @throws AdDataException
	 * @throws ErrorPageError
	 * @throws MWException
	 */
	function listCampaignDetail( $campaign ) {

		$c = new AdCampaign( $campaign ); // Todo: Convert the rest of this page to use this object
		try {
			if ( $c->isArchived() ) {
				$this->getOutput()->setSubtitle( $this->msg( 'promoter-archive-edit-prevented' ) );
				$this->editable = false; // Todo: Fix this gross hack to prevent editing
			}
		} catch ( AdCampaignExistenceException $ex ) {
			throw new ErrorPageError( 'promoter', 'promoter-campaign-doesnt-exist' );
		}

		// Handle form submissions from campaign detail interface
		$request = $this->getRequest();

		if ( $this->editable && $request->wasPosted() ) {
			// If what we're doing is actually serious (ie: not updating the ad
			// filter); process the request. Recall that if the serious request
			// succeeds, the page will be reloaded again.
			if ( $request->getCheck( 'ad-search' ) == false ) {

				// Check authentication token
				if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

					// Handle removing campaign
					if ( $request->getVal( 'archive' ) ) {
						AdCampaign::setBooleanCampaignSetting( $campaign, 'archived', 1 );
					}

					$initialCampaignSettings = AdCampaign::getCampaignSettings( $campaign );

					// Handle enabling/disabling campaign
					if ( $request->getCheck( 'enabled' ) ) {
						AdCampaign::setBooleanCampaignSetting( $campaign, 'enabled', 1 );
					} else {
						AdCampaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
					}

					// Handle adding of ads to the campaign
					$adsToAdd = $request->getArray( 'addAds' );
					if ( $adsToAdd ) {
						$weight = $request->getArray( 'weight' );
						foreach ( $adsToAdd as $adName ) {
							$adId = Ad::fromName( $adName )->getId();
							$result = AdCampaign::addAdTo(
								$campaign, $adName, $weight[ $adId ]
							);
							if ( $result !== true ) {
								$this->showError( $result );
							}
						}
					}

					// Handle removing of ads from the campaign
					$adToRemove = $request->getArray( 'removeAds' );
					if ( $adToRemove ) {
						foreach ( $adToRemove as $ad ) {
							AdCampaign::removeAdFor( $campaign, $ad );
						}
					}

					// Handle weight changes
					$updatedWeights = $request->getArray( 'weight' );
					$balanced = $request->getCheck( 'balanced' );
					if ( $updatedWeights ) {
						foreach ( $updatedWeights as $adId => $weight ) {
							if ( $balanced ) {
								$weight = 25;
							}
							AdCampaign::updateWeight( $campaign, $adId, $weight );
						}
					}

					$finalCampaignSettings = AdCampaign::getCampaignSettings( $campaign );
					$campaignId = AdCampaign::getCampaignId( $campaign );
					/*
					AdCampaign::logCampaignChange( 'modified', $campaignId, $this->getUser(),
						$initialCampaignSettings, $finalCampaignSettings );
					*/
					// If there were no errors, reload the page to prevent duplicate form submission
					if ( !$this->promoterError ) {
						$this->getOutput()->redirect( $this->getPageTitle()->getLocalUrl( [
								'method' => 'listCampaignDetail',
								'campaign' => $campaign
						] ) );
						return;
					}
				} else {
					$this->showError( 'sessionfailure' );
				}
			}
		}

		$htmlOut = '';

		// Begin AdCampaign detail form
		$htmlOut .= Xml::openElement( 'div', [ 'class' => 'prefsection' ] );

		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form',
				[
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL( [
						'method' => 'listCampaignDetail',
						'campaign' => $campaign
					] )
				]
			);
		}

		$output_detail = $this->campaignDetailForm( $campaign );
		$output_assigned = $this->assignedAdsForm( $campaign );
		$output_ads = $this->addAdsForm( $campaign );

		$htmlOut .= $output_detail;

		// Catch for no ads so that we don't double message
		if ( $output_assigned == '' && $output_ads == '' ) {
			$htmlOut .= $this->msg( 'promoter-no-ads' )->escaped();
			$htmlOut .= Xml::element( 'p' );
			$newPage = $this->getTitleFor( 'CampaignAd', 'add' );
			$htmlOut .= Linker::link(
				$newPage,
				$this->msg( 'promoter-add-ad' )->escaped()
			);
			$htmlOut .= Xml::element( 'p' );
		} elseif ( $output_assigned == '' ) {
			$htmlOut .= Xml::fieldset( $this->msg( 'promoter-assigned-ads' )->text() );
			$htmlOut .= $this->msg( 'promoter-no-ads-assigned' )->escaped();
			$htmlOut .= Xml::closeElement( 'fieldset' );
			if ( $this->editable ) {
				$htmlOut .= $output_ads;
			}
		} else {
			$htmlOut .= $output_assigned;
			if ( $this->editable ) {
				$htmlOut .= $output_ads;
			}
		}
		if ( $this->editable ) {
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

			// Submit button
			$htmlOut .= Xml::tags( 'div',
				[ 'class' => 'pr-buttons' ],
				Xml::submitButton( $this->msg( 'promoter-modify' )->text() )
			);
		}

		if ( $this->editable ) {
			$htmlOut .= Xml::closeElement( 'form' );
		}
		$htmlOut .= Xml::closeElement( 'div' );
		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Create form for managing campaign settings (start date, end date, languages, etc.)
	 *
	 * @param $campaignNameOrId
	 *
	 * @return string
	 */
	function campaignDetailForm( $campaignNameOrId ) {

		if ( $this->editable ) {
			$readonly = [];
		} else {
			$readonly = [ 'disabled' => 'disabled' ];
		}

		$campaign = AdCampaign::getCampaignSettings( $campaignNameOrId );

		if ( $campaign ) {
			// If there was an error, we'll need to restore the state of the form
			$request = $this->getRequest();

			if ( $request->wasPosted() ) {
				$isEnabled = $request->getCheck( 'enabled' );
				$isArchived = $request->getCheck( 'archived' );
				// $campaignNameOrId = $request->getText( 'campaign' );
				// $catPageId = $request->getInt( 'catPageId' );
			} else { // Defaults
				$isEnabled = ( $campaign[ 'enabled' ] == '1' );
				$isArchived = ( $campaign[ 'archived' ] == '1' );
				// $catPageId = (int)$campaign[ 'catPageId' ];
			}

			// Build Html
			$htmlOut = '';
			$htmlOut .= Xml::tags(
				'h2', null, $this->msg( 'promoter-campaign-heading', $campaignNameOrId )->text()
			);
			$htmlOut .= Xml::openElement( 'table', [ 'cellpadding' => 9 ] );

			// Rows

			// Allow changing campaign name
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::label( $this->msg( 'promoter-campaign-name' )->text(), 'campaign' ) );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::input( 'campaign', 30, $campaignNameOrId, array_replace( $readonly,
						[ 'id' => 'campaign' ]
					)
				)
			);
			$htmlOut .= Xml::closeElement( 'tr' );

			/*
			// Linked to Category / Page
			$catTitle = Title::newFromID( $catPageId );
			$catName = $catTitle ? $catTitle->getText() : $this->msg( 'promoter-no-assigned-cat' )->text();
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::label( $this->msg( 'promoter-campaign-linked-to' )->text(), 'catPageId' ) );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::input( 'catPageId', 30, $catName, array_replace( $readonly,
						array( 'id' => 'catPageId' ) )
				)
			);
			$htmlOut .= Xml::closeElement( 'tr' );
			*/

			// Enabled
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::label( $this->msg( 'promoter-enabled' )->text(), 'enabled' ) );
			$htmlOut .= Xml::tags( 'td', [],
				Xml::check( 'enabled', $isEnabled,
					array_replace( $readonly, [ 'value' => $campaignNameOrId, 'id' => 'enabled' ] )
				)
			);
			$htmlOut .= Xml::closeElement( 'tr' );

			if ( $this->editable ) {
				// Locked
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', [],
					Xml::label( $this->msg( 'promoter-archive-campaign' )->text(), 'archive' ) );
				$htmlOut .= Xml::tags( 'td', [],
					Xml::check( 'archive', $isArchived,
						[ 'value' => $campaignNameOrId, 'id' => 'archive' ]
					) );
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );
			return $htmlOut;
		} else {
			return '';
		}
	}

	/**
	 * Create form for managing ads assigned to a campaign
	 *
	 * @param $campaign
	 *
	 * @return string
	 * @throws AdDataException
	 */
	function assignedAdsForm( $campaign ) {

		$dbr = PRDatabase::getDb();
		$res = $dbr->select(
			// Aliases are needed to avoid problems with table prefixes
			[
				'campaigns' => 'pr_campaigns',
				'adlinks' => 'pr_adlinks',
				'ads' => 'pr_ads'
			],
			[
				'ads.ad_id',
				'ads.ad_name',
				'adlinks.adl_weight',
			],
			[
				'campaigns.cmp_name' => $campaign,
				'campaigns.cmp_id = adlinks.cmp_id',
				'adlinks.ad_id = ads.ad_id'
			],
			__METHOD__,
			[ 'ORDER BY' => 'campaigns.cmp_id' ]
		);

		// No ads found
		if ( $dbr->numRows( $res ) < 1 ) {
			return '';
		}

		if ( $this->editable ) {
			$readonly = [];
		} else {
			$readonly = [ 'disabled' => 'disabled' ];
		}

		$weights = [];

		$ads = [];
		foreach ( $res as $row ) {
			$ads[] = $row;

			$weights[] = $row->adl_weight;
		}
		$isBalanced = ( count( array_unique( $weights ) ) === 1 );

		// Build Assigned ads HTML
		$htmlOut = Html::hidden( 'change', 'weight' );
		$htmlOut .= Xml::fieldset( $this->msg( 'promoter-assigned-ads' )->text() );

		// Equal weight ads
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', [],
			Xml::label( $this->msg( 'promoter-balanced' )->text(), 'balanced' ) );
		$htmlOut .= Xml::tags( 'td', [],
			Xml::check( 'balanced', $isBalanced,
				array_replace( $readonly, [ 'value' => $campaign, 'id' => 'balanced' ] )
			)
		);
		$htmlOut .= Xml::closeElement( 'tr' );

		$htmlOut .= Xml::openElement( 'table',
			[
				'cellpadding' => 9,
				'width'       => '100%'
			]
		);
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
				$this->msg( "promoter-remove" )->text() );
		}
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%', 'class' => 'pr-weight' ],
			$this->msg( 'promoter-weight' )->text() );
		/*
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
			$this->msg( 'promoter-bucket' )->text() );
		*/
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '70%' ],
			$this->msg( 'promoter-ads' )->text() );

		// Table rows
		foreach ( $ads as $row ) {
			$htmlOut .= Xml::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
					Xml::check( 'removeAds[]', false, [ 'value' => $row->ad_name ] )
				);
			}

			// Weight
			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'pr-weight' ],
				$this->weightDropDown( "weight[$row->ad_id]", $row->adl_weight )
			);

			// Ad
			$ad = Ad::fromName( $row->ad_name );
			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
				$ad->linkToPreview()
			);

			$htmlOut .= Xml::closeElement( 'tr' );
		}
		$htmlOut .= Xml::closeElement( 'table' );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		return $htmlOut;
	}

	function weightDropDown( $name, $selected ) {
		$selected = intval( $selected );

		if ( $this->editable ) {
			$html = Html::openElement( 'select', [ 'name' => $name ] );
			foreach ( range( 5, 100, 5 ) as $value ) {
				$html .= Xml::option( $value, $value, $value === $selected );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			return htmlspecialchars( $selected );
		}
	}

	/**
	 * Create form for adding ads to a campaign
	 *
	 * @param $campaign
	 *
	 * @return string
	 */
	function addAdsForm( $campaign ) {
		// Sanitize input on search key and split out terms
		$searchTerms = $this->sanitizeSearchTerms( $this->getRequest()->getText( 'adsearchkey' ) );

		$pager = new PromoterPager( $this, $searchTerms );

		// Build HTML
		$htmlOut = Xml::fieldset( $this->msg( 'promoter-available-ads' )->text() );

		// Ad search box
		$htmlOut .= Html::openElement( 'fieldset', [ 'id' => 'pr-ad-searchbox' ] );
		$htmlOut .= Html::element(
			'legend', [ 'class' => 'sr-only' ], $this->msg( 'promoter-filter-ad-header' )->text()
		);

		$htmlOut .= Html::element(
			'label', [ 'for' => 'adsearchkey' ], $this->msg( 'promoter-filter-ad-prompt' )->text()
		);
		$htmlOut .= Html::input( 'adsearchkey', $searchTerms );
		$htmlOut .= Html::element(
			'input',
			[
				'type'=> 'submit',
				'name'=> 'ad-search',
				'value' => $this->msg( 'promoter-filter-ad-submit' )->text()
			]
		);

		$htmlOut .= Html::closeElement( 'fieldset' );

		// And now the ads, if any
		if ( $pager->getNumRows() > 0 ) {

			// Show paginated list of ads
			$htmlOut .= Xml::tags( 'div', [ 'class' => 'pr-pager' ], $pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div', [ 'class' => 'pr-pager' ], $pager->getNavigationBar() );

		} else {
			$htmlOut .= $this->msg( 'promoter-no-ads' )->escaped();
		}
		$htmlOut .= Xml::closeElement( 'fieldset' );

		return $htmlOut;
	}

	function getProjectName( $value ) {
		return $value; // @fixme -- use $this->msg()
	}

	public static function dropDownList( $text, $values ) {
		$dropDown = "*{$text}\n";
		foreach ( $values as $value ) {
			$dropDown .= "**{$value}\n";
		}
		return $dropDown;
	}

	protected function paddedRange( $begin, $end ) {
		$unpaddedRange = range( $begin, $end );
		$paddedRange = [];
		foreach ( $unpaddedRange as $number ) {
			$paddedRange[ ] = sprintf( "%02d", $number ); // pad number with 0 if needed
		}
		return $paddedRange;
	}

	function showError( $message ) {
		$this->getOutput()->wrapWikiMsg( "<div class='pr-error'>\n$1\n</div>", $message );
		$this->promoterError = true;
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 * @return null|string The sanitized value
	 */
	protected function getTextAndSanitize( $param, $regex, $default = null ) {
		if ( preg_match( $regex, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		} else {
			return $default;
		}
	}

	/**
	 * Sanitizes ad search terms by removing non alpha and ensuring space delimiting.
	 *
	 * @param $terms string Search terms to sanitize
	 *
	 * @return string Space delimited string
	 */
	public static function sanitizeSearchTerms( $terms ) {
		$retval = ' '; // The space is important... it gets trimmed later

		foreach ( preg_split( '/\s+/', $terms ) as $term ) {
			preg_match( '/[0-9a-zA-Z_\-]+/', $term, $matches );
			if ( $matches ) {
				$retval .= $matches[ 0 ];
				$retval .= ' ';
			}
		}

		return trim( $retval );
	}

	/**
	 * Adds Promoter specific navigation tabs to the UI.
	 * Implementation of SkinTemplateNavigation::SpecialPage hook.
	 *
	 * @param Skin $skin Reference to the Skin object
	 * @param array $tabs Any current skin tabs
	 *
	 * @return boolean
	 * @throws MWException
	 */
	public static function addNavigationTabs( Skin $skin, array &$tabs ) {
		global $wgPromoterTabifyPages;

		$title = $skin->getTitle();
		list( $alias, $sub ) = SpecialPageFactory::resolveAlias( $title->getText() );

		if ( !array_key_exists( $alias, $wgPromoterTabifyPages ) ) {
			return true;
		}

		foreach ( $wgPromoterTabifyPages as $page => $keys ) {
			$tabs[ $keys[ 'type' ] ][ $page ] = [
				'text' => wfMessage( $keys[ 'message' ] ),
				'href' => SpecialPage::getTitleFor( $page )->getFullURL(),
				'class' => ( $alias === $page ) ? 'selected' : ''
			];
		}

		return true;
	}

	/**
	 * Loads a Promoter variable from session data.
	 *
	 * @param string $variable Name of the variable
	 * @param object $default Default value of the variable
	 *
	 * @return object Stored variable or default
	 */
	public function getPRSessionVar( $variable, $default = null ) {
		$val = $this->getRequest()->getSessionData( "promoter-$variable" );
		if ( is_null( $val ) ) {
			$val = $default;
		}

		return $val;
	}

	/**
	 * Sets a Promoter session variable. Note that this will fail silently if a
	 * session does not exist for the user.
	 *
	 * @param string $variable Name of the variable
	 * @param object $value    Value for the variable
	 */
	public function setPRSessionVar( $variable, $value ) {
		$this->getRequest()->setSessionData( "promoter-{$variable}", $value );
	}

	protected function makeShortList( $all, $list ) {
		global $wgPromoterListComplementThreshold;
		// TODO ellipsis and js/css expansion
		if ( count( $list ) == count( $all ) ) {
			return $this->getContext()->msg( 'promoter-all' )->text();
		}
		if ( count( $list ) > $wgPromoterListComplementThreshold * count( $all ) ) {
			$inverse = array_values( array_diff( $all, $list ) );
			$txt = $this->getContext()->getLanguage()->listToText( $inverse );
			return $this->getContext()->msg( 'promoter-all-except', $txt )->text();
		}
		return $this->getContext()->getLanguage()->listToText( array_values( $list ) );
	}
}
