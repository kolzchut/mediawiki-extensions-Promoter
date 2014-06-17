<?php

class Promoter extends SpecialPage {


	var $editable, $promoterError;

	function __construct() {
		// Register special page
		parent::__construct( 'Promoter' );
	}

	/**
	 * Handle different types of page requests
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
		$out->addHTML( Xml::openElement( 'div', array( 'id' => 'preferences' ) ) );

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
						$result = Campaign::addCampaign( $campaignName, '0', $this->getUser() );
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
							Campaign::setBooleanCampaignSetting( $campaign, 'archived', 1 );
						}
					}

					// Get all the initial campaign settings for logging
					$allCampaignNames = Campaign::getAllCampaignNames();
					$allInitialCampaignSettings = array();
					foreach ( $allCampaignNames as $campaignName ) {
						$settings = Campaign::getCampaignSettings( $campaignName );
						$allInitialCampaignSettings[ $campaignName ] = $settings;
					}

					// Handle locking/unlocking campaigns
					$lockedCampaigns = $request->getArray( 'locked' );
					if ( $lockedCampaigns ) {
						// Build list of campaigns to lock
						$unlockedCampaigns = array_diff( Campaign::getAllCampaignNames(), $lockedCampaigns );

						// Set locked/unlocked flag accordingly
						foreach ( $lockedCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'locked', 1 );
						}
						foreach ( $unlockedCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'locked', 0 );
						}
					// Handle updates if no post content came through (all checkboxes unchecked)
					} else {
						$allCampaigns = Campaign::getAllCampaignNames();
						foreach ( $allCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'locked', 0 );
						}
					}

					// Handle enabling/disabling campaigns
					$enabledCampaigns = $request->getArray( 'enabled' );
					if ( $enabledCampaigns ) {
						// Build list of campaigns to disable
						$disabledCampaigns = array_diff( Campaign::getAllCampaignNames(), $enabledCampaigns );

						// Set enabled/disabled flag accordingly
						foreach ( $enabledCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'enabled', 1 );
						}
						foreach ( $disabledCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
						}
					// Handle updates if no post content came through (all checkboxes unchecked)
					} else {
						$allCampaigns = Campaign::getAllCampaignNames();
						foreach ( $allCampaigns as $campaign ) {
							Campaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
						}
					}

					// Handle setting priority on campaigns
					/*
					$preferredCampaigns = $request->getArray( 'priority' );
					if ( $preferredCampaigns ) {
						foreach ( $preferredCampaigns as $campaign => $value ) {
							Campaign::setNumericCampaignSetting(
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
						$finalCampaignSettings = Campaign::getCampaignSettings( $campaignName );
						if ( !$allInitialCampaignSettings[ $campaignName ] || !$finalCampaignSettings ) {
							// Condition where the campaign has apparently disappeared mid operations
							// -- possibly a delete call
							$diffs = false;
						} else {
							$diffs = array_diff_assoc( $allInitialCampaignSettings[ $campaignName ], $finalCampaignSettings );
						}
						// If there are changes, log them
						if ( $diffs ) {
							/*
							$campaignId = Campaign::getCampaignId( $campaignName );
							Campaign::logCampaignChange(
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
					$out->redirect( $this->getTitle()->getLocalUrl() );
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
	 */
	function tableRow( $fields, $element = 'td', $attribs = array() ) {
		$cells = array();
		foreach ( $fields as $field ) {
			$cells[ ] = Xml::tags( $element, array(), $field );
		}
		return Xml::tags( 'tr', $attribs, implode( "\n", $cells ) ) . "\n";
	}

	/**
	 * Show all campaigns found in the database, show "Add a campaign" form
	 */
	function listCampaigns() {
		// Cache these commonly used properties
		$readonly = array( 'disabled' => 'disabled' );

		//TODO: refactor to use Campaign::getCampaigns
		// Get all campaigns from the database
		$dbr = PRDatabase::getDb();
		$res = $dbr->select( 'pr_campaigns',
			array(
				'page_id',
				'cmp_name',
				'cmp_enabled',
				'cmp_archived',
				'cmp_add_general_ads',
			),
			array(),
			__METHOD__,
			array( 'ORDER BY' => 'cmp_id DESC' )
		);

		// Begin building HTML
		$htmlOut = '';

		// Begin Manage campaigns fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// If there are campaigns to show...
		if ( $res->numRows() >= 1 ) {
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
				$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );
			}
			$htmlOut .= Xml::element( 'h2', null, $this->msg( 'promoter-manage' )->text() );

