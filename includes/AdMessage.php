<?php

class AdMessage {
	function __construct( $ad_name, $name ) {
		$this->ad_name = $ad_name;
		$this->name = $name;
	}

	function getTitle( $lang, $namespace = NS_MEDIAWIKI ) {
		return Title::newFromText( $this->getDbKey( $lang, $namespace ), $namespace );
	}

	/**
	 * Obtains the key of the message as stored in the database. This varies depending on namespace
	 *  - in the MediaWiki namespace messages are Promoter-{ad name}-{message name}/{lang}
	 *  -- except for the content language which is stored without the /{lang} extension
	 *  - in the PR Ad namespace messages are {ad name}-{message name}/{lang}
	 *
	 * @param string|null $lang      Language code
	 * @param int         $namespace Namespace to get key for
	 *
	 * @return string Message database key
	 * @throws MWException
	 */
	function getDbKey( $lang = null, $namespace = NS_MEDIAWIKI ) {
		global $wgLanguageCode;

		if ( $namespace === NS_MEDIAWIKI ) {
			return ( $lang === null or $lang === $wgLanguageCode ) ?
				"Promoter-{$this->ad_name}-{$this->name}" :
				"Promoter-{$this->ad_name}-{$this->name}/{$lang}";
		} elseif ( $namespace === NS_PR_AD ) {
			return "{$this->ad_name}-{$this->name}/{$lang}";
		} else {
			throw new MWException( "Namespace '$namespace' not known for having Promoter messages." );
		}
	}

	/**
	 * Return the whether the message exists, without language fallback.
	 */
	function existsInLang( $lang ) {
		return $this->getTitle( $lang )->exists();
	}

	/**
	 * Obtain the raw contents of the message; stripping out the stupid <message-name> if it's blank
	 *
	 * @returns null|string Will be null if the message does not exist, otherwise will be
	 * the contents of the message.
	 */
	function getContents( $lang ) {
		if ( $this->existsInLang( $lang ) ) {
			$dbKey = $this->getDbKey();
			$rev = Revision::newFromTitle( $this->getTitle( $lang ) );

			if ( !$rev ) {
				// Try harder, might have just been created, otherwise the title wouldn't exist
				$rev = Revision::newFromTitle( $this->getTitle( $lang ), Revision::READ_LATEST );
			}

			if ( !$rev ) {
				return null;
			}

			$msg = $rev->getContent()->getNativeData();
			if ( $msg === "&lt;{$dbKey}&gt;" ) {
				$msg = '';
			}
			return $msg;
		} else {
			return null;
		}
	}

	function toHtml( IContextSource $context ) {
		global $wgNoticeUseLanguageConversion;
		$lang = $context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$lang = $lang->getParentLanguage();
		}
		return $context->msg( $this->getDbKey() )->inLanguage( $lang )->text();
	}

	/**
	 * Add or update message contents
	 */
	function update( $translation, $lang, $user ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$savePage = function( $title, $text ) {
			$wikiPage = new WikiPage( $title );

			if ( class_exists( 'ContentHandler' ) ) {
				// MediaWiki 1.21+
				$content = ContentHandler::makeContent( $text, $title );
				$result = $wikiPage->doEditContent( $content, '/* PR admin */', EDIT_FORCE_BOT );
			} else {
				$wikiPage->doEdit( $translation, '/* PR admin */', EDIT_FORCE_BOT );
			}

			return $wikiPage;
		};

		$savePage( $this->getTitle( $lang ), $translation );

		// If we're using translate : group review; create and protect the english page
		if ( $wgNoticeUseTranslateExtension
			&& ( $lang === $wgLanguageCode )
			&& AdMessageGroup::isUsingGroupReview()
		) {
			$this->protectMessageInPrNamespaces(
				$savePage( $this->getTitle( $lang, NS_PR_AD ), $translation ),
				$user
			);
		}
	}

	/**
	 * Protects a message entry in the PRAd namespace.
	 * The protection lasts for infinity and acts for group
	 * @ref $wgPromoterProtectGroup
	 *
	 * This really is intended only for use on the original source language
	 * because those messages are set via the PR UI; not the translate UI.
	 *
	 * @param WikiPage $page Page containing the message to protect
	 * @param User     $user User doing the protection (ie: the last one to edit the page)
	 */
	protected function protectMessageInPrNamespaces( $page, $user ) {
		global $wgPromoterProtectGroup;

		if ( !$page->getTitle()->getRestrictions( 'edit' ) ) {
			$var = false;

			$page->doUpdateRestrictions(
				array( 'edit' => $wgPromoterProtectGroup, 'move' => $wgPromoterProtectGroup ),
				array( 'edit' => 'infinity', 'move' => 'infinity' ),
				$var,
				'Auto protected by Promoter -- Only edit via Special:Promoter.',
				$user
			);
		}
	}
}
