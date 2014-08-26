<?php

class Campaign {

	protected $id = null;
	protected $name = null;

	/** @var int the page / category the campaign is linked to */
	//protected $catPageId = null;

	/** @var bool True if the campaign is enabled for showing */
	protected $enabled = null;

	/** @var bool True if the campaign has been moved to the archive */
	protected $archived = null;

	/**
	 * Construct a lazily loaded Promoter campaign object
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
			array('campaigns' => 'pr_campaigns'),
			array(
				 'cmp_id',
				 'cmp_name',
				 'cmp_enabled',
				 'cmp_archived',
			),
			$selector,
			__METHOD__
		);

		/*
		echo '<pre dir="ltr">';
		print_r($row);
		echo '</pre>';
		*/

		if ( $row ) {
			$this->id = (int)$row->cmp_id;
			$this->name = $row->cmp_name;
			$this->enabled = (bool)$row->cmp_enabled;
			$this->archived = (bool)$row->cmp_archived;
		} else {
			throw new CampaignExistenceException(
				"Campaign could not be retrieved from database with id '{$this->id}' or name '{$this->name}'"
			);
		}

		/*
		echo '<pre dir="ltr">';
		print_r($this);
		echo '</pre>';
		*/
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
	 * Return all ads bound to the campaign
	 *
	 * @return array a 2D array of ads with associated weights and settings
	 */
	function getAds() {
		$dbr = PRDatabase::getDb();

		$ads = array();

		$res = $dbr->select(
		// Aliases (keys) are needed to avoid problems with table prefixes
			array(
				'ads' => 'pr_ads',
				'adlinks' => 'pr_adlinks',
			),
			array(
				'ad_name',
				'adl_weight',
				'ad_display_anon',
				'ad_display_user',
			),
			array(
				'adlinks.cmp_id' => $this->getId(),
				'adlinks.ad_id = ads.ad_id'
			),
			__METHOD__,
			array(),
			array()
		);

		foreach ( $res as $row ) {
			$ads[] = array(
				'name'             => $row->ad_name, // name of the ad
				'weight'           => intval( $row->adl_weight ), // weight assigned to the ad
				'display_anon'     => intval( $row->ad_display_anon ), // display to anonymous users?
				'display_user'     => intval( $row->ad_display_user ), // display to logged in users?
			);
		}

		return $ads;
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
			array('campaigns' => 'pr_campaigns'),
			array(
				'cmp_id',
				'cmp_enabled',
				'cmp_archived',
			),
			array( 'cmp_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				'enabled'   => $row->cmp_enabled,
				'archived'  => $row->cmp_archived,
			);
		} else {
			return false;
		}

