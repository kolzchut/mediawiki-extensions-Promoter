<?php

namespace MediaWiki\Extension\Promoter;

/**
 * Class PromoterHtmlForm
 */
class PromoterHtmlForm extends \HTMLForm {
	/**
	 * Get the whole body of the form.
	 * @return string
	 */
	public function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'pr-formsection-' );
	}
}
