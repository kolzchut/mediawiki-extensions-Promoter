<?php

/**
 * Generate a group of message definitions for a banner so they can be translated
 */
class AdMessageGroup extends WikiMessageGroup {

	const TRANSLATE_GROUP_NAME_BASE = 'Promoter-tgroup';

	protected $adName = '';

	protected $namespace = NS_PR_AD;

	/**
	 * Constructor.
	 *
	 * @param string $id Unique id for this group.
	 * @param string $title The page name of the Promoter ad
	 */
	public function __construct( $namespace, $title ) {

		$titleObj = Title::makeTitle( $namespace, $title );
		$this->id = static::getTranslateGroupName( $title );

		// For internal usage we just want the name of the ad. In the MediaWiki namespace
		// this is stored with a prefix. Elsewhere (like the Promoter namespace) it is
		// just the page name.
		$this->adName = str_replace( 'Promoter-ad-', '', $title );

		// And now set the label for the Translate UI
		$this->setLabel( $titleObj->getPrefixedText() );
	}

	/**
	 * This is optimized version of getDefinitions that only returns
	 * message keys to speed up message index creation.
	 * @return array
	 */
	public function getKeys() {
		$keys = array();

		$ad = Ad::fromName( $this->adName );
		$fields = $ad->getMessageFieldsFromCache();

		// The MediaWiki page name convention for messages is the same as the
		// convention for ads themselves, except that it doesn't include
		// the 'template' designation.
		if ( $this->namespace == NS_PR_AD ) {
			$msgKeyPrefix = $this->adName . '-';
		} else {
			$msgKeyPrefix = "Promoter-{$this->adName}-";
		}

		foreach ( array_keys( $fields ) as $msgName ) {
			$keys[] = $msgKeyPrefix . $msgName;
		}

		return $keys;
	}

	/**
	 * Fetch the messages for the ad
	 * @return array Array of message keys with definitions.
	 */
	public function getDefinitions() {
		$definitions = array();

		$ad = Ad::fromName( $this->adName );
		$fields = $ad->getMessageFieldsFromCache();

		// The MediaWiki page name convention for messages is the same as the
		// convention for ads themselves, except that it doesn't include
		// the 'template' designation.
		$msgDefKeyPrefix = "Promoter-{$this->adName}-";
		if ( $this->namespace == NS_PR_AD ) {
			$msgKeyPrefix = $this->adName . '-';
		}
		else {
			$msgKeyPrefix = $msgDefKeyPrefix;
		}

		// Build the array of message definitions.
		foreach ( $fields as $msgName => $msgCount ) {
			$defkey = $msgDefKeyPrefix . $msgName;
			$msgkey = $msgKeyPrefix . $msgName;
			$definitions[$msgkey] = wfMessage( $defkey )->inContentLanguage()->plain();
		}

		return $definitions;
	}

	/**
	 * Determine if the Promoter ad group is using the group review feature of translate
	 */
	static function isUsingGroupReview() {
		static $useGroupReview = null;

		if ( $useGroupReview === null ) {
			$group = MessageGroups::getGroup( AdMessageGroup::TRANSLATE_GROUP_NAME_BASE );
			if ( $group && $group->getMessageGroupStates() ) {
				$useGroupReview = true;
			} else {
				$useGroupReview = false;
			}
		}

		return $useGroupReview;
	}

	/**
	 * Constructs the translate group name from any number of alternate forms. The group name is
	 * defined to be 'Promoter-tgroup-<AdName>'
	 *
	 * This function can handle input in the form of:
	 *  - raw ad name
	 *  - Promoter-ad-<ad name>
	 *
	 * @param string $adName The name of the banner
	 *
	 * @return string Canonical translate group name
	 */
	static function getTranslateGroupName( $adName ) {
		if ( strpos( $adName, 'Promoter-template' ) === 0 ) {
			return str_replace( 'Promoter-ad', AdMessageGroup::TRANSLATE_GROUP_NAME_BASE, $adName );
		} else {
			return AdMessageGroup::TRANSLATE_GROUP_NAME_BASE . '-' . $adName;
		}
	}