			// Filters
			$htmlOut .= Xml::openElement( 'div', array( 'class' => 'pr-formsection-emphasis' ) );
			$htmlOut .= Xml::checkLabel(
				$this->msg( 'promoter-archive-show' )->text(),
				'promoter-showarchived',
				'promoter-showarchived',
				false
			);
			$htmlOut .= Xml::closeElement( 'div' );

			// Begin table of campaigns
			$htmlOut .= Xml::openElement( 'table',
				array(
					'cellpadding' => 9,
					'width'       => '100%',
					'class'       => 'wikitable sortable'
				)
			);

			// Table headers
			$headers = array(
				$this->msg( 'promoter-campaign-name' )->escaped(),
				//$this->msg( 'promoter-start-timestamp' )->escaped(),
				//$this->msg( 'promoter-end-timestamp' )->escaped(),
				$this->msg( 'promoter-enabled' )->escaped(),
				$this->msg( 'promoter-archive-campaign' )->escaped()
			);
			$htmlOut .= $this->tableRow( $headers, 'th' );

			// Table rows
			foreach ( $res as $row ) {
				$rowIsEnabled = ( $row->cmp_enabled == '1' );
				$rowIsArchived = ( $row->cmp_archived == '1' );

				$rowCells = '';

				// Name
				$rowCells .= Html::rawElement( 'td', array(),
					Linker::link(
						$this->getTitle(),
						htmlspecialchars( $row->cmp_name ),
						array(),
						array(
							'method' => 'listCampaignDetail',
							'campaign' => $row->cmp_name
						)
					)
				);

				/*
				// Date and time calculations
				$start_timestamp = wfTimestamp( TS_UNIX, $row->cmp_start );
				$end_timestamp = wfTimestamp( TS_UNIX, $row->cmp_end );

				// Start
				$rowCells .= Html::rawElement( 'td', array( 'class' => 'pr-date-column' ),
					date( '<\b>Y-m-d</\b> H:i', $start_timestamp )
				);

				// End
				$rowCells .= Html::rawElement( 'td', array( 'class' => 'pr-date-column' ),
					date( '<\b>Y-m-d</\b> H:i', $end_timestamp )
				);
				*/

				// Enabled
				$rowCells .= Html::rawElement( 'td', array( 'data-sort-value' => (int)$rowIsEnabled ),
					Xml::check(
						'enabled[]',
						$rowIsEnabled,
						array_replace(
							( !$this->editable || $rowIsArchived ) ? $readonly : array(),
							array( 'value' => $row->cmp_name, 'class' => 'noshiftselect mw-pr-input-check-sort' )
						)
					)
				);

				// Archive
				$rowCells .= Html::rawElement( 'td', array( 'data-sort-value' => (int)$rowIsArchived ),
					Xml::check(
						'archiveCampaigns[]',
						$rowIsArchived,
						array_replace(
							( !$this->editable || $rowIsEnabled ) ? $readonly : array(),
							array( 'value' => $row->cmp_name, 'class' => 'noshiftselect mw-pr-input-check-sort' )
						)
					)
				);

				// If campaign is currently active, set special class on table row.
				$classes = array();
				if (
					$rowIsEnabled
					/* &&
					wfTimestamp() > wfTimestamp( TS_UNIX, $row->cmp_start ) &&
					wfTimestamp() < wfTimestamp( TS_UNIX, $row->cmp_end )
					*/
				) {
					$classes[] = 'pr-active-campaign';
				}
				if ( $rowIsArchived ) {
					$classes[] = 'pr-archived-item';
				}

				$htmlOut .= Html::rawElement( 'tr', array( 'class' => $classes ), $rowCells );
			}
			// End table of campaigns
			$htmlOut .= Xml::closeElement( 'table' );

			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'div', array( 'class' => 'pr-buttons pr-formsection-emphasis' ) );
				$htmlOut .= Xml::submitButton( $this->msg( 'promoter-modify' )->text(),
					array(
						'id'   => 'promotersubmit',
						'name' => 'promotersubmit'
					)
				);
				$htmlOut .= Xml::closeElement( 'div' );
				$htmlOut .= Xml::closeElement( 'form' );
			}

		// No campaigns to show
		} else {
			$htmlOut .= $this->msg( 'promoter-no-campaigns-exist' )->escaped();
		}

		// End Manage Campaigns fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		if ( $this->editable ) {
			$request = $this->getRequest();
			// If there was an error, we'll need to restore the state of the form
			if ( $request->wasPosted() && ( $request->getVal( 'method' ) == 'addCampaign' ) ) {
				// Used to have projects & languages
			} else { // Defaults
				/*
				$start = null;
				$campaignProjects = array();
				$campaignLanguages = array();
				*/
			}

			// Begin Add a campaign fieldset
			$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

			// Form for adding a campaign
			$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			$htmlOut .= Xml::element( 'h2', null, $this->msg( 'promoter-add-campaign' )->text() );
			$htmlOut .= Html::hidden( 'title', $this->getTitle()->getPrefixedText() );
			$htmlOut .= Html::hidden( 'method', 'addCampaign' );

			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

			// Name
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-campaign-name' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::input( 'campaignName', 25, $request->getVal( 'campaignName' ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			/*
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-start-date' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $this->editable, $start ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-start-time' )->escaped() );
			$htmlOut .= $this->timeSelectorTd( 'start', $this->editable, $start );
			$htmlOut .= Xml::closeElement( 'tr' );
			*/

			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Html::hidden( 'change', 'weight' );
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

			// Submit button
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'pr-buttons' ),
				Xml::submitButton( $this->msg( 'promoter-modify' )->text() )
			);

			$htmlOut .= Xml::closeElement( 'form' );

			// End Add a campaign fieldset
			$htmlOut .= Xml::closeElement( 'fieldset' );
		}

		// Output HTML
		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Retrieve jquery.ui.datepicker date and homebrew time,
	 * and return as a MW timestamp string.
	 */
	function getDateTime( $prefix ) {
		global $wgRequest;
		// Check whether the user left the date field blank.
		// Interpret any form of "empty" as a blank value.
		$manual_entry = $wgRequest->getVal( "{$prefix}Date" );
		if ( !$manual_entry ) {
			return null;
		}

		$datestamp = $wgRequest->getVal( "{$prefix}Date_timestamp" );
		$timeArray = $wgRequest->getArray( $prefix );
		$timestamp = substr( $datestamp, 0, 8 ) .
			$timeArray[ 'hour' ] .
			$timeArray[ 'min' ] . '00';
		return $timestamp;
	}

	/**
	 * Show the interface for viewing/editing an individual campaign
	 *
	 * @param $campaign string The name of the campaign to view
	 * @throws ErrorPageError
	 */
	function listCampaignDetail( $campaign ) {

		$c = new Campaign( $campaign ); // Todo: Convert the rest of this page to use this object
		try {
			if ( $c->isArchived() ) {
				$this->getOutput()->setSubtitle( $this->msg( 'promoter-archive-edit-prevented' ) );
				$this->editable = false; // Todo: Fix this gross hack to prevent editing
			}
		} catch ( CampaignExistenceException $ex ) {
			throw new ErrorPageError( 'promoter', 'promoter-campaign-doesnt-exist' );
		}

		// Handle form submissions from campaign detail interface
		$request = $this->getRequest();

		if ( $this->editable && $request->wasPosted() ) {
			// If what we're doing is actually serious (ie: not updating the banner
			// filter); process the request. Recall that if the serious request
			// succeeds, the page will be reloaded again.
			if ( $request->getCheck( 'ad-search' ) == false ) {

				// Check authentication token
				if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

					// Handle removing campaign
					if ( $request->getVal( 'archive' ) ) {
						Campaign::setBooleanCampaignSetting( $campaign, 'archived', 1 );
					}

					$initialCampaignSettings = Campaign::getCampaignSettings( $campaign );

					// Handle enabling/disabling campaign
					if ( $request->getCheck( 'enabled' ) ) {
						Campaign::setBooleanCampaignSetting( $campaign, 'enabled', 1 );
					} else {
						Campaign::setBooleanCampaignSetting( $campaign, 'enabled', 0 );
					}


					// Handle adding of ads to the campaign
					$adsToAdd = $request->getArray( 'addAds' );
					if ( $adsToAdd ) {
						$weight = $request->getArray( 'weight' );
						foreach ( $adsToAdd as $adName ) {
							$adId = Ad::fromName( $adName )->getId();
							$result = Campaign::addAdTo(
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
							Campaign::removeAdFor( $campaign, $ad );
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
							Campaign::updateWeight( $campaign, $adId, $weight );
						}
					}

					$finalCampaignSettings = Campaign::getCampaignSettings( $campaign );
					$campaignId = Campaign::getCampaignId( $campaign );
					/*
					Campaign::logCampaignChange( 'modified', $campaignId, $this->getUser(),
						$initialCampaignSettings, $finalCampaignSettings );
					*/
					// If there were no errors, reload the page to prevent duplicate form submission
					if ( !$this->promoterError ) {
						$this->getOutput()->redirect( $this->getTitle()->getLocalUrl( array(
								'method' => 'listCampaignDetail',
								'campaign' => $campaign
						) ) );
						return;
					}
				} else {
					$this->showError( 'sessionfailure' );
				}
			}
		}

		$htmlOut = '';

		// Begin Campaign detail fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle()->getLocalUrl( array(
						'method' => 'listCampaignDetail',
						'campaign' => $campaign
					) )
				)
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
				array( 'class' => 'pr-buttons' ),
				Xml::submitButton( $this->msg( 'promoter-modify' )->text() )
			);
		}

		if ( $this->editable ) {
			$htmlOut .= Xml::closeElement( 'form' );
		}
		$htmlOut .= Xml::closeElement( 'fieldset' );
		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Create form for managing campaign settings (start date, end date, languages, etc.)
	 */
	function campaignDetailForm( $campaignNameOrId ) {

		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		$campaign = Campaign::getCampaignSettings( $campaignNameOrId );

		if ( $campaign ) {
			// If there was an error, we'll need to restore the state of the form
			$request = $this->getRequest();

			if ( $request->wasPosted() ) {
				//$start = $this->getDateTime( 'start' );
				//$end = $this->getDateTime( 'end' );
				$isEnabled = $request->getCheck( 'enabled' );
				$isArchived = $request->getCheck( 'archived' );
			} else { // Defaults
				//$start = $campaign[ 'start' ];
				//$end = $campaign[ 'end' ];
				$isEnabled = ( $campaign[ 'enabled' ] == '1' );
				$isArchived = ( $campaign[ 'archived' ] == '1' );
			}

			// Build Html
			$htmlOut = '';
			$htmlOut .= Xml::tags( 'h2', null, $this->msg( 'promoter-campaign-heading', $campaignNameOrId )->text() );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

			// Rows

			/*
				// Start Date
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-start-date' )->escaped() );
				$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $this->editable, $start ) );
				$htmlOut .= Xml::closeElement( 'tr' );
				// Start Time
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-start-time' )->escaped() );
				$htmlOut .= $this->timeSelectorTd( 'start', $this->editable, $start );
				$htmlOut .= Xml::closeElement( 'tr' );

				// End Date
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-end-date' )->escaped() );
				$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'end', $this->editable, $end ) );
				$htmlOut .= Xml::closeElement( 'tr' );
				// End Time
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'promoter-end-time' )->escaped() );
				$htmlOut .= $this->timeSelectorTd( 'end', $this->editable, $end );
				$htmlOut .= Xml::closeElement( 'tr' );
			*/

			// Enabled
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'promoter-enabled' )->text(), 'enabled' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::check( 'enabled', $isEnabled,
					array_replace( $readonly,
						array( 'value' => $campaign, 'id' => 'enabled' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );

			if ( $this->editable ) {
				// Locked
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(),
					Xml::label( $this->msg( 'promoter-archive-campaign' )->text(), 'archive' ) );
				$htmlOut .= Xml::tags( 'td', array(),
					Xml::check( 'archive', $isArchived,
						array( 'value' => $campaign, 'id' => 'archive' ) ) );
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
	 */
	function assignedAdsForm( $campaign ) {

		$dbr = PRDatabase::getDb();
		$res = $dbr->select(
			// Aliases are needed to avoid problems with table prefixes
			array(
				'campaigns' => 'pr_campaigns',
				'adlinks' => 'pr_adlinks',
				'ads' => 'pr_ads'
			),
			array(
				'ads.ad_id',
				'ads.ad_name',
				'adlinks.adl_weight',
			),
			array(
				'campaigns.cmp_name' => $campaign,
				'campaigns.cmp_id = adlinks.cmp_id',
				'adlinks.ad_id = ads.ad_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'campaigns.cmp_id' )
		);

		// No ads found
		if ( $dbr->numRows( $res ) < 1 ) {
			return '';
		}

		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		$weights = array();

		$banners = array();
		foreach ( $res as $row ) {
			$banners[] = $row;

			$weights[] = $row->adl_weight;
		}
		$isBalanced = ( count( array_unique( $weights ) ) === 1 );

		// Build Assigned ads HTML
		$htmlOut = Html::hidden( 'change', 'weight' );
		$htmlOut .= Xml::fieldset( $this->msg( 'promoter-assigned-ads' )->text() );

		// Equal weight ads
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::label( $this->msg( 'promoter-balanced' )->text(), 'balanced' ) );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::check( 'balanced', $isBalanced,
				array_replace( $readonly,
					array( 'value' => $campaign, 'id' => 'balanced' ) ) ) );
		$htmlOut .= Xml::closeElement( 'tr' );

		$htmlOut .= Xml::openElement( 'table',
			array(
				'cellpadding' => 9,
				'width'       => '100%'
			)
		);
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( "promoter-remove" )->text() );
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%', 'class' => 'pr-weight' ),
			$this->msg( 'promoter-weight' )->text() );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
			$this->msg( 'promoter-bucket' )->text() );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '70%' ),
			$this->msg( 'promoter-templates' )->text() );

		// Table rows
		foreach ( $banners as $row ) {
			$htmlOut .= Xml::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
					Xml::check( 'removeAdss[]', false, array( 'value' => $row->ad_name ) )
				);
			}

			// Weight
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'pr-weight' ),
				$this->weightDropDown( "weight[$row->ad_id]", $row->adl_weight )
			);


			// Ad
			$banner = Ad::fromName( $row->ad_name );
			$renderer = new AdRenderer( $this->getContext(), $banner );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$renderer->linkTo() . '<br/>' .
				$renderer->previewFieldSet()
			);

			$htmlOut .= Xml::closeElement( 'tr' );
		}
		$htmlOut .= XMl::closeElement( 'table' );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		return $htmlOut;
	}

	function weightDropDown( $name, $selected ) {
		$selected = intval($selected);

		if ( $this->editable ) {
			$html = Html::openElement( 'select', array( 'name' => $name ) );
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
	 */
	function addAdsForm( $campaign ) {
		// Sanitize input on search key and split out terms
		$searchTerms = $this->sanitizeSearchTerms( $this->getRequest()->getText( 'adsearchkey' ) );

		$pager = new PromoterPager( $this, $searchTerms );

		// Build HTML
		$htmlOut = Xml::fieldset( $this->msg( 'promoter-available-ads' )->text() );

		// Ad search box
		$htmlOut .= Html::openElement( 'fieldset', array( 'id' => 'pr-ad-searchbox' ) );
		$htmlOut .= Html::element( 'legend', null, $this->msg( 'promoter-filter-ad-banner' )->text() );

		$htmlOut .= Html::element( 'label', array( 'for' => 'adsearchkey' ), $this->msg( 'promoter-filter-ad-prompt' )->text() );
		$htmlOut .= Html::input( 'adsearchkey', $searchTerms );
		$htmlOut .= Html::element(
			'input',
			array(
				'type'=> 'submit',
				'name'=> 'ad-search',
				'value' => $this->msg( 'promoter-filter-ad-submit' )->text()
			)
		);

		$htmlOut .= Html::closeElement( 'fieldset' );

		// And now the ads, if any
		if ( $pager->getNumRows() > 0 ) {

			// Show paginated list of ads
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'pr-pager' ),
				$pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'pr-pager' ),
				$pager->getNavigationBar() );

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
		$paddedRange = array();
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
	 * @param Skin  $skin Reference to the Skin object
	 * @param array $tabs Any current skin tabs
	 *
	 * @return boolean
	 */
	public static function addNavigationTabs( Skin $skin, array &$tabs ) {
		global $wgPromoterTabifyPages;

		$title = $skin->getTitle();
		list( $alias, $sub ) = SpecialPageFactory::resolveAlias( $title->getText() );

		if ( !array_key_exists( $alias, $wgPromoterTabifyPages ) ) {
			return true;
		}

		// Clear the special page tab that's there already
		//$tabs['namespaces'] = array();

		// Now add our own
		foreach ( $wgPromoterTabifyPages as $page => $keys ) {
			$tabs[ $keys[ 'type' ] ][ $page ] = array(
				'text' => wfMessage( $keys[ 'message' ] ),
				'href' => SpecialPage::getTitleFor( $page )->getFullURL(),
				'class' => ( $alias === $page ) ? 'selected' : '',
			);
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
		//TODO ellipsis and js/css expansion
		if ( count($list) == count($all)  ) {
			return $this->getContext()->msg( 'promoter-all' )->text();
		}
		if ( count($list) > $wgPromoterListComplementThreshold * count($all) ) {
			$inverse = array_values( array_diff( $all, $list ) );
			$txt = $this->getContext()->getLanguage()->listToText( $inverse );
			return $this->getContext()->msg( 'promoter-all-except', $txt )->text();
		}
		return $this->getContext()->getLanguage()->listToText( array_values( $list ) );
	}
}
