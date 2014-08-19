<?php

class AdRenderer {
	/**
	 * @var IContextSource $context
	 */
	protected $context;

	/**
	 * @var Ad $ad
	 */
	protected $ad;

	/**
	 * Campaign in which context the rendering is taking place.  Empty during preview.
	 *
	 * @var string $campaignName
	 */
	protected $campaignName = "";

	function __construct( IContextSource $context, Ad $ad, $campaignName = null, AllocationContext $allocContext = null ) {
		$this->context = $context;

		$this->ad = $ad;
		$this->campaignName = $campaignName;

		if ( $allocContext === null ) {
			/**
			 * This should only be used when ads are previewed in management forms.
			 * TODO: set realistic context in the admin ui, drawn from the campaign
			 * configuration.
			 */
			$this->allocContext = new AllocationContext( true );
		} else {
			$this->allocContext = $allocContext;
		}

	}

	function linkTo() {
		return Linker::link(
			SpecialPage::getTitleFor( 'PromoterAds', "edit/{$this->ad->getName()}" ),
			htmlspecialchars( $this->ad->getName() ),
			array( 'class' => 'pr-ad-title' )
		);
	}

	/**
	 * Render the ad as an html fieldset
	 */
	function previewFieldSet() {
		global $wgPromoterAdPreview;

		if ( !$wgPromoterAdPreview ) {
			return '';
		}

		$adName = $this->ad->getName();
		$lang = $this->context->getLanguage()->getCode();

		$previewUrl = $wgPromoterAdPreview . "{$adName}/{$adName}_{$lang}.png";
		$preview = Html::element(
			'img',
			array(
				 'src' => $previewUrl,
				 'alt' => $adName,
			)
		);

		$label = $this->context->msg( 'promoter-preview', $lang )->text();

		return Xml::fieldset(
			$label,
			$preview,
			array(
				 'class' => 'pr-adpreview',
				 'id' => Sanitizer::escapeId( "pr-ad-preview-{$this->ad->getName()}" ),
			)
		);
	}

	/**
	 * Get the body of the ad, with all transformations applied.
	 *
	 * FIXME: "->inLanguage( $context->getLanguage() )" is necessary due to a bug in DerivativeContext
	 */
	function toHtml() {
		$adHtml = $this->context->msg( $this->ad->getDbKey() )->text();

		return $adHtml;
	}


}
