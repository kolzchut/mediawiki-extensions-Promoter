<?php

class Campaign {

	protected $id = null;
	protected $name = null;

	/** @var bool True if the campaign is enabled for showing */
	protected $enabled = null;


	/** @var bool True if the campaign has been moved to the archive */
	protected $archived = null;

	/**
	 * Construct a lazily loaded CentralNotice campaign object
	 *
	 * @param string|int $campaignIdentifier Either an ID or name for the campaign
	 */
	public function __construct( $campaignIdentifier ) {
		if ( is_int( $campaignIdentifier ) ) {
			$this->id = $campaignIdentifier;
		} else {
			$this->name = $campaignIdentifier;
		}
	}

	/**
	 * Get the unique numerical ID for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getId() {
		if ( $this->id === null ) {
			$this->loadBasicSettings();
		}

		return $this->id;
	}

	/**
	 * Get the unique name for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return string
	 */
	public function getName() {
		if ( $this->name === null ) {
			$this->loadBasicSettings();
		}

		return $this->name;
	}


	/**
	 * Returns the enabled/disabled status of the campaign.
	 *
	 * If a campaign is enabled it is eligible to be shown to users.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isEnabled() {
		if ( $this->enabled === null ) {
			$this->loadBasicSettings();
		}

		return $this->enabled;
	}

	/**
	 * Returns the archival status of the campaign. An archived campaign is not allowed to be
	 * edited.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isArchived() {
		if ( $this->archived === null ) {
			$this->loadBasicSettings();
		}

		return $this->archived;
	}


	/**
	 * Load basic campaign settings from the database table pr_campaigns
	 *
	 * @throws CampaignExistenceException If the campaign doesn't exist
	 */
	protected function loadBasicSettings() {
		$db = PRDatabase::getDb();

		// What selector are we using?
		if ( $this->id !== null ) {
			$selector = array( 'cmp_id' => $this->id );
		} elseif ( $this->name !== null ) {
			$selector = array( 'cmp_name' => $this->name );
		} else {
			throw new CampaignExistenceException( "No valid database key available for campaign." );
		}

		// Get campaign info from database
		$row = $db->selectRow(
			array('notices' => 'pr_campaigns'),
			array(
				 'cmp_id',
				 'cmp_name',
				 //'cmp_start',
				 //'cmp_end',
				 'cmp_enabled',
				 'cmp_archived',
			),
			$selector,
			__METHOD__
		);
		if ( $row ) {
			//$this->start = new MWTimestamp( $row->cmp_start );
			//$this->end = new MWTimestamp( $row->cmp_end );
			$this->enabled = (bool)$row->cmp_enabled;
			$this->archived = (bool)$row->cmp_archived;
		} else {
			throw new CampaignExistenceException(
				"Campaign could not be retrieved from database with id '{$this->id}' or name '{$this->name}'"
			);
		}
	}

	/**
	 * See if a given campaign exists in the database
	 *
	 * @param $campaignName string
	 *
	 * @return bool
	 */
	static function campaignExists( $campaignName ) {
		$dbr = PRDatabase::getDb();

		$eCampaignName = htmlspecialchars( $campaignName );
		return (bool)$dbr->selectRow( 'pr_campaigns', 'cmp_name', array( 'cmp_name' => $eCampaignName ) );
	}

