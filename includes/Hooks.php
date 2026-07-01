<?php

namespace MediaWiki\Extension\Promoter;

use MediaWiki\Config\Config;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;

/**
 * Skin hooks for the Promoter extension.
 */
class Hooks implements SkinTemplateNavigation__UniversalHook {

	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly Config $config
	) {
	}

	/**
	 * Add navigation tabs linking the Promoter admin special pages together.
	 *
	 * Fires on every page, but only acts when the current title is one of the
	 * tabified Promoter special pages.
	 *
	 * @param \SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$tabifyPages = $this->config->get( 'PromoterTabifyPages' );

		$title = $sktemplate->getTitle();
		[ $alias, ] = $this->specialPageFactory->resolveAlias( $title->getText() );

		if ( !array_key_exists( $alias, $tabifyPages ) ) {
			return;
		}

		foreach ( $tabifyPages as $page => $keys ) {
			$links[ $keys['type'] ][ $page ] = [
				'text' => $sktemplate->msg( $keys['message'] ),
				'href' => SpecialPage::getTitleFor( $page )->getFullURL(),
				'class' => ( $alias === $page ) ? 'selected' : ''
			];
		}
	}
}