		$campaignObj = new Campaign( $campaignName );
		$adsIn = $campaignObj->getAds();
		$adsOut = array();
		// All we want are the ad names and weights
		foreach ( $adsIn as $key => $row ) {
			$outKey = $adsIn[ $key ][ 'name' ];
			$adsOut[ $outKey ]['weight'] = $adsIn[ $key ][ 'weight' ];
		}
		// Encode into a JSON string for storage
		$campaign[ 'ads' ] = FormatJson::encode( $adsOut );

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
		$campaigns = array();
		foreach ( $res as $row ) {
			$campaigns[ ] = $row->cmp_name;
		}
		return $campaigns;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param $campaignName        string: Name of the campaign
	 * @param $enabled           int: Boolean setting, 0 or 1
	 * @param $user              User adding the campaign
	 *
	 * @throws MWException
	 * @internal param int $catPageId : Page / Category the campaign is linked to
	 * @return int|string campaignId on success, or message key for error
	 */
	//	static function addCampaign( $campaignName, $catPageId = 0, $enabled, $user ) {
	static function addCampaign( $campaignName, $enabled, $user ) {
		$campaignName = trim( $campaignName );
		if ( Campaign::campaignExists( $campaignName ) ) {
			return 'promoter-campaign-exists';
		}


		$dbw = PRDatabase::getDb();
		$dbw->begin();

		$dbw->insert( 'pr_campaigns',
			array( 'cmp_name'    => $campaignName,
				'cmp_enabled' => $enabled,
			)
		);
		$cmp_id = $dbw->insertId();

		if ( $cmp_id ) {

			$dbw->commit();

			// Log the creation of the campaign
			/*
			$beginSettings = array();
			$endSettings = array(
				//'start'     => $dbw->timestamp( $startTs ),
				//'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => $enabled,
			);
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
			return 'promoter-remove-campaign-doesnt-exist';
		}

		Campaign::removeCampaignByName( $campaignName, $user );

		return true;
	}

	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = Campaign::getCampaignId( $campaignName );
		//Campaign::logCampaignChange( 'removed', $campaignId, $user );

		$dbw = PRDatabase::getDb();
		$dbw->begin();
		$dbw->delete( 'pr_adlinks', array( 'cmp_id' => $campaignId ) );
		$dbw->delete( 'pr_campaigns', array( 'cmp_name' => $campaignName ) );
		$dbw->commit();
	}

	/**
	 * Assign an ad to a campaign at a certain weight
	 * @param $campaignName string
	 * @param $adName string
	 * @param $weight
	 * @return bool|string True on success, string with message key for error
	 */
	static function addAdTo( $campaignName, $adName, $weight ) {
		$dbw = PRDatabase::getDb();

		$eCampaignName = htmlspecialchars( $campaignName );
		$campaignId = Campaign::getCampaignId( $eCampaignName );
		$adId = Ad::fromName( $adName )->getId();
		$res = $dbw->select( 'pr_adlinks', 'adl_id',
			array(
				'ad_id' => $adId,
				'cmp_id' => $campaignId
			)
		);

		if ( $dbw->numRows( $res ) > 0 ) {
			return 'promoter-ad-already-exists';
		}

		$dbw->begin();
		$campaignId = Campaign::getCampaignId( $eCampaignName );
		$dbw->insert( 'pr_adlinks',
			array(
				'ad_id'     => $adId,
				'adl_weight' => $weight,
				'cmp_id'     => $campaignId
			)
		);
		$dbw->commit();

		return true;
	}

	/**
	 * Remove an ad assignment from a campaign
	 */
	static function removeAdFor( $campaignName, $adName ) {
		$dbw = PRDatabase::getDb();
		$dbw->begin();
		$campaignId = Campaign::getCampaignId( $campaignName );
		$adId = Ad::fromName( $adName )->getId();
		$dbw->delete( 'pr_adlinks', array( 'ad_id' => $adId, 'cmp_id' => $campaignId ) );
		$dbw->commit();
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	static function getCampaignId( $campaignName ) {
		$dbr = PRDatabase::getDb();
		$eCampaignName = htmlspecialchars( $campaignName );
		$row = $dbr->selectRow( 'pr_campaigns', 'cmp_id', array( 'cmp_name' => $eCampaignName ) );
		if ( $row ) {
			return $row->cmp_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 */
	static function getCampaignName( $campaignId ) {
		$dbr = PRDatabase::getDb();
		if ( is_numeric( $campaignId ) ) {
			$row = $dbr->selectRow( 'pr_campaigns', 'cmp_name', array( 'cmp_id' => $campaignId ) );
			if ( $row ) {
				return $row->cmp_name;
			}
		}
		return null;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param $campaignName string: Name of the campaign
	 * @param $settingName string: Name of a boolean setting (enabled, locked, or geo)
	 * @param $settingValue int: Value to use for the setting, 0 or 1
	 */
	static function setBooleanCampaignSetting( $campaignName, $settingName, $settingValue ) {
		if ( !Campaign::campaignExists( $campaignName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				array( 'cmp_' . $settingName => $settingValue ),
				array( 'cmp_name' => $campaignName )
			);
		}
	}

	/**
	 * Updates a numeric setting on a campaign
	 *
	 * @param string $campaignName Name of the campaign
	 * @param string $settingName Name of a numeric setting (preferred)
	 * @param int $settingValue Value to use
	 * @param int $max The max that the value can take, default 1
	 * @param int $min The min that the value can take, default 0
	 * @throws MWException|RangeException
	 */
	static function setNumericCampaignSetting( $campaignName, $settingName, $settingValue, $max = 1, $min = 0 ) {
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

		if ( !Campaign::campaignExists( $campaignName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				array( 'cmp_'.$settingName => $settingValue ),
				array( 'cmp_name' => $campaignName )
			);
		}
	}

	/**
	 * Updates the weight of a ad in a campaign.
	 *
	 * @param $campaignName String Name of the campaign to update
	 * @param $adId   		Int ID of the ad in the campaign
	 * @param $weight       Int New ad weight
	 */
	static function updateWeight( $campaignName, $adId, $weight ) {
		$dbw = PRDatabase::getDb();
		$campaignId = Campaign::getCampaignId( $campaignName );
		$dbw->update( 'pr_adlinks',
			array( 'adl_weight' => $weight ),
			array(
				'ad_id' => $adId,
				'cmp_id' => $campaignId
			)
		);
	}

}

class CampaignExistenceException extends MWException {}

