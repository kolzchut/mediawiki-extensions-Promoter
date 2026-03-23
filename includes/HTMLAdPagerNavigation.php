<?php

namespace MediaWiki\Extension\Promoter;

use HTMLFormField;
use Xml;

class HTMLAdPagerNavigation extends HTMLFormField {
	/**
	 * Empty - no validation can be done on a navigation element
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
	 * @inheritDoc
	 */
	public function getInputHTML( $value ) {
		return $this->mParams[ 'value' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getDiv( $value ) {
		$html = Xml::openElement(
			'div',
			[ 'class' => "pr-ad-list-pager-nav" ]
		);
		$html .= $this->getInputHTML( $value );
		$html .= Xml::closeElement( 'div' );

		return $html;
	}
}
