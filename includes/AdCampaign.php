<?php

namespace MediaWiki\Extension\Promoter;

use User;

class AdCampaign {

	/** @var int */
	protected $id = null;
	/** @var string */
	protected $name = null;

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
	 * @throws AdCampaignExistenceException If lazy loading failed.
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
	 * @throws AdCampaignExistenceException If lazy loading failed.
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
	 * @throws AdCampaignExistenceException If lazy loading failed.
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
	 * @throws AdCampaignExistenceException If lazy loading failed.
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
	 * @throws AdCampaignExistenceException If the campaign doesn't exist
	 */
	protected function loadBasicSettings() {
		$db = PRDatabase::getDb();

		// What selector are we using?
		if ( $this->id !== null ) {
			$selector = [ 'cmp_id' => $this->id ];
		} elseif ( $this->name !== null ) {
			$selector = [ 'cmp_name' => $this->name ];
		} else {
			throw new AdCampaignExistenceException( "No valid database key available for campaign." );
		}

		// Get campaign info from database
		$row = $db->selectRow(
			[ 'campaigns' => 'pr_campaigns' ],
			[
				 'cmp_id',
				 'cmp_name',
				 'cmp_enabled',
				 'cmp_archived',
			],
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
			throw new AdCampaignExistenceException(
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
	 * @param string $campaignName
	 *
	 * @return bool
	 */
	public static function campaignExists( $campaignName ) {
		$dbr = PRDatabase::getDb();
		return (bool)$dbr->selectRow( 'pr_campaigns', 'cmp_name', [ 'cmp_name' => $campaignName ] );
	}

	/**
	 * Return all ads bound to the campaign
	 *
	 * @return array a 2D array of ads with settings
	 */
	public function getAds() {
		$dbr = PRDatabase::getDb();

		$ads = [];

		$res = $dbr->select(
		// Aliases (keys) are needed to avoid problems with table prefixes
			[
				'ads' => 'pr_ads',
				'adlinks' => 'pr_adlinks',
			],
			[
				'ad_name',
				'ad_display_anon',
				'ad_display_user',
			],
			[
				'adlinks.cmp_id' => $this->getId(),
				'adlinks.ad_id = ads.ad_id',
				'ads.ad_active' => 1
			],
			__METHOD__,
			[],
			[]
		);

		foreach ( $res as $row ) {
			$ads[] = [
				'name'             => $row->ad_name,
				'display_anon'     => intval( $row->ad_display_anon ),
				'display_user'     => intval( $row->ad_display_user )
			];
		}

		return $ads;
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param string $campaignName The name of the campaign
	 *
	 * @return array|bool an array of settings or false if the campaign does not exist
	 */
	public static function getCampaignSettings( $campaignName ) {
		$dbr = PRDatabase::getDb();

		// Get campaign info from database
		$row = $dbr->selectRow(
			[ 'campaigns' => 'pr_campaigns' ],
			[
				'cmp_id',
				'cmp_enabled',
				'cmp_archived',
			],
			[ 'cmp_name' => $campaignName ],
			__METHOD__
		);
		if ( $row ) {
			$campaign = [
				'enabled'   => $row->cmp_enabled,
				'archived'  => $row->cmp_archived,
			];
		} else {
			return false;
		}

		$campaignObj = new AdCampaign( $campaignName );
		$adsIn = $campaignObj->getAds();
		$adsOut = [];
		// All we want are the ad names
		foreach ( $adsIn as $key => $row ) {
			$outKey = $adsIn[ $key ][ 'name' ];
			$adsOut[ $outKey ]['weight'] = $adsIn[ $key ][ 'weight' ];
		}
		// Encode into a JSON string for storage
		$campaign[ 'ads' ] = \FormatJson::encode( $adsOut );

		return $campaign;
	}

	/**
	 * Get all the campaigns in the database, even disabled and archived campaigns
	 * This is made available for b/c, and calls getCampaignNames() internally
	 *
	 * @return array an array of campaign names
	 */
	public static function getAllCampaignNames() {
		return self::getCampaignNames( false, true );
	}

	/**
	 * Get campaign names from DB
	 *
	 * @param bool $enabled - get only enabled campaigns
	 * @param bool $archived - get archived campaigns as well?
	 *
	 * @return array an array of campaign names
	 */
	public static function getCampaignNames( $enabled = true, $archived = false ) {
		$dbr = PRDatabase::getDb();
		$conds = [];
		if ( $enabled === true ) {
			$conds[ 'cmp_enabled'] = 1;
		}
		if ( $archived === false ) {
			$conds[ 'cmp_archived'] = 0;
		}

		$res = $dbr->select( 'pr_campaigns', 'cmp_name', $conds, __METHOD__ );
		$campaigns = [];
		foreach ( $res as $row ) {
			$campaigns[ ] = $row->cmp_name;
		}
		return $campaigns;
	}

	/**
	 * Get a limited number of ads from certain campaigns,
	 * with the option of excluding certain ads by their URLs.
	 *
	 * @param array $campaigns Campaign names to fetch ads from
	 * @param array $urls URLs to exclude from result (to prevent duplicate entries)
	 * @param int $limit Number of max ads to fetch
	 * @return array Array of resulting ads
	 */
	public static function getCampaignAds( array $campaigns = [], array $urls = [], int $limit = 2 ) {
		if ( empty( $campaigns ) ) {
			return [];
		}
		$ads       = [];
		$campaigns = str_replace( ' ', '_', $campaigns );
		$dbr       = wfGetDB( DB_REPLICA );

		$now = $dbr->timestamp();

		$result = $dbr->select(
			[
				'ads'     => 'pr_ads',
				'adlinks' => 'pr_adlinks',
				'cmp'     => 'pr_campaigns'
			],
			[ 'ads.*' ],
			[
				'cmp.cmp_enabled' => 1,
				'ads.ad_active'   => 1,
				'cmp.cmp_name'    => $campaigns,
				'ads.ad_date_end > ' . $now . ' OR ads.ad_date_end IS NULL',
				'ads.ad_date_start < ' . $now . ' OR ads.ad_date_start IS NULL',
				'ads.ad_mainlink NOT IN (' . $dbr->makeList( $urls ) . ')'
			],
			__METHOD__,
			[
				'ORDER BY' => 'RAND()',
				'LIMIT'    => $limit
			],
			[
				'cmp'     => [ 'INNER JOIN', [ 'cmp.cmp_id=adlinks.cmp_id' ] ],
				'adlinks' => [ 'INNER JOIN', [ 'ads.ad_id=adlinks.ad_id' ] ]
			]
		);

		foreach ( $result as $row ) {
			$ads[] = $row;
		}

		return $ads;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param string $campaignName Name of the campaign
	 * @param int $enabled Boolean setting, 0 or 1
	 * @param User $user adding the campaign
	 *
	 * @throws \MWException
	 * @return int|string campaignId on success, or message key for error
	 */
	public static function addCampaign( $campaignName, $enabled, $user ) {
		$campaignName = trim( $campaignName );
		if ( self::campaignExists( $campaignName ) ) {
			return 'promoter-campaign-exists';
		}

		$dbw = PRDatabase::getDb();
		$dbw->insert(
			'pr_campaigns',
			[
				'cmp_name'    => $campaignName,
				'cmp_enabled' => $enabled,
			]
		);
		$cmp_id = $dbw->insertId();

		if ( !$cmp_id ) {
			throw new \MWException( 'insertId() did not return a value.' );
		}

		return $cmp_id;
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param string $campaignName Name of the campaign
	 * @param User $user removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	public static function removeCampaign( $campaignName, $user ) {
		$dbr = PRDatabase::getDb();

		$res = $dbr->select( 'pr_campaigns', 'cmp_name',
			[ 'cmp_name' => $campaignName ]
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			return 'promoter-remove-campaign-doesnt-exist';
		}

		self::removeCampaignByName( $campaignName, $user );

		return true;
	}

	/**
	 * @param string $campaignName
	 * @param User $user
	 */
	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = self::getCampaignId( $campaignName );
		// Campaign::logCampaignChange( 'removed', $campaignId, $user );

		$dbw = PRDatabase::getDb();
		$dbw->delete( 'pr_adlinks', [ 'cmp_id' => $campaignId ] );
		$dbw->delete( 'pr_campaigns', [ 'cmp_name' => $campaignName ] );
	}

	/**
	 * Assign an ad to a campaign
	 * @param string $campaignName
	 * @param int $adId
	 * @return bool|string True on success, string with message key for error
	 *
	 * @todo we should probably validate the ad's ID
	 */
	public static function addAdTo( $campaignName, $adId ) {
		$dbw = PRDatabase::getDb();
		$campaignId = self::getCampaignId( $campaignName );
		$res = $dbw->select( 'pr_adlinks', 'adl_id',
			[
				'ad_id' => $adId,
				'cmp_id' => $campaignId
			]
		);

		if ( $dbw->numRows( $res ) > 0 ) {
			return 'promoter-ad-already-linked';
		}

		$campaignId = self::getCampaignId( $campaignName );
		$dbw->insert( 'pr_adlinks',
			[
				'ad_id'     => $adId,
				'cmp_id'     => $campaignId
			]
		);

		return true;
	}

	/**
	 * Remove an ad assignment from a campaign
	 *
	 * @param string $campaignName
	 * @param int $adId
	 *
	 * @todo we should probably validate the ad's ID
	 */
	public static function removeAdFor( $campaignName, $adId ) {
		$dbw = PRDatabase::getDb();
		$campaignId = self::getCampaignId( $campaignName );
		$dbw->delete( 'pr_adlinks', [ 'ad_id' => $adId, 'cmp_id' => $campaignId ] );
	}

	/**
	 * Add an ad to multiple campaigns based on an array of campaign IDs
	 *
	 * @param array $campaignIds Array of IDs of target campaigns
	 * @param int $adId Ad ID
	 * @return bool
	 */
	public static function addAdToCampaigns( $campaignIds, $adId ) {
		if ( empty( $campaignIds ) ) {
			return false;
		}

		$dbw = PRDatabase::getDb();

		$rows = [];
		foreach ( $campaignIds as $key => $id ) {
			$rows[] = [
				'cmp_id'     => $id,
				'ad_id'      => $adId
			];
		}

		$dbw->insert( 'pr_adlinks', $rows );

		return true;
	}

	/**
	 * * Remove an ad from multiple campaigns based on an array of campaign IDs
	 *
	 * @param array $campaignIds Array of IDs of target campaigns
	 * @param int $adId Ad ID
	 * @return bool
	 */
	public static function removeAdForCampaigns( $campaignIds, $adId ) {
		if ( empty( $campaignIds ) ) {
			return false;
		}

		$dbw = PRDatabase::getDb();

		$dbw->delete( 'pr_adlinks', [
			'ad_id'  => $adId,
			'cmp_id' => $campaignIds
		] );

		return true;
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 *
	 * @param string $campaignName
	 *
	 * @return null|string
	 */
	public static function getCampaignId( $campaignName ) {
		$dbr = PRDatabase::getDb();
		$row = $dbr->selectRow( 'pr_campaigns', 'cmp_id', [ 'cmp_name' => $campaignName ] );
		if ( $row ) {
			return $row->cmp_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 *
	 * @param int $campaignId
	 *
	 * @return string|null
	 */
	public static function getCampaignName( $campaignId ) {
		$dbr = PRDatabase::getDb();
		if ( is_numeric( $campaignId ) ) {
			$row = $dbr->selectRow( 'pr_campaigns', 'cmp_name', [ 'cmp_id' => $campaignId ] );
			if ( $row ) {
				return $row->cmp_name;
			}
		}
		return null;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param string $campaignName Name of the campaign
	 * @param string $settingName Name of a boolean setting (enabled, locked, or geo)
	 * @param int $settingValue Value to use for the setting, 0 or 1
	 */
	public static function setBooleanCampaignSetting( $campaignName, $settingName, $settingValue ) {
		if ( !self::campaignExists( $campaignName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				[ 'cmp_' . $settingName => $settingValue ],
				[ 'cmp_name' => $campaignName ]
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
	 * @throws \MWException|\RangeException
	 */
	public static function setNumericCampaignSetting(
		$campaignName, $settingName, $settingValue, $max = 1, $min = 0
	) {
		if ( $max <= $min ) {
			throw new \RangeException( 'Max must be greater than min.' );
		}

		if ( !is_numeric( $settingValue ) ) {
			throw new \MWException( 'Setting value must be numeric.' );
		}

		if ( $settingValue > $max ) {
			$settingValue = $max;
		}

		if ( $settingValue < $min ) {
			$settingValue = $min;
		}

		if ( !self::campaignExists( $campaignName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = PRDatabase::getDb();
			$dbw->update( 'pr_campaigns',
				[ 'cmp_' . $settingName => $settingValue ],
				[ 'cmp_name' => $campaignName ]
			);
		}
	}

}
