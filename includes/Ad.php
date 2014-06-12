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
 * @see AdRenderer
 * @see AdMixin
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
		'mixins' => null,
		'prioritylang' => null,
	);

	//<editor-fold desc="Properties">
	// !!! NOTE !!! It is not recommended to use directly. It is almost always more
	//              correct to use the accessor/setter function.

	/** @var int Unique database identifier key. */
	protected $id = null;

	/** @var string Unique human friendly name of ad. */
	protected $name = null;

	/** @var bool True if the ad should be allocated to anonymous users. */
	protected $allocateAnon = false;

	/** @var bool True if the ad should be allocated to logged in users. */
	protected $allocateLoggedIn = false;

	/** @var string Category that the ad belongs to. Will be special value expanded. */
	protected $category = '{{{campaign}}}';

	/** @var bool True if archived and hidden from default view. */
	protected $archived = false;

	/** @var string[] Names of enabled mixins  */
	protected $mixins = array();

	/** @var string[] Language codes considered a priority for translation.  */
	protected $priorityLanguages = array();

	/** @var string Wikitext content of the ad */
	protected $bodyContent = '';

	protected $runTranslateJob = false;
	//</editor-fold>

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
	public function allocateToLoggedIn() {
		$this->populateBasicData();
		return $this->allocateLoggedIn;
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

		if ( ( $this->allocateAnon !== $anon ) || ( $this->allocateLoggedIn !== $loggedIn ) ) {
			$this->setBasicDataDirty();
			$this->allocateAnon = $anon;
			$this->allocateLoggedIn = $loggedIn;
		}

		return $this;
	}

	/**
	 * Get the ad category.
	 *
	 * The category is the name of the cookie stored on the users computer. In this way
	 * ads in the same category may share settings.
	 *
	 * @return string
	 */
	public function getCategory() {
		$this->populateBasicData();
		return $this->category;
	}

	/**
	 * Set the ad category.
	 *
	 * @see Ad->getCategory()
	 *
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setCategory( $value ) {
		$this->populateBasicData();

		if ( $this->category !== $value ) {
			$this->setBasicDataDirty();
			$this->category = $value;
		}

		return $this;
	}

	/**
	 * Obtain an array of all categories currently seen attached to ads
	 * @return string[]
	 */
	public static function getAllUsedCategories() {
		$db = PRDatabase::getDb();
		$res = $db->select(
			'pr_ads',
			'ad_category',
			'',
			__METHOD__,
			array( 'DISTINCT', 'ORDER BY ad_category ASC' )
		);

		$categories = array();
		foreach ( $res as $row ) {
			$categories[$row->ad_category] = $row->ad_category;
		}
		return $categories;
	}

	/**
	 * Remove invalid characters from a category string that has been magic
	 * word expanded.
	 *
	 * @param $cat Category string to sanitize
	 *
	 * @return string
	 */
	public static function sanitizeRenderedCategory( $cat ) {
		return preg_replace( '/[^a-zA-Z0-9_]/', '', $cat );
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
				 'ad_display_anon',
				 'ad_display_user',
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
			$this->allocateLoggedIn = (bool)$row->ad_display_user;
			//$this->archived = (bool)$row->ad_archived;
			//$this->category = $row->ad_category;
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
					 'ad_display_user' => (int)$this->allocateLoggedIn,
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

	//<editor-fold desc="Mixin management">
	/**
	 * @return array Keys are names of enabled mixins; valeus are mixin params.
	 * @see $wgNoticeMixins
	 */
	public function getMixins() {
		$this->populateMixinData();
		return $this->mixins;
	}

	/**
	 * Set the ad mixins to enable.
	 *
	 * @param array $mixins Names of mixins to enable on this ad. Valid values
	 * come from @see $wgNoticeMixins
	 *
	 * @throws MWException
	 * @return $this
	 */
	function setMixins( $mixins ) {
		global $wgNoticeMixins;

		$this->populateMixinData();

		$mixins = array_unique( $mixins );
		sort( $mixins );

		if ( $this->mixins != $mixins ) {
			$this->markMixinDataDirty();
		}

		$this->mixins = array();
		foreach ( $mixins as $mixin ) {
			if ( !array_key_exists( $mixin, $wgNoticeMixins ) ) {
				throw new MWException( "Mixin does not exist: {$mixin}" );
			}
			$this->mixins[$mixin] = $wgNoticeMixins[$mixin];
		}

		return $this;
	}

	/**
	 * Populates mixin data from the pr_ad_mixins table.
	 *
	 * @throws MWException
	 */
	protected function populateMixinData() {
		global $wgCampaignMixins;

		if ( $this->dirtyFlags['mixins'] !== null ) {
			return;
		}

		$dbr = PRDatabase::getDb();

		$result = $dbr->select( 'pr_ad_mixins', 'mixin_name',
			array(
				 "ad_id" => $this->getId(),
			),
			__METHOD__
		);

		$this->mixins = array();
		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->mixin_name, $wgCampaignMixins ) ) {
				// We only want to warn here otherwise we'd never be able to
				// edit the ad to fix the issue! The editor should warn
				// when a deprecated mixin is being used; but also when we
				// do deprecate something we should make sure nothing is using
				// it!
				wfLogWarning( "Mixin does not exist: {$row->mixin_name}, included from ad {$this->name}" );
			}
			$this->mixins[$row->mixin_name] = $wgCampaignMixins[$row->mixin_name];
		}

		$this->markMixinDataDirty( false );
	}

	/**
	 * Sets the flag which will force saving of mixin data upon next save()
	 */
	protected function markMixinDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['mixins'], $dirty, true );
	}

	/**
	 * @param DatabaseBase $db
	 */
	protected function saveMixinData( $db ) {
		if ( $this->dirtyFlags['mixins'] ) {
			$db->delete( 'pr_ad_mixins',
				array( 'ad_id' => $this->getId() ),
				__METHOD__
			);

			foreach ( $this->mixins as $name => $params ) {
				$name = trim( $name );
				if ( !$name ) {
					continue;
				}
				$db->insert( 'pr_ad_mixins',
					array(
						 'ad_id' => $this->getId(),
						 'page_id' => 0,	// TODO: What were we going to use this for again?
						 'mixin_name' => $name,
					),
					__METHOD__
				);
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc="Priority languages">
	/**
	 * Returns language codes that are considered a priority for translations.
	 *
	 * If a language is in this list it means that the translation UI will promote
	 * translating them, and discourage translating other languages.
	 *
	 * @return string[]
	 */
	public function getPriorityLanguages() {
		$this->populatePriorityLanguageData();
		return $this->priorityLanguages;
	}

	/**
	 * Set language codes that should be considered a priority for translation.
	 *
	 * If a language is in this list it means that the translation UI will promote
	 * translating them, and discourage translating other languages.
	 *
	 * @param string[] $languageCodes
	 *
	 * @return $this
	 */
	public function setPriorityLanguages( $languageCodes ) {
		$this->populatePriorityLanguageData();

		$languageCodes = array_unique( (array)$languageCodes );
		sort( $languageCodes );

		if ( $this->priorityLanguages != $languageCodes ) {
			$this->priorityLanguages = $languageCodes;
			$this->markPriorityLanguageDataDirty();
		}

		return $this;
	}

	protected function populatePriorityLanguageData() {
		global $wgNoticeUseTranslateExtension;

		if ( $this->dirtyFlags['prioritylang'] !== null ) {
			return;
		}

		if ( $wgNoticeUseTranslateExtension ) {
			$langs = TranslateMetadata::get(
				AdMessageGroup::getTranslateGroupName( $this->getName() ),
				'prioritylangs'
			);
			if ( !$langs ) {
				// If priority langs is not set; TranslateMetadata::get will return false
				$langs = '';
			}
			$this->priorityLanguages = explode( ',', $langs );
		}
		$this->markPriorityLanguageDataDirty( false );
	}

	protected function markPriorityLanguageDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['prioritylang'], $dirty, true );
	}

	protected function savePriorityLanguageData() {
		global $wgNoticeUseTranslateExtension;

		if ( $wgNoticeUseTranslateExtension && $this->dirtyFlags['prioritylang'] ) {
			TranslateMetadata::set(
				AdMessageGroup::getTranslateGroupName( $this->getName() ),
				'prioritylangs',
				implode( ',', $this->priorityLanguages )
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
		global $wgNoticeUseTranslateExtension;

		if ( $this->dirtyFlags['content'] ) {
			$wikiPage = new WikiPage( $this->getTitle() );

			$contentObj = ContentHandler::makeContent( $this->bodyContent, $wikiPage->getTitle() );
			$pageResult = $wikiPage->doEditContent( $contentObj, '', EDIT_FORCE_BOT );

		}
	}
	//</editor-fold>

	//<editor-fold desc="Ad message fields">
	function getMessageField( $field_name ) {
		return new AdMessage( $this->getName(), $field_name );
	}

	/**
	 * Returns all the message fields in an ad
	 * @see Ad::extractMessageFields()
	 *
	 * @param bool|string $bodyContent If a string will regenerate cache object from the string
	 *
	 * @return array|mixed
	 */
	function getMessageFieldsFromCache( $bodyContent = false ) {
		global $wgMemc;

		$key = wfMemcKey( 'promoter', 'adfields', $this->getName() );
		$data = false;
		if ( $bodyContent === false ) {
			$data = $wgMemc->get( $key );
		}

		if ( $data !== false ) {
			$data = json_decode( $data, true );
		} else {
			$data = $this->extractMessageFields( $bodyContent );
			$wgMemc->set( $key, json_encode( $data ) );
		}

		return $data;
	}

	/**
	 * Extract the raw fields and field names from the ad body source.
	 * @param string $body The unparsed body source of the ad
	 * @return array
	 */
	function extractMessageFields( $body = null ) {
		global $wgParser;

		if ( $body === null ) {
			$body = $this->getBodyContent();
		}

		$expanded = $wgParser->parse(
			$body, $this->getTitle(), ParserOptions::newFromContext( RequestContext::getMain() )
		)->getText();

		// Also search the preload js for fields.
		$renderer = new AdRenderer( RequestContext::getMain(), $this );
		//$expanded .= $renderer->getPreloadJsRaw();

		// Extract message fields from the ad body
		$fields = array();
		$allowedChars = Title::legalChars();
		// We're using a janky custom syntax to pass arguments to a field message:
		// "{{{fieldname:arg1|arg2}}}"
		$allowedChars = str_replace( ':', '', $allowedChars );
		preg_match_all( "/{{{([$allowedChars]+)(:[^}]*)?}}}/u", $expanded, $fields );

		// Remove duplicate keys and count occurrences
		$unique_fields = array_unique( array_flip( $fields[1] ) );
		$fields = array_intersect_key( array_count_values( $fields[1] ), $unique_fields );

		$fields = array_diff_key( $fields, array_flip( $renderer->getMagicWords() ) );

		return $fields;
	}

	/**
	 * Returns a list of messages that are either published or in the PRAd translation
	 *
	 * @param bool $inTranslation If true and using group translation this will return
	 * all the messages that are in the translation system
	 *
	 * @return array A list of languages with existing field translations
	 */
	function getAvailableLanguages( $inTranslation = false ) {
		global $wgLanguageCode;
		$availableLangs = array();

		// Bit of an ugly hack to get just the ad prefix
		$prefix = $this->getMessageField( '' )->getDbKey( null, $inTranslation ? NS_PR_AD : NS_MEDIAWIKI );

		$db = PRDatabase::getDb();
		$result = $db->select( 'page',
			'page_title',
			array(
				 'page_namespace' => $inTranslation ? NS_PR_AD : NS_MEDIAWIKI,
				 'page_title' . $db->buildLike( $prefix, $db->anyString() ),
			),
			__METHOD__
		);
		while ( $row = $result->fetchRow() ) {
			if ( preg_match( "/\Q{$prefix}\E([^\/]+)(?:\/([a-z_]+))?/", $row['page_title'], $matches ) ) {
				$field = $matches[1];
				if ( isset( $matches[2] ) ) {
					$lang = $matches[2];
				} else {
					$lang = $wgLanguageCode;
				}
				$availableLangs[$lang] = true;
			}
		}
		return array_keys( $availableLangs );
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

			if ( $this->runTranslateJob ) {
				// Must be run after ad has finished saving due to some dependencies that
				// exist in the render job.
				// TODO: This will go away if we start tracking messages in database :)
				MessageGroups::clearCache();
				MessageIndexRebuildJob::newJob()->run();
				$this->runTranslateJob = false;
			}

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
		//$this->saveMixinData( $db );
		$this->savePriorityLanguageData();
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

		$destAd->setAllocation( $this->allocateToAnon(), $this->allocateToLoggedIn() );
		$destAd->setCategory( $this->getCategory() );
		//$destAd->setMixins( array_keys( $this->getMixins() ) );

		$destAd->setBodyContent( $this->getBodyContent() );

		// Populate the message fields
		$langs = $this->getAvailableLanguages();
		$fields = $this->extractMessageFields();
		foreach ( $langs as $lang ) {
			foreach ( $fields as $field => $count ) {
				$text = $this->getMessageField( $field )->getContents( $lang );
				if ( $text !== null ) {
					$destAd->getMessageField( $field )->update( $text, $lang, $user );
				}
			}
		}

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
		global $wgPromoterUseTranslateExtension;

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
			$pageId = $article->getPage()->getId();
			$article->doDeleteArticle( 'Promoter automated removal' );

			if ( $wgPromoterUseTranslateExtension ) {
				// Remove any revision tags related to the ad
				Ad::removeTag( 'ad:translate', $pageId );

				// And the preferred language metadata if it exists
				TranslateMetadata::set(
					AdMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					false
				);
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc=" Random stuff that still needs to die a hideous horrible death">
	/**
	 * Add a revision tag for the ad
	 * @param string $tag The name of the tag
	 * @param integer $revisionId ID of the revision
	 * @param integer $pageId ID of the MediaWiki page for the ad
	 * @param string $adId ID of ad this revtag belongs to
	 * @throws MWException
	 */
	static function addTag( $tag, $revisionId, $pageId, $adId ) {
		$dbw = PRDatabase::getDb();

		if ( is_object( $revisionId ) ) {
			throw new MWException( 'Got object, excepted id' );
		}

		// There should only ever be one tag applied to an ad object
		Ad::removeTag( $tag, $pageId );

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag ),
			'rt_revision' => $revisionId
		);

		if ( $adId !== null ) {
			$conds['rt_value'] = $adId;
		}

		$dbw->insert( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Make sure ad is not tagged with specified tag
	 * @param string $tag The name of the tag
	 * @param integer $pageId ID of the MediaWiki page for the ad
	 * @throws MWException
	 */
	static protected function removeTag( $tag, $pageId ) {
		$dbw = PRDatabase::getDb();

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag )
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Given one or more campaign ids, return all ads bound to them
	 *
	 * @param array $campaigns list of campaign numeric IDs
	 *
	 * @return array a 2D array of ads with associated weights and settings
	 */
	static function getCampaignAds( $campaigns ) {
		$dbr = PRDatabase::getDb();

		$ads = array();

		if ( $campaigns ) {
			$res = $dbr->select(
				// Aliases (keys) are needed to avoid problems with table prefixes
				array(
					'campaigns' => 'pr_campaigns',
					'ads' => 'pr_ads',
					'adlinks' => 'pr_adlinks',
				),
				array(
					'ad_name',
					'adl_weight',
					'ad_display_anon',
					'ad_display_user',
					'cmp_name',
				),
				array(
					'campaigns.cmp_id' => $campaigns,
					'campaigns.cmp_id = adlinks.cmp_id',
					'adlinks.ad_id = ads.ad_id'
				),
				__METHOD__,
				array(),
				array()
			);

			foreach ( $res as $row ) {
				$ads[ ] = array(
					'name'             => $row->ad_name, // name of the ad
					'weight'           => intval( $row->adl_weight ), // weight assigned to the ad
					'display_anon'     => intval( $row->ad_display_anon ), // display to anonymous users?
					'display_user'     => intval( $row->ad_display_user ), // display to logged in users?
					'campaign'         => $row->cmp_name, // campaign the ad is assigned to
				);
			}
		}
		return $ads;
	}

	/**
	 * Return settings for an ad
	 *
	 * @param $adName string name of ad
	 * @param $detailed boolean if true, get some more expensive info
	 *
	 * @throws MWException
	 * @return array an array of ad settings
	 */
	static function getAdSettings( $adName, $detailed = true ) {
		$ad = Ad::fromName( $adName );
		if ( !$ad->exists() ) {
			throw new MWException( "Ad doesn't exist!" );
		}

		$details = array(
			'anon'             => (int)$ad->allocateToAnon(),
			'user'          => (int)$ad->allocateToLoggedIn(),
			//'controller_mixin' => implode( ",", array_keys( $ad->getMixins() ) ),
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
	 * @param $user             User causing the change
	 * @param $displayAnon      integer flag for display to anonymous users
	 * @param $displayAccount   integer flag for display to logged in users
	 * @param $mixins           array list of mixins (optional)
	 * @param $priorityLangs    array Array of priority languages for the translate extension
	 *
	 * @return bool true or false depending on whether ad was successfully added
	 */
	static function addAd( $name, $body, $user, $displayAnon, $displayAccount,
						  $mixins = array(), $priorityLangs = array()
	) {
		if ( $name == '' || !Ad::isValidAdName( $name ) || $body == '' ) {
			return 'promoter-null-string';
		}

		$ad = Ad::newFromName( $name );
		if ( $ad->exists() ) {
			return 'promoter-ad-exists';
		}

		$ad->setAllocation( $displayAnon, $displayAccount );
		$ad->setBodyContent( $body );

		array_walk( $landingPages, function ( &$x ) { $x = trim( $x ); } );

		//$ad->setMixins( $mixins );

		$ad->save( $user );
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
		return preg_match( '/^[A-Za-z0-9_]+$/', $name );
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
}

class AdDataException extends MWException {}
class AdContentException extends AdDataException {}
class AdExistenceException extends AdDataException {}
