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

use Html;
use HTMLInfoField;
use MWException;
use Sanitizer;
use Xml;

/**
 * Produces a ad preview DIV that can be embedded in an HTMLForm.
 *
 * Expects the following options:
 * - 'language'  - ISO language code to render ad in
 * - 'ad'    - Canonical name of ad
 * - 'withlabel' - Presence of this attribute causes a label to be shown
 */
class HTMLPromoterAd extends HTMLInfoField {
	/**
	 * Empty - no validation can be done on a ad
	 *
	 * @param array|string $value
	 * @param array $alldata
	 *
	 * @return true
	 */
	public function validate( $value, $alldata ) {
		return true;
	}

	/**
	 * Get a preview of the ad
	 *
	 * @param mixed $value
	 *
	 * @return mixed|string
	 */
	public function getInputHTML( $value ) {
		global $wgOut,
			$wgPromoterAdPreview;

		$adName = $this->mParams['ad'];
		if ( array_key_exists( 'language', $this->mParams ) ) {
			$language = $this->mParams['language'];
		} else {
			$language = $wgOut->getContext()->getLanguage()->getCode();
		}

		$previewUrl = $wgPromoterAdPreview . "/{$adName}/{$adName}_{$language}.png";
		$preview = Html::Element(
			'img',
			[
				'src' => $previewUrl,
				'alt' => $adName,
			]
		);

		return Xml::tags(
			'div',
			[
				 'id' => Sanitizer::escapeId( "pr-ad-preview-$adName" ),
				 'class' => 'pr-ad-preview-div',
			],
			$preview
		);
	}

	/**
	 * @param string $value
	 *
	 * @return string|void
	 * @throws MWException
	 */
	public function getTableRow( $value ) {
		throw new MWException( "getTableRow() is not implemented for HTMLPromoterAd" );
	}

	/**
	 * @param string $value
	 *
	 * @return string|void
	 * @throws MWException
	 */
	public function getRaw( $value ) {
		throw new MWException( "getRaw() is not implemented for HTMLPromoterAd" );
	}

	/**
	 * Get the complete div for the input, including help text,
	 * labels, and whatever.
	 *
	 * @param string $value The value to set the input to.
	 *
	 * @return string Complete HTML table row.
	 */
	public function getDiv( $value ) {
		global $wgOut,
			$wgPromoterAdPreview;

		if ( array_key_exists( 'language', $this->mParams ) ) {
			$language = $this->mParams['language'];
		} else {
			$language = $wgOut->getContext()->getLanguage()->getCode();
		}

		$html = Xml::openElement(
			'div',
			[
				 'id' => Sanitizer::escapeId( "pr-ad-list-element-{$this->mParams['ad']}" ),
				 'class' => "pr-ad-list-element",
			]
		);

		// Make the label; this consists of a text link to the ad editor, and a series of status icons
		if ( array_key_exists( 'withlabel', $this->mParams ) ) {
			$adName = $this->mParams['ad'];
			$html .= Xml::openElement( 'div', [ 'class' => 'pr-ad-list-element-label' ] );
			$html .= \Linker::link(
				\SpecialPage::getTitleFor( 'PromoterAds', "edit/$adName" ),
				htmlspecialchars( $adName ),
				[ 'class' => 'pr-ad-list-element-label-text' ]
			);
			// TODO: Output status icons
			$html .= Xml::tags( 'div', [ 'class' => 'pr-ad-list-element-label-icons' ], '' );
			$html .= Xml::closeElement( 'div' );
		}

		// Add the ad preview
		if ( $wgPromoterAdPreview ) {
			$html .= $this->getInputHTML( null );
		}

		$html .= Xml::closeElement( 'div' );
		return $html;
	}
}
