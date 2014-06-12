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

	protected $mixinController = null;

	function __construct( IContextSource $context, Ad $ad, $campaignName = null, AllocationContext $allocContext = null ) {
		$this->context = $context;

		$this->ad = $ad;
		$this->campaignName = $campaignName;

		if ( $allocContext === null ) {
			/**
			 * This should only be used when ads are previewed in management forms.
			 * TODO: set realistic context in the admin ui, drawn from the campaign
			 * configuration and current translation settings.
			 */
			$this->allocContext = new AllocationContext( true );
		} else {
			$this->allocContext = $allocContext;
		}

		//$this->mixinController = new MixinController( $this->context, $this->ad->getMixins(), $allocContext );

		//FIXME: it should make sense to do this:
		// $this->mixinController->registerMagicWord( 'campaign', array( $this, 'getCampaign' ) );
		// $this->mixinController->registerMagicWord( 'ad', array( $this, 'getAd' ) );
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
		global $wgNoticeBannerPreview;

		if ( !$wgNoticeBannerPreview ) {
			return '';
		}

		$adName = $this->ad->getName();
		$lang = $this->context->getLanguage()->getCode();

		$previewUrl = $wgNoticeBannerPreview . "{$adName}/{$adName}_{$lang}.png";
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
		global $wgNoticeUseLanguageConversion;
		$parentLang = $lang = $this->context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$parentLang = $lang->getParentLanguage();
		}

		$adHtml = $this->context->msg( $this->ad->getDbKey() )->inLanguage( $parentLang )->text();
		$adHtml .= $this->getResourceLoaderHtml();
		$adHtml = $this->substituteMagicWords( $adHtml );

		if ( $wgNoticeUseLanguageConversion ) {
			$adHtml = $parentLang->getConverter()->convertTo( $adHtml, $lang->getCode() );
		}
		return $adHtml;
	}

	function getPreloadJs() {
		return $this->substituteMagicWords( $this->getPreloadJsRaw() );
	}

	function getPreloadJsRaw() {
		$snippets = $this->mixinController->getPreloadJsSnippets();
		$bundled = array();
		$bundled[] = 'var retval = true;';

		if ( $snippets ) {
			foreach ( $snippets as $mixin => $code ) {
				if ( !$this->context->getRequest()->getFuzzyBool( 'debug' ) ) {
					$code = JavaScriptMinifier::minify( $code );
				}

				$bundled[] = "/* {$mixin}: */ retval &= {$code}";
			}
		}
		$bundled[] = 'return retval;';
		return implode( "\n", $bundled );
	}

	function getResourceLoaderHtml() {
		$modules = $this->mixinController->getResourceLoaderModules();
		if ( $modules ) {
			$html = "<!-- " . implode( ", ", array_keys( $modules ) ) . " -->";
			$html .= Html::inlineScript(
				ResourceLoader::makeLoaderConditionalScript(
					Xml::encodeJsCall( 'mw.loader.load', array_values( $modules ) )
				)
			);
			return $html;
		}
		return "";
	}

	function substituteMagicWords( $contents ) {
		return preg_replace_callback(
			'/{{{([^}:]+)(?:[:]([^}]*))?}}}/',
			array( $this, 'renderMagicWord' ),
			$contents
		);
	}

	function getMagicWords() {
		$words = array( 'ad', 'campaign' );
		//$words = array_merge( $words, $this->mixinController->getMagicWords() );
		return $words;
	}

	protected function renderMagicWord( $re_matches ) {
		$field = $re_matches[1];
		if ( $field === 'ad' ) {
			return $this->ad->getName();
		} elseif ( $field === 'campaign' ) {
			return $this->campaignName;
		}
		$params = array();
		if ( isset( $re_matches[2] ) ) {
			$params = explode( "|", $re_matches[2] );
		}

		$value = $this->mixinController->renderMagicWord( $field, $params );
		if ( $value !== null ) {
			return $value;
		}

		$adMessage = $this->ad->getMessageField( $field );
		return $adMessage->toHtml( $this->context );
	}
}
