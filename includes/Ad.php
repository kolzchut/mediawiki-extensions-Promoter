<?php
/**
 * This file is part of the Promoter Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:Promoter
 *
 * @section LICENSE
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
 *
 * @file
 */

/**
 * Promoter ad object. Ads are pieces of rendered wikimarkup
 * injected as HTML onto MediaWiki pages via the sitenotice hook.
 *
 * - They allow 'mixins', pieces of javascript that add additional standard
 *   functionality to the ad.
 * - They have a concept of 'messages' which are translatable strings marked
 *   out by {{{name}}} in the ad body.
 *
 * @see AdChooser
 * @see AdMessage
 */
class Ad {
	/**
	 * Keys indicate a group of properties (which should be a 1-to-1 match to
	 * a database table.) If the value is null it means the data is not yet
	 * loaded. True means the data is clean and not modified. False means the
	 * data should be saved on the next call to save().
	 *
	 * Most functions should only ever set the flag to true; flags will be
	 * reset to false in save().
	 *
	 * @var null|bool[]
	 */
	protected $dirtyFlags = array(
		'content' => null,
		'messages' => null,
		'basic' => null,
	);

	// !!! NOTE !!! It is not recommended to use directly. It is almost always more
	//              correct to use the accessor/setter function.

	/** @var int Unique database identifier key. */
	protected $id = null;

	/** @var string Unique human friendly name of ad. */
	protected $name = null;

	/** @var bool True if the ad should be allocated to anonymous users. */
	protected $allocateAnon = false;

	/** @var bool True if the ad should be allocated to logged in users. */
	protected $allocateUser = false;

	/** @var bool True if archived and hidden from default view. */
	protected $archived = false;


	/** @var string Wikitext content of the ad */
	protected $bodyContent = '';

	/** @var string Heading/caption of the ad */
	protected $adCaption = '';

	/** @var string Main link of the ad */
	protected $adLink = '';

	/** @var bool Ad active status */
	protected $active = false;

	//</editor-fold>

	/**
	 * @var string Pattern for bool
	 */
	const BOOLEAN_PARAM_FILTER = '/true|false/';

	//<editor-fold desc="Constructors">
	/**
	 * Create an ad object from a known ID. Must already be
	 * an object in the database. If a fully new ad is to be created
	 * use @see newFromName().
	 *
	 * @param int $id Unique database ID of the ad
	 *
	 * @return Ad
	 */
	public static function fromId( $id ) {
		$obj = new Ad();
		$obj->id = $id;
		return $obj;
	}

	/**
	 * Create an ad object from a known ad name. Must already be
	 * an object in the database. If a fully new ad is to be created
	 * use @see newFromName().
	 *
	 * @param $name
	 *
	 * @return Ad
	 * @throws AdDataException
	 */
	public static function fromName( $name ) {
		if ( !Ad::isValidAdName( $name ) ) {
			throw new AdDataException( "Invalid ad name supplied." );
		}

		$obj = new Ad();
		$obj->name = $name;
		return $obj;
	}

	/**
	 * Create a brand new ad object.
	 *
	 * @param $name
	 *
	 * @return Ad
	 * @throws AdDataException
	 */
	public static function newFromName( $name ) {
		if ( !Ad::isValidAdName( $name ) ) {
			throw new AdDataException( "Invalid ad name supplied." );
		}

		$obj = new Ad();
		$obj->name = $name;

		foreach ( $obj->dirtyFlags as $flag => &$value ) {
			$value = true;
		}

		return $obj;
	}
	//</editor-fold>

	//<editor-fold desc="Basic metadata getters/setters">
	/**
	 * Get the unique ID for this ad.
	 *
	 * @return int
	 */
	public function getId() {
		$this->populateBasicData();
		return $this->id;
	}

	/**
	 * Get the unique name for this ad.
	 *
	 * This specifically does not include namespace or other prefixing.
	 *
	 * @return null|string
	 */
	public function getName() {
		$this->populateBasicData();
		return $this->name;
	}

	/**
	 * Should we allocate this ad to anonymous users.
	 *
	 * @return bool
	 */
	public function allocateToAnon() {
		$this->populateBasicData();
		return $this->allocateAnon;
	}

	/**
	 * Should we allocate this ad to logged in users.
	 *
	 * @return bool
	 */
	public function allocateToUser() {
		$this->populateBasicData();
		return $this->allocateUser;
	}

	/**
	 * Set current ad active status
	 *
	 * @param bool $status Should the ad be active?
	 * @return void
	 */
	public function setActive( $status ) {
		$this->populateBasicData();
		$this->setBasicDataDirty();

		$this->active = $status;

		return $this;
	}

