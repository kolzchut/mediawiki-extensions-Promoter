<?php

namespace MediaWiki\Extension\Promoter\Tests\Integration;

use MediaWiki\Extension\Promoter\AdCampaign;
use MediaWiki\Extension\Promoter\Special\SpecialPromoter;
use MediaWiki\Request\FauxRequest;
use PermissionsError;
use SpecialPageTestBase;
use User;

/**
 * Special:Promoter: who may view it, and how the campaign detail view
 * renders the campaign name.
 *
 * @group Database
 * @covers \MediaWiki\Extension\Promoter\Special\SpecialPromoter
 */
class SpecialPromoterTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed = array_merge(
			$this->tablesUsed ?? [],
			[ 'pr_campaigns', 'pr_adlinks', 'pr_ads' ]
		);
	}

	/** @inheritDoc */
	protected function newSpecialPage() {
		return new SpecialPromoter( $this->getServiceContainer()->getConnectionProvider() );
	}

	private function makeUser( bool $admin ): User {
		$user = $this->getMutableTestUser()->getUser();
		$this->overrideUserPermissions(
			$user,
			$admin ? [ 'promoter-admin', 'edit' ] : [ 'edit' ]
		);
		return $user;
	}

	public function testAnonymousUserIsRefused(): void {
		$anon = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', new FauxRequest(), 'qqx', $anon );
	}

	public function testRegisteredUserWithoutRightIsRefused(): void {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', new FauxRequest(), 'qqx', $this->makeUser( false ) );
	}

	public function testCampaignDetailIsRefusedWithoutRight(): void {
		$admin = $this->makeUser( true );
		AdCampaign::addCampaign( 'RefusedDetail', '0', $admin );

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'listCampaignDetail', 'campaign' => 'RefusedDetail' ] ),
			'qqx',
			$this->makeUser( false )
		);
	}

	public function testAdminCanViewCampaignList(): void {
		$admin = $this->makeUser( true );
		AdCampaign::addCampaign( 'VisibleCampaign', '0', $admin );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), 'qqx', $admin );

		$this->assertStringContainsString( 'VisibleCampaign', $html );
	}

	public function testCampaignHeadingEscapesTheCampaignName(): void {
		$admin = $this->makeUser( true );
		$name = '<i>Italic</i>';
		$this->assertIsInt( AdCampaign::addCampaign( $name, '0', $admin ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'listCampaignDetail', 'campaign' => $name ] ),
			'qqx',
			$admin
		);

		$this->assertStringContainsString(
			'<h2>(promoter-campaign-heading: &lt;i&gt;Italic&lt;/i&gt;)</h2>',
			$html
		);
		$this->assertStringNotContainsString( $name, $html,
			'The campaign name must never reach the page as markup' );
	}
}
