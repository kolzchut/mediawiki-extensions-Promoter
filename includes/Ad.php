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

namespace MediaWiki\Extension\Promoter;

use ContentHandler;
use Exception;
use FormatJSON;
use Html;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MWException;
use MWTimestamp;
use SpecialPage;
use Title;
use User;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\TimestampException;
use WikiPage;

/**
 * Promoter ad object. Ads are pieces of rendered wikimarkup
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
	 * @var array<string bool|null>
	 */
	protected array $dirtyFlags = [
		'content' => null,
		'messages' => null,
		'basic' => null
	];

	/** @var IConnectionProvider|null */
	protected ?IConnectionProvider $dbProvider = null;

	// !!! NOTE !!! It is not recommended to use directly. It is almost always more
	// correct to use the accessor/setter function.

	/** @var int|null Unique database identifier key */
	protected ?int $id = null;

	/** @var string|null Unique human friendly name of ad */
	protected ?string $name = null;

	/** @var bool True if the ad should be allocated to anonymous users. */
	protected bool $allocateAnon = false;

	/** @var bool True if the ad should be allocated to logged-in users. */
	protected bool $allocateUser = false;

	/**
	 * @var array<string bool> Tags associated with the ad
	 */
	protected array $tags = [
		'new' => false
	];

	/** @var MWTimestamp|null */
	protected ?MWTimestamp $startDate = null;

	/** @var MWTimestamp|null */
	protected ?MWTimestamp $endDate = null;

	/** @var bool True if archived and hidden from default view. */
	protected bool $archived = false;

	/** @var string Wikitext content of the ad */
	protected string $bodyContent = '';

	/** @var string Heading/caption of the ad */
	protected string $adCaption = '';

	/** @var string Main link of the ad */
	protected string $adLink = '';

	/** @var bool Ad active status */
	protected bool $active = false;

	// </editor-fold>

	// <editor-fold desc="Constructors">

	/**
	 * Create an ad object from a known ID. Must already be
	 * an object in the database.
	 * @param int $id Unique database ID of the ad
	 *
	 * @return Ad
	 * @see newFromName().
	 */
	public static function fromId( int $id ): Ad {
		$obj = new Ad();
		$obj->id = $id;
		return $obj;
	}

	/**
	 * Create an ad object from a known ad name. Must already be
	 * an object in the database.
	 * @param string $name
	 *
	 * @return Ad
	 * @throws AdDataException
	 * @see newFromName().
	 */
	public static function fromName( string $name ): Ad {
		if ( !self::isValidAdName( $name ) ) {
			throw new AdDataException( 'promoter-ad-name-error' );
		}

		$obj = new Ad();
		$obj->name = $name;
		return $obj;
	}

	/**
	 * Create a brand-new ad object.
	 *
	 * @param string $name
	 *
	 * @return Ad
	 * @throws AdDataException
	 */
	public static function newFromName( string $name ): Ad {
		if ( !self::isValidAdName( $name ) ) {
			throw new AdDataException( 'promoter-ad-name-error' );
		}

		$obj = new Ad();
		$obj->name = $name;

		foreach ( $obj->dirtyFlags as $flag => &$value ) {
			$value = true;
		}

		return $obj;
	}

	// </editor-fold>

	// <editor-fold desc="Database access">

	/**
	 * Get the database connection provider, initializing if needed
	 *
	 * @return IConnectionProvider
	 */
	protected function getDbProvider(): IConnectionProvider {
		if ( $this->dbProvider === null ) {
			$this->dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		}
		return $this->dbProvider;
	}

	// </editor-fold>

	// <editor-fold desc="Basic metadata getters/setters">

	/**
	 * Get the unique ID for this ad.
	 *
	 * @return int|null
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function getId(): ?int {
		$this->populateBasicData();
		return $this->id;
	}

	/**
	 * Get the unique name for this ad.
	 *
	 * This specifically does not include namespace or other prefixing.
	 *
	 * @return null|string
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function getName(): ?string {
		$this->populateBasicData();
		return $this->name;
	}

	/**
	 * Should we allocate this ad to anonymous users.
	 *
	 * @return bool
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function allocateToAnon(): bool {
		$this->populateBasicData();
		return $this->allocateAnon;
	}

	/**
	 * Should we allocate this ad to logged-in users.
	 *
	 * @return bool
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function allocateToUser(): bool {
		$this->populateBasicData();
		return $this->allocateUser;
	}

	/**
	 * Should the ad be marked as 'new'.
	 *
	 * @return bool
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function isNew(): bool {
		$this->populateBasicData();
		return $this->tags['new'];
	}

	/**
	 * Set current ad active status
	 *
	 * @param bool $status Should the ad be active?
	 * @return $this
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function setActiveStatus( bool $status ): static {
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
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function setAllocation( bool $anon, bool $loggedIn ): static {
		$this->populateBasicData();

		if ( ( $this->allocateAnon !== $anon ) || ( $this->allocateUser !== $loggedIn ) ) {
			$this->setBasicDataDirty();
			$this->allocateAnon = $anon;
			$this->allocateUser = $loggedIn;
		}

		return $this;
	}

	/**
	 * @param array $tags
	 *
	 * @return $this
	 * @throws AdDataException|AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function setTags( array $tags ): static {
		$this->populateBasicData();
		$this->setBasicDataDirty();

		array_walk( $this->tags, static function ( &$item, $key ) use ( &$tags ) {
			$item = in_array( $key, $tags );
		} );

		return $this;
	}

	/**
	 * @param string|null $date
	 * @throws TimestampException
	 */
	public function setStartDate( ?string $date ): void {
		$this->startDate = empty( $date ) ? null : new MWTimestamp( $date );
	}

	/**
	 * @return MWTimestamp|null
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function getStartDate(): ?MWTimestamp {
		$this->populateBasicData();

		return $this->startDate;
	}

	/**
	 * @param string|null $date
	 * @throws TimestampException
	 */
	public function setEndDate( ?string $date ): void {
		$this->endDate = empty( $date ) ? null : new MWTimestamp( $date );
	}

	/**
	 * @return MWTimestamp|null
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function getEndDate(): ?MWTimestamp {
		$this->populateBasicData();

		return $this->endDate;
	}

	/**
	 * @return bool
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function isNotExpired(): bool {
		$this->populateBasicData();

		if ( $this->endDate === null ) {
			return true;
		}

		$now       = wfTimestamp( TS_UNIX );
		$endDate   = wfTimestamp( TS_UNIX, $this->endDate );
		$startDate = wfTimestamp( TS_UNIX, $this->startDate );

		return ( $now < $endDate && $startDate < $now );
	}

	/**
	 * @return string
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function getCaption(): string {
		$this->populateBasicData();
		return $this->adCaption;
	}

	/**
	 * @param string $value
	 *
	 * @return $this
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function setCaption( string $value ): static {
		$this->populateBasicData();

		if ( $this->adCaption !== $value ) {
			$this->setBasicDataDirty();
			$this->adCaption = $value;
		}

		return $this;
	}

	/**
	 * @return string
	 * @throws AdDataException|AdExistenceException|MWException
	 */
	public function getMainLink(): string {
		$this->populateBasicData();
		return $this->adLink;
	}

	/**
	 * @param string $value
	 *
	 * @return $this
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function setMainLink( string $value ): static {
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
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 */
	public function isArchived(): bool {
		$this->populateBasicData();
		return $this->archived;
	}

	/**
	 * Populates basic ad data by querying the pr_ads table
	 *
	 * @throws AdDataException If neither a name or ID can be used to query for data
	 * @throws AdExistenceException|MWException If no ad data was received
	 * @throws TimestampException
	 */
	protected function populateBasicData(): void {
		if ( $this->dirtyFlags['basic'] !== null ) {
			return;
		}

		$db = $this->getDbProvider()->getReplicaDatabase();

		// What are we using to select on?
		if ( $this->name !== null ) {
			$selector = [ 'ad_name' => $this->name ];
		} elseif ( $this->id !== null ) {
			$selector = [ 'ad_id' => $this->id ];
		} else {
			throw new MWException( 'Cannot retrieve ad data without name or ID.' );
		}

		// Query!
		$rowRes = $db->select(
			[ 'ads' => 'pr_ads' ],
			[
				 'ad_id',
				 'ad_name',
				 'ad_title',
				 'ad_mainlink',
				 'ad_display_anon',
				 'ad_display_user',
				 'ad_tag_new',
				 'ad_date_start',
				 'ad_date_end',
				 'ad_active'
				 // 'ad_archived',
			],
			$selector,
			__METHOD__
		);

		// Extract the dataz!
		$row = $rowRes->fetchObject();
		if ( $row ) {
			$this->id = (int)$row->ad_id;
			$this->name = $row->ad_name;
			$this->allocateAnon = (bool)$row->ad_display_anon;
			$this->allocateUser = (bool)$row->ad_display_user;
			$this->tags['new'] = (bool)$row->ad_tag_new;
			$this->adCaption = $row->ad_title;
			// ad_mainlink is nullable in the schema; coerce to empty string
			// to satisfy the typed `string` property.
			$this->adLink = $row->ad_mainlink ?? '';
			$this->setStartDate( $row->ad_date_start );
			$this->setEndDate( $row->ad_date_end );
			$this->active = (bool)$row->ad_active;
			// $this->archived = (bool)$row->ad_archived;
		} else {
			$keystr = [];
			foreach ( $selector as $key => $value ) {
				$keystr[] = "$key = $value";
			}
			$keystr = implode( " AND ", $keystr );
			throw new AdExistenceException( [ 'promoter-ad-doesnt-exists', $keystr ] );
		}

		// Set the dirty flag to not dirty because we just loaded clean data
		$this->setBasicDataDirty( false );
	}

	/**
	 * Sets the flag which will save basic metadata on next save()
	 *
	 * @param bool $dirty
	 *
	 * @return bool
	 */
	protected function setBasicDataDirty( bool $dirty = true ): bool {
		return (bool)wfSetVar( $this->dirtyFlags['basic'], $dirty, true );
	}

	/**
	 * Helper function to initializeDbForNewAd()
	 *
	 * @param IReadableDatabase $db
	 */
	protected function initializeDbBasicData( IReadableDatabase $db ): void {
		$db->insert( 'pr_ads', [ 'ad_name' => $this->name ], __METHOD__ );
		$this->id = $db->insertId();
	}

	/**
	 * Helper function to saveAdInternal() for saving basic ad metadata
	 * @param IReadableDatabase $db
	 */
	protected function saveBasicData( IReadableDatabase $db ): void {
		if ( $this->dirtyFlags['basic'] ) {
			$db->update( 'pr_ads',
				[
					 'ad_display_anon'    => (int)$this->allocateAnon,
					 'ad_display_user' => (int)$this->allocateUser,
					 'ad_tag_new' => (int)$this->tags['new'],
					 'ad_title' => $this->adCaption,
					 'ad_mainlink' => $this->adLink,
					 'ad_date_start' => $db->timestampOrNull( $this->startDate ),
					 'ad_date_end' => $db->timestampOrNull( $this->endDate ),
					 'ad_active' => $this->active
					 // 'ad_archived'        => $this->archived,
					 // 'ad_category'        => $this->category,
				],
				[
					 'ad_id'              => $this->id
				],
				__METHOD__
			);
		}
	}

	// </editor-fold>

	// <editor-fold desc="Ad body content">

	/**
	 * @return string
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 */
	public function getDbKey(): string {
		$name = $this->getName();
		return "Promoter-ad-$name";
	}

	/**
	 * @return Title|null
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 */
	public function getTitle(): ?Title {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	/**
	 * Returns an array of Title objects that have been included as templates
	 * in this ad.
	 *
	 * @return Title[]
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 */
	public function getIncludedTemplates(): array {
		return $this->getTitle()->getTemplateLinksFrom();
	}

	/**
	 * Get the raw body HTML for the ad.
	 *
	 * @return string HTML
	 * @throws MWException
	 */
	public function getBodyContent(): string {
		$this->populateBodyContent();
		return $this->bodyContent;
	}

	/**
	 * Set the raw body HTML for the ad.
	 *
	 * @param string $text HTML
	 *
	 * @return Ad
	 * @throws MWException
	 */
	public function setBodyContent( string $text ): static {
		$this->populateBodyContent();

		if ( $this->bodyContent !== $text ) {
			$this->bodyContent = $text;
			$this->markBodyContentDirty();
		}

		return $this;
	}

	/**
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 */
	protected function populateBodyContent(): void {
		if ( $this->dirtyFlags['content'] !== null ) {
			return;
		}

		$bodyPage = $this->getTitle();
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$curRev = $revisionStore->getRevisionByTitle( $bodyPage );
		if ( !$curRev ) {
			throw new MWException( "No content for ad: $this->name" );
		}
		$content = $curRev->getContent( SlotRecord::MAIN );
		$this->bodyContent = ( $content instanceof TextContent ) ? $content->getText() : '';

		$this->markBodyContentDirty( false );
	}

	/**
	 * @param bool $dirty
	 *
	 * @return bool
	 */
	protected function markBodyContentDirty( bool $dirty = true ): bool {
		return (bool)wfSetVar( $this->dirtyFlags['content'], $dirty, true );
	}

	/**
	 * @param User $user The user performing the save operation
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws \MWContentSerializationException
	 * @throws \MWUnknownContentModelException
	 */
	protected function saveBodyContent( User $user ): void {
		if ( $this->dirtyFlags['content'] ) {
			$wikiPage = new WikiPage( $this->getTitle() );
			$contentObj = ContentHandler::makeContent( $this->bodyContent, $wikiPage->getTitle() );

			$mediaWikiServices = MediaWikiServices::getInstance();
			$pageUpdater = $mediaWikiServices->getPageUpdaterFactory()->newPageUpdater( $this->getTitle(), $user );
			$pageUpdater->setContent( SlotRecord::MAIN, $contentObj );
			$pageUpdater->saveRevision(
				CommentStoreComment::newUnsavedComment( '' ),
				EDIT_FORCE_BOT
			);
		}
	}

	// </editor-fold>

	// <editor-fold desc="Saving">

	/**
	 * Saves any changes made to the ad object into the database
	 *
	 * @param null|User $user
	 *
	 * @return $this
	 * @throws Exception
	 * @throws \PermissionsError If user lacks promoter-admin permission
	 */
	public function save( ?User $user = null ): static {
		$user = $user ?? RequestContext::getMain()->getUser();

		// Verify user has permission to modify ads (defense-in-depth)
		if ( !$user->isAllowed( 'promoter-admin' ) ) {
			throw new \PermissionsError( 'promoter-admin' );
		}

		// Always use primary database for write operations
		$db = $this->getDbProvider()->getPrimaryDatabase();

		$action = 'modified';

		$this->saveBodyContent( $user );

		if ( !$this->exists() ) {
			$action = 'created';
			$this->initializeDbForNewAd( $db );
		}
		$this->saveAdInternal( $db );
		$this->logAdChange( $action, $user );

		// Clear the dirty flags
		foreach ( $this->dirtyFlags as $flag => &$value ) {
			$value = false;
		}

		return $this;
	}

	/**
	 * Called before saveAdInternal() when a new to the database ad is
	 * being saved. Intended to create all table rows required such that any
	 * additional operation can be an UPDATE statement.
	 *
	 * @param IReadableDatabase $db
	 */
	protected function initializeDbForNewAd( IReadableDatabase $db ): void {
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
	 * @param IReadableDatabase $db
	 */
	protected function saveAdInternal( IReadableDatabase $db ): void {
		$this->saveBasicData( $db );
	}

	// </editor-fold>

	/**
	 * Archive an ad.
	 *
	 * TODO: Remove data from translation, in place replace all templates
	 *
	 * @return $this
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws TimestampException
	 */
	public function archive(): static {
		if ( $this->dirtyFlags['basic'] === null ) {
			$this->populateBasicData();
		}
		$this->dirtyFlags['basic'] = true;

		$this->archived = true;

		return $this;
	}

	/**
	 * @param string $destination
	 * @param User $user
	 *
	 * @return Ad
	 * @throws AdDataException|AdExistenceException|MWException
	 * @throws \PermissionsError If user lacks promoter-admin permission
	 */
	public function cloneAd( string $destination, User $user ): Ad {
		// Verify user has permission to create ads (defense-in-depth)
		if ( !$user->isAllowed( 'promoter-admin' ) ) {
			throw new \PermissionsError( 'promoter-admin' );
		}

		if ( !$this->isValidAdName( $destination ) ) {
			throw new AdDataException( 'promoter-ad-name-error' );
		}

		$destAd = self::newFromName( $destination );
		if ( $destAd->exists() ) {
			throw new AdExistenceException( [ 'promoter-ad-already-exists', $destination ] );
		}

		$destAd->setAllocation( $this->allocateToAnon(), $this->allocateToUser() );

		$destAd->setCaption( $this->getCaption() );
		$destAd->setMainLink( $this->getMainLink() );

		$destAd->setBodyContent( $this->getBodyContent() );

		// Save it!
		$destAd->save( $user );
		return $destAd;
	}

	/**
	 * @param User|null $user
	 *
	 * @throws AdDataException
	 * @throws MWException
	 * @throws \FatalError
	 */
	public function remove( ?User $user = null ): void {
		$user = $user ?? RequestContext::getMain()->getUser();
		self::removeAd( $this->getId(), $user );
	}

	/**
	 * Remove an ad either by ID or name.
	 *
	 * @param int|string $identifier
	 * @param User $user
	 *
	 * @throws AdDataException|MWException|\FatalError
	 * @throws \PermissionsError If user lacks promoter-admin permission
	 */
	public static function removeAd( int|string $identifier, User $user ): void {
		// Verify user has permission to delete ads (defense-in-depth)
		if ( !$user->isAllowed( 'promoter-admin' ) ) {
			throw new \PermissionsError( 'promoter-admin' );
		}

		$adObj = is_int( $identifier ) ? self::fromId( $identifier ) : self::fromName( $identifier );
		$id = $adObj->getId();
		$services = MediaWikiServices::getInstance();
		$dbProvider = $services->getConnectionProvider();

		$dbr = $dbProvider->getReplicaDatabase();
		$res = $dbr->select( 'pr_adlinks', 'adl_id', [ 'ad_id' => $id ], __METHOD__ );

		if ( $res->numRows() > 0 ) {
			throw new MWException( 'Cannot remove an ad still bound to a campaign!' );
		} else {
			// Log the removal of the ad
			// FIXME: this log line will display changes with inverted sense
			$adObj->logAdChange( 'removed', $user );

			// Delete ad record from the Promoter pr_ads table
			$dbw = $dbProvider->getPrimaryDatabase();
			$dbw->delete( 'pr_ads',
				[ 'ad_id' => $id ],
				__METHOD__
			);

			// Delete the MediaWiki page that contains the ad source
			$title = Title::newFromText( "promoter-ad-{$adObj->getName()}", NS_MEDIAWIKI );
			$wikiPageFactory = $services->getWikiPageFactory();
			$deletePageFactory = $services->getDeletePageFactory();

			$page = $wikiPageFactory->newFromTitle( $title );
			$deletePage = $deletePageFactory->newDeletePage( $page, $user );
			$deletePage->deleteUnsafe( 'Promoter automated removal' );
		}
	}

	// </editor-fold>

	/**
	 * Return settings for an ad
	 *
	 * @return array an array of ad settings
	 * @throws MWException
	 */
	public function getAdSettings(): array {
		if ( !$this->exists() ) {
			throw new MWException( "Ad doesn't exist!" );
		}

		$details = [
			'anon' => (int)$this->allocateToAnon(),
			'user' => (int)$this->allocateToUser(),
			'new'  => (int)$this->isNew(),
			'active' => (int)$this->active
		];

		return $details;
	}

	/**
	 * Create a new ad
	 *
	 * @param string $name name of ad
	 * @param string $body content of ad
	 * @param string $caption caption/heading of ad
	 * @param string $mainlink main link for the ad (link caption to page)
	 * @param User $user causing the change
	 * @param bool|int $displayAnon integer flag for display to anonymous users
	 * @param bool|int $displayUser integer flag for display to logged in users
	 * @param bool|int $isActive
	 *
	 * @return bool|string true or error message, depending on whether ad was successfully added
	 * @throws AdDataException
	 * @throws AdExistenceException
	 * @throws MWException
	 * @throws \PermissionsError If user lacks promoter-admin permission
	 */
	public static function addAd(
		string $name, string $body, string $caption, string $mainlink, User $user,
		bool|int $displayAnon = true, bool|int $displayUser = true, bool|int $isActive = false
	): bool|string {
		// Verify user has permission to create ads (defense-in-depth)
		if ( !$user->isAllowed( 'promoter-admin' ) ) {
			throw new \PermissionsError( 'promoter-admin' );
		}

		if ( !self::isValidAdName( $name ) ) {
			return 'promoter-null-string';
		}

		$ad = self::newFromName( $name );
		if ( $ad->exists() ) {
			return 'promoter-ad-exists';
		}

		$ad->setAllocation( $displayAnon, $displayUser );

		$ad->setCaption( $caption );
		$ad->setMainLink( $mainlink );
		$ad->setActiveStatus( $isActive );
		$ad->setBodyContent( $body );

		$ad->save( $user );
		return true;
	}

	/**
	 * Log setting changes related to an ad
	 *
	 * @param string $action 'created', 'modified', or 'removed'
	 * @param User $user causing the change
	 * @param array $beginSettings array of ad settings before changes (optional)
	 * @throws MWException
	 */
	private function logAdChange( string $action, User $user, array $beginSettings = [] ): void {
		$endSettings = [];
		if ( $action !== 'removed' ) {
			$endSettings = $this->getAdSettings();
		}

		$dbw = $this->getDbProvider()->getPrimaryDatabase();

		$log = [
			'adlog_timestamp'     => $dbw->timestamp(),
			'adlog_user_id'       => $user->getId(),
			'adlog_action'        => $action,
			'adlog_ad_id'   => $this->getId(),
			'adlog_ad_name' => $this->getName(),
			'adlog_content_change' => (int)$this->dirtyFlags['content'],
		];

		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJSON::encode( $value );
			}

			$log[ 'adlog_end_' . $key ] = $value;
		}

		$dbw->insert( 'pr_ad_log', $log );
	}

	// </editor-fold>

	/**
	 * Validation function for ad names. Will return true iff the name fits
	 * the generic format of letters, numbers, and dashes.
	 *
	 * @param string $name The name to check
	 *
	 * @return bool True if valid
	 */
	public static function isValidAdName( string $name ): bool {
		if ( empty( $name ) ) {
			return false;
		}

		$pattern = '/^[A-Za-zא-ת0-9_]+$/';

		return preg_match( $pattern, $name );
	}

	/**
	 * Check to see if an ad actually exists in the database
	 *
	 * @return bool
	 * @throws MWException If it's a silly query
	 */
	public function exists(): bool {
		$db = $this->getDbProvider()->getReplicaDatabase();
		if ( $this->name !== null ) {
			$selector = [ 'ad_name' => $this->name ];
		} elseif ( $this->id !== null ) {
			$selector = [ 'ad_id' => $this->id ];
		} else {
			throw new MWException( 'Cannot determine ad existence without name or ID.' );
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
	 *
	 * @return string
	 */
	public function renderHtml(): string {
		$adCaption = $this->getCaption();
		$adBody = wfMessage( $this->getDbKey() )->parse();
		$adMainLink = $this->getMainLink();
		$adMainLink = empty( $adMainLink ) ?
			null : \Skin::makeInternalOrExternalUrl( $this->getMainLink() );

		$adHtml = Html::openElement(
			'div', [ 'class' => 'promotion', 'data-adname' => $this->getName() ]
		);
			$adHtml .= Html::openElement( 'div', [ 'class' => 'header' ] );
				// $adHtml .= HTML::element( 'span', [ 'class' => 'icon pull-right' ] );
		if ( empty( $adMainLink ) ) {
			$adHtml .= Html::element( 'span', [ 'class' => 'caption' ], $adCaption );
		} else {
			$adHtml .= Html::element(
				'a',
				[ 'class' => 'caption', 'href' => $adMainLink ],
				$adCaption
			);
		}

			$adHtml .= Html::closeElement( 'div' );
			$adHtml .= Html::rawElement( 'div', [ 'class' => 'content' ], $adBody );
		if ( $adMainLink ) {
			$adHtml .= Html::openElement( 'div', [ 'class' => 'mainlink' ] );
			$adHtml .= Html::element( 'a', [ 'href' => $adMainLink ], 'לפרטים נוספים...' );
			$adHtml .= Html::closeElement( 'div' );
		}
		$adHtml .= Html::closeElement( 'div' );

		 return $adHtml;
	}

	/**
	 * @return string
	 */
	public function linkToEdit(): string {
		return MediaWikiServices::getInstance()->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'PromoterAds', "edit/{$this->getName()}" ),
			$this->getName(),
			[ 'class' => 'pr-ad-title' ]
		);
	}

	/**
	 * @todo do a join with pr_campaign instead of getting campaign names one by one
	 *
	 * @return array
	 */
	public function getLinkedCampaignNames(): array {
		$campaignNames = [];
		$dbr = $this->getDbProvider()->getReplicaDatabase();
		$res = $dbr->select( 'pr_adlinks', 'cmp_id',
			[ 'ad_id' => $this->getId() ]
		);
		foreach ( $res as $row ) {
			$campaignNames[] = AdCampaign::getCampaignName( $row->cmp_id );
		}

		return $campaignNames;
	}
}