	/**
	 * Set user state allocation properties for this ad
	 *
	 * @param bool $anon Should the ad be allocated to logged out users.
	 * @param bool $loggedIn Should the ad be allocated to logged in users.
	 *
	 * @return $this
	 */
	public function setAllocation( $anon, $loggedIn ) {
		$this->populateBasicData();

		if ( ( $this->allocateAnon !== $anon ) || ( $this->allocateUser !== $loggedIn ) ) {
			$this->setBasicDataDirty();
			$this->allocateAnon = $anon;
			$this->allocateUser = $loggedIn;
		}

		return $this;
	}


	public function getCaption() {
		$this->populateBasicData();
		return $this->adCaption;
	}

	public function setCaption( $value ) {
		$this->populateBasicData();

		if ( $this->adCaption !== $value ) {
			$this->setBasicDataDirty();
			$this->adCaption = $value;
		}

		return $this;
	}

	public function getMainLink() {
		$this->populateBasicData();
		return $this->adLink;
	}

	public function setMainLink( $value ) {
		$this->populateBasicData();

		if ( $this->adLink !== $value ) {
			$this->setBasicDataDirty();
			$this->adLink = $value;
		}

		return $this;
	}

	/**
	 * Should the ad be considered archived and hidden from default view
	 *
	 * @return bool
	 */
	public function isArchived() {
		$this->populateBasicData();
		return $this->archived;
	}

	/**
	 * Populates basic ad data by querying the pr_ads table
	 *
	 * @throws AdDataException If neither a name or ID can be used to query for data
	 * @throws AdExistenceException If no ad data was received
	 */
	protected function populateBasicData() {
		if ( $this->dirtyFlags['basic'] !== null ) {
			return;
		}

		$db = PRDatabase::getDb();

		// What are we using to select on?
		if ( $this->name !== null ) {
			$selector = array( 'ad_name' => $this->name );
		} elseif ( $this->id !== null ) {
			$selector = array( 'ad_id' => $this->id );
		} else {
			throw new AdDataException( 'Cannot retrieve ad data without name or ID.' );
		}

		// Query!
		$rowRes = $db->select(
			array( 'ads' => 'pr_ads' ),
			array(
				 'ad_id',
				 'ad_name',
				 'ad_title',
				 'ad_mainlink',
				 'ad_display_anon',
				 'ad_display_user',
				 'ad_active'
				 //'ad_archived',
			),
			$selector,
			__METHOD__
		);

		// Extract the dataz!
		$row = $db->fetchObject( $rowRes );
		if ( $row ) {
			$this->id = (int)$row->ad_id;
			$this->name = $row->ad_name;
			$this->allocateAnon = (bool)$row->ad_display_anon;
			$this->allocateUser = (bool)$row->ad_display_user;
			$this->adCaption = $row->ad_title;
			$this->adLink = $row->ad_mainlink;
			$this->active = (bool)$row->ad_active;
			//$this->archived = (bool)$row->ad_archived;
		} else {
			$keystr = array();
			foreach ( $selector as $key => $value ) {
				$keystr[] = "{$key} = {$value}";
			}
			$keystr = implode( " AND ", $keystr );
			throw new AdExistenceException( "No ad exists where {$keystr}. Could not load." );
		}

		// Set the dirty flag to not dirty because we just loaded clean data
		$this->setBasicDataDirty( false );
	}