	/**
	 * Hook to handle message group review state changes. If the $newState for a group is equal to
	 * @see $wgNoticeTranslateDeployStates then this function will copy from the PRAds namespace
	 * into the MW namespace. This implies that the user calling this hook must have site-edit
	 * permissions.
	 *
	 * @param object        $group        Effected group object
	 * @param string        $code         Language code that was modified
	 * @param string        $currentState Review state the group is transitioning from
	 * @param string        $newState     Review state the group is transitioning to
	 *
	 * @return bool
	 */
	static function updateAdGroupStateHook( $group, $code, $currentState, $newState ) {
		global $wgNoticeTranslateDeployStates;

		// We only need to run this if we're actually using group review
		if ( !AdMessageGroup::isUsingGroupReview() ) {
			return true;
		}

		if ( $group instanceof AggregateMessageGroup ) {
			// Deal with an aggregate group object having changed
			$groups = $group->getGroups();
			foreach ( $groups as $subgroup ) {
				AdMessageGroup::updateAdGroupStateHook( $subgroup, $code, $currentState, $newState );
			}
		}
		elseif ( ( $group instanceof AdMessageGroup )
				 && in_array( $newState, $wgNoticeTranslateDeployStates )
		) {
			// Finally an object we can deal with directly and it's in the right state!
			$collection = $group->initCollection( $code );
			$collection->loadTranslations( DB_MASTER );
			$keys = $collection->getMessageKeys();

			// Now copy each key into the MW namespace
			foreach ( $keys as $key ) {
				$wikiPage = new WikiPage(
					Title::makeTitleSafe( NS_PR_AD, $key . '/' . $code )
				);

				// Make sure the translation actually exists :p
				if ( $wikiPage->exists() ) {
					$text = $wikiPage->getContent()->getNativeData();

					$wikiPage = new WikiPage(
						Title::makeTitleSafe( NS_MEDIAWIKI, 'Promoter-' . $key . '/' . $code )
					);
					if ( class_exists( 'ContentHandler' ) ) {
						// MediaWiki 1.21+
						$wikiPage->doEditContent(
							ContentHandler::makeContent( $text, $wikiPage->getTitle() ),
							'Update from translation plugin',
							EDIT_FORCE_BOT
						);
					} else {
						// Legacy -- pre content handler
						$wikiPage->doEdit( $text, 'Update from translation plugin', EDIT_FORCE_BOT );
					}
				}
			}
		}
		else {
			// We do nothing; we don't care about this type of group; or it's in the wrong state
		}

		return true;
	}

	public function getMessageGroupStates() {
		$conf = array(
			'progress' => array( 'color' => 'E00' ),
			'proofreading' => array( 'color' => 'FFBF00' ),
			'ready' => array( 'color' => 'FF0' ),
			'published' => array( 'color' => 'AEA', 'right' => 'promoter-admin' ),
			'state conditions' => array(
				array( 'ready', array( 'PROOFREAD' => 'MAX' ) ),
				array( 'proofreading', array( 'TRANSLATED' => 'MAX' ) ),
				array( 'progress', array( 'UNTRANSLATED' => 'NONZERO' ) ),
				array( 'unset', array( 'UNTRANSLATED' => 'MAX', 'OUTDATED' => 'ZERO', 'TRANSLATED' => 'ZERO' ) ),
			),
		);

		return new MessageGroupStates( $conf );
	}

	/**
	 * TranslatePostInitGroups hook handler
	 * Add ad message groups to the list of message groups that should be
	 * translated through the Translate extension.
	 *
	 * @param array $list
	 * @return bool
	 */
	public static function registerGroupHook( &$list ) {
		$dbr = PRDatabase::getDb( DB_MASTER ); // Must be explicitly master for runs under a jobqueue

		// Create the base aggregate group
		$conf = array();
		$conf['BASIC'] = array(
			'id' => AdMessageGroup::TRANSLATE_GROUP_NAME_BASE,
			'label' => 'Promoter Ads',
			'description' => '{{int:promoter-aggregate-group-desc}}',
			'meta' => 1,
			'class' => 'AggregateMessageGroup',
			'namespace' => NS_PR_AD,
		);
		$conf['GROUPS'] = array();

		// Find all the ads marked for translation
		$tables = array( 'page', 'revtag' );
		$vars   = array( 'page_id', 'page_namespace', 'page_title', );
		$conds  = array( 'page_id=rt_page', 'rt_type' => RevTag::getType( 'banner:translate' ) );
		$options = array( 'GROUP BY' => 'rt_page' );
		$res = $dbr->select( $tables, $vars, $conds, __METHOD__, $options );

		foreach ( $res as $r ) {
			$grp = new AdMessageGroup( $r->page_namespace, $r->page_title );
			$id = $grp::getTranslateGroupName( $r->page_title );
			$list[$id] = $grp;

			// Add the banner group to the aggregate group
			$conf['GROUPS'][] = $id;
		}

		// Update the subgroup meta with any new groups since the last time this was run
		$list[$conf['BASIC']['id']] = MessageGroupBase::factory( $conf );

		return true;
	}

	public static function getLanguagesInState( $banner, $state ) {
		if ( !AdMessageGroup::isUsingGroupReview() ) {
			throw new MWException( 'Promoter is not using group review. Cannot query group review state.' );
		}

		$groupName = AdMessageGroup::getTranslateGroupName( $banner );

		$db = PRDatabase::getDb();
		$result = $db->select(
			'translate_groupreviews',
			'tgr_lang',
			array(
				 'tgr_group' => $groupName,
				 'tgr_state' => $state,
			),
			__METHOD__
		);

		$langs = array();
		while ( $row = $result->fetchRow() ) {
			$langs[] = $row['tgr_lang'];
		}
		return $langs;
	}
}
