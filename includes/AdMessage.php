<?php

class AdMessage {
	function __construct( $ad_name, $name ) {
		$this->ad_name = $ad_name;
		$this->name = $name;
	}

	function getTitle( $namespace = NS_MEDIAWIKI ) {
		return Title::newFromText( $this->getDbKey(), $namespace );
	}

	/**
	 * Obtains the key of the message as stored in the database.
	 *  - in the MediaWiki namespace messages are Promoter-{ad name}-{message name}
	 *
	 * @return string Message database key
	 */
	function getDbKey() {
		return "Promoter-{$this->ad_name}-{$this->name}";
	}

	function toHtml( IContextSource $context ) {
		return $context->msg( $this->getDbKey() )->parse();
	}

	/**
	 * Add or update message contents
	 */
	function update( $translation, $user ) {
		$savePage = function ( $title, $text ) {
			$wikiPage = new WikiPage( $title );
			$content = ContentHandler::makeContent( $text, $title );
			$result = $wikiPage->doEditContent( $content, '/* PR admin */', EDIT_FORCE_BOT );

			return $wikiPage;
		};

		$savePage( $this->getTitle(), $translation );
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
	 * @param User $user User doing the protection (ie: the last one to edit the page)
	 */
	protected function protectMessageInPrNamespaces( $page, $user ) {
		global $wgPromoterProtectGroup;

		if ( !$page->getTitle()->getRestrictions( 'edit' ) ) {
			$var = false;

			$page->doUpdateRestrictions(
				[ 'edit' => $wgPromoterProtectGroup, 'move' => $wgPromoterProtectGroup ],
				[ 'edit' => 'infinity', 'move' => 'infinity' ],
				$var,
				'Auto protected by Promoter -- Only edit via Special:Promoter.',
				$user
			);
		}
	}
}