	/**
	 * Sets the flag which will save basic metadata on next save()
	 */
	protected function setBasicDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['basic'], $dirty, true );
	}

	/**
	 * Helper function to initializeDbForNewAd()
	 *
	 * @param DatabaseBase $db
	 */
	protected function initializeDbBasicData( $db ) {
		$db->insert( 'pr_ads', array( 'ad_name' => $this->name ), __METHOD__ );
		$this->id = $db->insertId();
	}

	/**
	 * Helper function to saveAdInternal() for saving basic ad metadata
	 * @param DatabaseBase $db
	 */
	protected function saveBasicData( $db ) {
		if ( $this->dirtyFlags['basic'] ) {
			$db->update( 'pr_ads',
				array(
					 'ad_display_anon'    => (int)$this->allocateAnon,
					 'ad_display_user' => (int)$this->allocateUser,
					 'ad_title' => $this->adCaption,
					 'ad_mainlink' => $this->adLink,
					 'ad_active' => (int)$this->active
					 //'ad_archived'        => $this->archived,
					 //'ad_category'        => $this->category,
				),
				array(
					 'ad_id'              => $this->id
				),
				__METHOD__
			);
		}
	}
	//</editor-fold>

	//<editor-fold desc="Ad body content">
	public function getDbKey() {
		$name = $this->getName();
		return "Promoter-ad-{$name}";
	}

	public function getTitle() {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	/**
	 * Returns an array of Title objects that have been included as templates
	 * in this ad.
	 *
	 * @return Array of Title
	 */
	public function getIncludedTemplates() {
		return $this->getTitle()->getTemplateLinksFrom();
	}

	/**
	 * Get the raw body HTML for the ad.
	 *
	 * @return string HTML
	 */
	public function getBodyContent() {
		$this->populateBodyContent();
		return $this->bodyContent;
	}

	/**
	 * Set the raw body HTML for the ad.
	 *
	 * @param string $text HTML
	 *
	 * @return $this
	 */
	public function setBodyContent( $text ) {
		$this->populateBodyContent();

		if ( $this->bodyContent !== $text ) {
			$this->bodyContent = $text;
			$this->markBodyContentDirty();
		}

		return $this;
	}

	protected function populateBodyContent() {
		if ( $this->dirtyFlags['content'] !== null ) {
			return;
		}

		$bodyPage = $this->getTitle();
		$curRev = Revision::newFromTitle( $bodyPage );
		if ( !$curRev ) {
			throw new AdContentException( "No content for ad: {$this->name}" );
		}
		$this->bodyContent = ContentHandler::getContentText( $curRev->getContent() );

		$this->markBodyContentDirty( false );
	}

	protected function markBodyContentDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['content'], $dirty, true );
	}

	protected function saveBodyContent() {

		if ( $this->dirtyFlags['content'] ) {
			$wikiPage = new WikiPage( $this->getTitle() );

			$contentObj = ContentHandler::makeContent( $this->bodyContent, $wikiPage->getTitle() );
			$pageResult = $wikiPage->doEditContent( $contentObj, '', EDIT_FORCE_BOT );

		}
	}
	//</editor-fold>

	//<editor-fold desc="Ad actions">
	//<editor-fold desc="Saving">
	/**
	 * Saves any changes made to the ad object into the database
	 *
	 * @param null $user
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function save( $user = null ) {
		global $wgUser;

		$db = PRDatabase::getDb();

		$action = 'modified';
		if ( $user === null ) {
			$user = $wgUser;
		}

		try {
			$this->saveBodyContent(); // Do not move into saveAdInternal -- cannot be in a transaction

			// Open a transaction so that everything is consistent
			$db->begin( __METHOD__ );

			if ( !$this->exists() ) {
				$action = 'created';
				$this->initializeDbForNewAd( $db );
			}
			$this->saveAdInternal( $db );
			$this->logAdChange( $action, $user );

			$db->commit( __METHOD__ );

			// Clear the dirty flags
			foreach ( $this->dirtyFlags as $flag => &$value ) { $value = false; }

		} catch ( Exception $ex ) {
			$db->rollback( __METHOD__ );
			throw $ex;
		}

		return $this;
	}

	/**
	 * Called before saveAdInternal() when a new to the database ad is
	 * being saved. Intended to create all table rows required such that any
	 * additional operation can be an UPDATE statement.
	 *
	 * @param DatabaseBase $db
	 */
	protected function initializeDbForNewAd( $db ) {
		$this->initializeDbBasicData( $db );
	}

	/**
	 * Helper function to save(). This is wrapped in a database transaction and
	 * is intended to be easy to override -- though overriding function should
	 * call this at some point. :)
	 *
	 * Because it is wrapped in a database transaction; most MediaWiki calls
	 * like page saving cannot be performed here.
	 *
	 * Dirty flags are not globally reset until after this function is called.
	 *
	 * @param DatabaseBase $db
	 *
	 * @throws AdExistenceException
	 */
	protected function saveAdInternal( $db ) {
		$this->saveBasicData( $db );
	}
	//</editor-fold>

	/**
	 * Archive an ad.
	 *
	 * TODO: Remove data from translation, in place replace all templates
	 *
	 * @return $this
	 */
	public function archive() {
		if ( $this->dirtyFlags['basic'] === null ) {
			$this->populateBasicData();
		}
		$this->dirtyFlags['basic'] = true;

		$this->archived = true;

		return $this;
	}

	public function cloneAd( $destination, $user ) {
		if ( !$this->isValidAdName( $destination ) ) {
			throw new AdDataException( "Ad name must be in format /^[A-Za-z0-9_]+$/" );
		}

		$destAd = Ad::newFromName( $destination );
		if ( $destAd->exists() ) {
			throw new AdExistenceException( "Ad by that name already exists!" );
		}

		$destAd->setAllocation( $this->allocateToAnon(), $this->allocateToUser() );

		$destAd->setCaption( $this->getCaption() );
		$destAd->setMainLink( $this->getMainLink() );

		$destAd->setBodyContent( $this->getBodyContent() );

		// Save it!
		$destAd->save( $user );
		return $destAd;
	}

	public function remove( $user = null ) {
		global $wgUser;
		if ( $user === null ) {
			$user = $wgUser;
		}
		Ad::removeAd( $this->getName(), $user );
	}

	static function removeAd( $name, $user ) {
		$adObj = Ad::fromName( $name );
		$id = $adObj->getId();
		$dbr = PRDatabase::getDb();
		$res = $dbr->select( 'pr_adlinks', 'adl_id', array( 'ad_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			throw new MWException( 'Cannot remove an ad still bound to a campaign!' );
		} else {
			// Log the removal of the ad
			// FIXME: this log line will display changes with inverted sense
			$adObj->logAdChange( 'removed', $user );

			// Delete ad record from the Promoter pr_ads table
			$dbw = PRDatabase::getDb();
			$dbw->begin();
			$dbw->delete( 'pr_ads',
				array( 'ad_id' => $id ),
				__METHOD__
			);
			$dbw->commit();

			// Delete the MediaWiki page that contains the ad source
			$article = new Article(
				Title::newFromText( "promoter-ad-{$name}", NS_MEDIAWIKI )
			);
			$article->doDeleteArticle( 'Promoter automated removal' );
		}
	}
	//</editor-fold>

	/**
	 * Return settings for an ad
	 *
	 * @param $adName string name of ad
	 * @param $detailed boolean if true, get some more expensive info
	 *
	 * @throws MWException
	 * @return array an array of ad settings
	 */
	public function getAdSettings() {
		if ( !$this->exists() ) {
			throw new MWException( "Ad doesn't exist!" );
		}

		$details = array(
			'anon' => (int)$this->allocateToAnon(),
			'user' => (int)$this->allocateToUser(),
			'active' => (int)$this->active
		);

		return $details;
	}

	/**
	 * FIXME: a little thin, it's just enough to get the job done
	 *
	 * @param $name
	 * @param $ts
	 * @return array|null ad settings as an associative array, with these properties:
	 *    display_anon: 0/1 whether the ad is displayed to anonymous users
	 *    display_account: 0/1 same, for logged-in users
	 */
	static function getHistoricalAd( $name, $ts ) {
		$id = Ad::fromName( $name )->getId();

		$dbr = PRDatabase::getDb();

		$newestLog = $dbr->selectRow(
			"pr_ad_log",
			array(
				"log_id" => "MAX(adlog_id)",
			),
			array(
				"adlog_timestamp <= $ts",
				"adlog_ad_id = $id",
			),
			__METHOD__
		);

		if ( $newestLog->log_id === null ) {
			return null;
		}

		$row = $dbr->selectRow(
			"pr_ad_log",
			array(
				"display_anon" => "adlog_end_anon",
				"display_account" => "adlog_end_account",
			),
			array(
				"adlog_id = {$newestLog->log_id}",
			),
			__METHOD__
		);
		$ad['display_anon'] = (int) $row->display_anon;
		$ad['display_account'] = (int) $row->display_account;

		return $ad;
	}

	/**
	 * Create a new ad
	 *
	 * @param $name             string name of ad
	 * @param $body             string content of ad
	 * @param $caption            string caption/heading of ad
	 * @param $mainlink            string main link for the ad (link caption to page)
	 * @param $user             User causing the change
	 * @param bool|int $displayAnon integer flag for display to anonymous users
	 * @param bool|int $displayUser integer flag for display to logged in users
	 *
	 * @return bool true or false depending on whether ad was successfully added
	 */
	static function addAd(
		$name, $body, $caption, $mainlink, $user, $displayAnon = true, $displayUser = true
	) {
		//if ( $name == '' || !Ad::isValidAdName( $name ) || $body == '' ) {
		if ( $name == '' || !Ad::isValidAdName( $name ) ) {
			return 'promoter-null-string';
		}

		$ad = Ad::newFromName( $name );
		if ( $ad->exists() ) {
			return 'promoter-ad-exists';
		}

		$ad->setAllocation( $displayAnon, $displayUser );


		$ad->setCaption( $caption );
		$ad->setMainLink( $mainlink );

		$ad->setBodyContent( $body );

		$ad->save( $user );
		return true;
	}

	/**
	 * Log setting changes related to an ad
	 *
	 * @param $action        string: 'created', 'modified', or 'removed'
	 * @param $user          User causing the change
	 * @param $beginSettings array of ad settings before changes (optional)
	 */
	function logAdChange( $action, $user, $beginSettings = array() ) {
		$endSettings = array();
		if ( $action !== 'removed' ) {
			$endSettings = Ad::getAdSettings( $this->getName(), true );
		}

		$dbw = PRDatabase::getDb();

		$log = array(
			'adlog_timestamp'     => $dbw->timestamp(),
			'adlog_user_id'       => $user->getId(),
			'adlog_action'        => $action,
			'adlog_ad_id'   => $this->getId(),
			'adlog_ad_name' => $this->getName(),
			'adlog_content_change'=> (int)$this->dirtyFlags['content'],
		);

		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJSON::encode( $value );
			}

			$log[ 'adlog_end_' . $key ] = $value;
		}

		$dbw->insert( 'pr_ad_log', $log );
	}
	//</editor-fold>

	/**
	 * Validation function for ad names. Will return true iff the name fits
	 * the generic format of letters, numbers, and dashes.
	 *
	 * @param string $name The name to check
	 *
	 * @return bool True if valid
	 */
	static function isValidAdName( $name ) {
		global $wgExperimentalHtmlIds;

		$pattern = $wgExperimentalHtmlIds ? '/^[A-Za-zא-ת0-9_]+$/' : '/^[A-Za-z0-9_]+$/';

		return preg_match( $pattern, $name );
	}

	/**
	 * Check to see if an ad actually exists in the database
	 *
	 * @return bool
	 * @throws AdDataException If it's a silly query
	 */
	public function exists() {
		$db = PRDatabase::getDb();
		if ( $this->name !== null ) {
			$selector = array( 'ad_name' => $this->name );
		} elseif ( $this->id !== null ) {
			$selector = array( 'ad_id' => $this->id );
		} else {
			throw new AdDataException( 'Cannot determine ad existence without name or ID.' );
		}
		$row = $db->selectRow( 'pr_ads', 'ad_name', $selector );
		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the body of the ad, with all transformations applied.
	 */
	public function renderHtml() {
		$adCaption = $this->getCaption();
		$adBody = wfMessage( $this->getDbKey() )->parse();
		$adMainLink = $this->getMainLink();
		$adMainLink = empty( $adMainLink ) ?
			null : Skin::makeInternalOrExternalUrl( $this->getMainLink() );

		$adHtml = HTML::openElement(
			'div', array( 'class' => 'promotion', 'data-adname' => $this->getName() )
		);
		    $adHtml .= HTML::openElement( 'div', array( 'class' => 'header' ) );
		        //$adHtml .= HTML::element( 'span', array( 'class' => 'icon pull-right' ) );
		        if ( empty( $adMainLink ) ) {
					$adHtml .= HTML::element( 'span', array( 'class' => 'caption' ), $adCaption );
				} else {
					$adHtml .= HTML::element(
						'a',
						array( 'class' => 'caption', 'href' => $adMainLink ),
						$adCaption
					);
				}

		 	$adHtml .= HTML::closeElement( 'div' );
		 	$adHtml .= HTML::rawElement( 'div', array( 'class' => 'content' ), $adBody );
		 	if ( $adMainLink ) {
				$adHtml .= HTML::openElement( 'div', array( 'class' => 'mainlink' ) );
				$adHtml .= HTML::element( 'a', array( 'href' => $adMainLink ), 'לפרטים נוספים...' );
				$adHtml .= HTML::closeElement( 'div' );
			}
	    $adHtml .= HTML::closeElement( 'div' );

		 return $adHtml;
	}

	function linkToPreview() {
		return Linker::link(
			SpecialPage::getTitleFor( 'PromoterAds', "edit/{$this->getName()}" ),
			htmlspecialchars( $this->getName() ),
			array( 'class' => 'pr-ad-title' )
		);
	}

	// @TODO do a join with pr_campaign instead of getting campaign names one by one
	function getLinkedCampaignNames() {
		$campaignNames = array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'pr_adlinks', 'cmp_id',
			array(
				'ad_id' => $this->getId(),
			)
		);
		foreach ( $res as $row ) {
			$campaignNames[] = AdCampaign::getCampaignName( $row->cmp_id );
		}

		return $campaignNames;
	}
}

class AdDataException extends MWException {
}
class AdContentException extends AdDataException {
}
class AdExistenceException extends AdDataException {
}