	/**
	 * Returns a list of campaigns. May be filtered on optional constraints.
	 * By default returns only enabled and active campaigns in all projects, languages and
	 * countries.
	 *
	 * @param bool $enabled If true, select only active campaigns. If false select all.
	 * @param bool $archived If true: only archived; false: only active; null; all.
	 *
	 * @return array Array of campaign IDs that matched the filter.
	 */
	static function getCampaigns( $enabled = true, $archived = false ) {
		$notices = array();

		// Database setup
		$dbr = PRDatabase::getDb();

		// Therefore... construct the common components : pr_campaigns

		$tables = array( 'notices' => 'pr_campaigns' );

		if ( $enabled ) {
			$conds[ 'cmp_enabled' ] = 1;
		}

		if ( $archived === true ) {
			$conds[ 'cmp_archived' ] = 1;
		} elseif ( $archived === false ) {
			$conds[ 'cmp_archived' ] = 0;
		}

		// Pull the notice IDs of the campaigns
		$res = $dbr->select(
			$tables,
			'cmp_id',
			$conds,
			__METHOD__
		);
		foreach ( $res as $row ) {
			$notices[ ] = $row->cmp_id;
		}


		return $notices;
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param $campaignName string: The name of the campaign
	 *
	 * @return array|bool an array of settings or false if the campaign does not exist
	 */
	static function getCampaignSettings( $campaignName ) {
		$dbr = PRDatabase::getDb();

		// Get campaign info from database
		$row = $dbr->selectRow(
			array('notices' => 'pr_campaigns'),
			array(
				'cmp_id',
				//'cmp_start',
				//'cmp_end',
				'cmp_enabled',
				'cmp_archived',
			),
			array( 'cmp_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				//'start'     => $row->cmp_start,
				//'end'       => $row->cmp_end,
				'enabled'   => $row->cmp_enabled,
				'archived'  => $row->cmp_archived,
			);
		} else {
			return false;
		}

		$bannersIn = Banner::getCampaignBanners( $row->cmp_id, true );
		$bannersOut = array();
		// All we want are the banner names, weights, and buckets
		foreach ( $bannersIn as $key => $row ) {
			$outKey = $bannersIn[ $key ][ 'name' ];
			$bannersOut[ $outKey ]['weight'] = $bannersIn[ $key ][ 'weight' ];
		}
		// Encode into a JSON string for storage
		$campaign[ 'banners' ] = FormatJson::encode( $bannersOut );

		return $campaign;
	}

	/**
	 * Get all the campaigns in the database
	 *
	 * @return array an array of campaign names
	 */
	static function getAllCampaignNames() {
		$dbr = PRDatabase::getDb();
		$res = $dbr->select( 'pr_campaigns', 'cmp_name', null, __METHOD__ );
		$notices = array();
		foreach ( $res as $row ) {
			$notices[ ] = $row->cmp_name;
		}
		return $notices;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param $noticeName        string: Name of the campaign
	 * @param $enabled           int: Boolean setting, 0 or 1
	 * @param $user              User adding the campaign
	 *
	 * @throws MWException
	 * @return int|string noticeId on success, or message key for error
	 */
	static function addCampaign( $noticeName, $enabled, $user ) {
		$noticeName = trim( $noticeName );
		if ( Campaign::campaignExists( $noticeName ) ) {
			return 'promoter-campaign-exists';
		}


		$dbw = PRDatabase::getDb();
		$dbw->begin();

		$dbw->insert( 'pr_campaigns',
			array( 'cmp_name'    => $noticeName,
				'cmp_enabled' => $enabled,
				//'cmp_start'   => $dbw->timestamp( $startTs ),
				//'cmp_end'     => $dbw->timestamp( $endTs ),
			)
		);
		$cmp_id = $dbw->insertId();

		if ( $cmp_id ) {

			$dbw->commit();

			// Log the creation of the campaign
			$beginSettings = array();
			$endSettings = array(
				//'start'     => $dbw->timestamp( $startTs ),
				//'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => $enabled,
			);
			/*
			Campaign::logCampaignChange( 'created', $cmp_id, $user,
				$beginSettings, $endSettings );
			*/
			return $cmp_id;
		}

		throw new MWException( 'insertId() did not return a value.' );
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param $campaignName string: Name of the campaign
	 * @param $user User removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	static function removeCampaign( $campaignName, $user ) {
		$dbr = PRDatabase::getDb();

		$res = $dbr->select( 'pr_campaigns', 'cmp_name',
			array( 'cmp_name' => $campaignName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			return 'centralnotice-remove-campaign-doesnt-exist';
		}
		$row = $dbr->fetchObject( $res );

		Campaign::removeCampaignByName( $campaignName, $user );

		return true;
	}

	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = Campaign::getNoticeId( $campaignName );
		Campaign::logCampaignChange( 'removed', $campaignId, $user );

		$dbw = PRDatabase::getDb();
		$dbw->begin();
		$dbw->delete( 'cn_assignments', array( 'cmp_id' => $campaignId ) );
		$dbw->delete( 'pr_campaigns', array( 'cmp_name' => $campaignName ) );
		$dbw->commit();
	}

	/**
	 * Assign a banner to a campaign at a certain weight
	 * @param $noticeName string
	 * @param $templateName string
	 * @param $weight
	 * @return bool|string True on success, string with message key for error
	 */
	static function addTemplateTo( $noticeName, $templateName, $weight ) {
		$dbw = PRDatabase::getDb();

		$eNoticeName = htmlspecialchars( $noticeName );
		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$res = $dbw->select( 'cn_assignments', 'asn_id',
			array(
				'tmp_id' => $templateId,
				'cmp_id' => $noticeId
			)
		);

		if ( $dbw->numRows( $res ) > 0 ) {
			return 'centralnotice-template-already-exists';
		}

		$dbw->begin();
		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$dbw->insert( 'cn_assignments',
			array(
				'tmp_id'     => $templateId,
				'tmp_weight' => $weight,
				'cmp_id'     => $noticeId
			)
		);
		$dbw->commit();

		return true;
	}

	/**
	 * Remove a banner assignment from a campaign
	 */
	static function removeTemplateFor( $noticeName, $templateName ) {
		$dbw = PRDatabase::getDb();
		$dbw->begin();
		$noticeId = Campaign::getNoticeId( $noticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$dbw->delete( 'cn_assignments', array( 'tmp_id' => $templateId, 'cmp_id' => $noticeId ) );
		$dbw->commit();
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	static function getNoticeId( $noticeName ) {
		$dbr = PRDatabase::getDb();
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'pr_campaigns', 'cmp_id', array( 'cmp_name' => $eNoticeName ) );
		if ( $row ) {
			return $row->cmp_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 */
	static function getNoticeName( $noticeId ) {
		$dbr = PRDatabase::getDb();
		if ( is_numeric( $noticeId ) ) {
			$row = $dbr->selectRow( 'pr_campaigns', 'cmp_name', array( 'cmp_id' => $noticeId ) );
			if ( $row ) {
				return $row->cmp_name;
			}
		}
		return null;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param $noticeName string: Name of the campaign
	 * @param $settingName string: Name of a boolean setting (enabled, locked, or geo)
	 * @param $settingValue int: Value to use for the setting, 0 or 1
	 */
	static function setBooleanCampaignSetting( $noticeName, $settingName, $settingValue ) {
		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				array( 'cmp_' . $settingName => $settingValue ),
				array( 'cmp_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates a numeric setting on a campaign
	 *
	 * @param string $noticeName Name of the campaign
	 * @param string $settingName Name of a numeric setting (preferred)
	 * @param int $settingValue Value to use
	 * @param int $max The max that the value can take, default 1
	 * @param int $min The min that the value can take, default 0
	 * @throws MWException|RangeException
	 */
	static function setNumericCampaignSetting( $noticeName, $settingName, $settingValue, $max = 1, $min = 0 ) {
		if ( $max <= $min ) {
			throw new RangeException( 'Max must be greater than min.' );
		}

		if ( !is_numeric( $settingValue ) ) {
			throw new MWException( 'Setting value must be numeric.' );
		}

		if ( $settingValue > $max ) {
			$settingValue = $max;
		}

		if ( $settingValue < $min ) {
			$settingValue = $min;
		}

		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				array( 'cmp_'.$settingName => $settingValue ),
				array( 'cmp_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates the weight of a banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $weight       New banner weight
	 */
	static function updateWeight( $noticeName, $templateId, $weight ) {
		$dbw = PRDatabase::getDb();
		$noticeId = Campaign::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'tmp_weight' => $weight ),
			array(
				'tmp_id' => $templateId,
				'cmp_id' => $noticeId
			)
		);
	}

}

class CampaignExistenceException extends MWException {}
