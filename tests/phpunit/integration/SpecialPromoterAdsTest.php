<?php

namespace MediaWiki\Extension\Promoter\Tests\Integration;

use MediaWiki\Extension\Promoter\Ad;
use MediaWiki\Extension\Promoter\Special\SpecialPromoterAds;
use MediaWiki\Request\FauxRequest;
use PermissionsError;
use SpecialPageTestBase;
use User;

/**
 * Special:PromoterAds: who may view the ad list and the ad editor, and that
 * the ad list's bulk actions run only on a POST carrying a valid edit token.
 *
 * @group Database
 * @covers \MediaWiki\Extension\Promoter\Special\SpecialPromoterAds
 */
class SpecialPromoterAdsTest extends SpecialPageTestBase {

	private const AD_NAME = 'PromoterSpecialTestAd';

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed = array_merge(
			$this->tablesUsed ?? [],
			[ 'pr_ads', 'pr_adlinks', 'pr_ad_log', 'pr_campaigns', 'page', 'revision', 'archive' ]
		);
	}

	/** @inheritDoc */
	protected function newSpecialPage() {
		return new SpecialPromoterAds( $this->getServiceContainer()->getConnectionProvider() );
	}

	private function makeUser( bool $admin ): User {
		$user = $this->getMutableTestUser()->getUser();
		$this->overrideUserPermissions(
			$user,
			$admin ? [ 'promoter-admin', 'edit' ] : [ 'edit' ]
		);
		return $user;
	}

	/**
	 * Create an ad that no campaign is bound to, so it can be removed.
	 */
	private function makeUnboundAd( User $admin ): int {
		$this->assertTrue( Ad::addAd( self::AD_NAME, 'body', 'caption', '', $admin ) );
		$id = Ad::fromName( self::AD_NAME )->getId();
		$this->assertIsInt( $id );
		return $id;
	}

	private function countAds(): int {
		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'pr_ads' )
			->where( [ 'ad_name' => self::AD_NAME ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	public static function provideSubPages(): array {
		return [
			'ad list' => [ '' ],
			'ad editor' => [ 'edit/' . self::AD_NAME ],
		];
	}

	/**
	 * @dataProvider provideSubPages
	 */
	public function testAnonymousUserIsRefused( string $subPage ): void {
		$this->makeUnboundAd( $this->makeUser( true ) );
		$anon = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( $subPage, new FauxRequest(), 'qqx', $anon );
	}

	/**
	 * @dataProvider provideSubPages
	 */
	public function testRegisteredUserWithoutRightIsRefused( string $subPage ): void {
		$this->makeUnboundAd( $this->makeUser( true ) );

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( $subPage, new FauxRequest(), 'qqx', $this->makeUser( false ) );
	}

	public function testAdminCanViewAdList(): void {
		$admin = $this->makeUser( true );
		$id = $this->makeUnboundAd( $admin );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), 'qqx', $admin );

		$this->assertStringContainsString( "wpapplyTo-$id", $html );
	}

	public function testRemoveOnGetDeletesNothing(): void {
		$admin = $this->makeUser( true );
		$id = $this->makeUnboundAd( $admin );

		$request = new FauxRequest(
			[ 'wpaction' => 'remove', "wpapplyTo-$id" => '1' ],
			false
		);
		$this->executeSpecialPage( '', $request, 'qqx', $admin );

		$this->assertSame( 1, $this->countAds(), 'A GET must not remove an ad' );
	}

	public function testRemoveOnPostWithBadTokenDeletesNothing(): void {
		$admin = $this->makeUser( true );
		$id = $this->makeUnboundAd( $admin );

		// An explicit token stops SpecialPageExecutor from filling in a valid one.
		$request = new FauxRequest(
			[ 'wpaction' => 'remove', "wpapplyTo-$id" => '1', 'wpEditToken' => 'bogus+\\' ],
			true
		);
		$this->executeSpecialPage( '', $request, 'qqx', $admin );

		$this->assertSame( 1, $this->countAds(), 'A POST with a bad token must not remove an ad' );
	}

	public function testRemoveOnPostWithoutTokenDeletesNothing(): void {
		$admin = $this->makeUser( true );
		$id = $this->makeUnboundAd( $admin );

		// SpecialPageExecutor adds a valid token to any POST that lacks one, so
		// an empty token stands in for "no token".
		$request = new FauxRequest(
			[ 'wpaction' => 'remove', "wpapplyTo-$id" => '1', 'wpEditToken' => '' ],
			true
		);
		$this->executeSpecialPage( '', $request, 'qqx', $admin );

		$this->assertSame( 1, $this->countAds(), 'A POST without a token must not remove an ad' );
	}

	/**
	 * Control for the tests above: the same request, posted with a valid token,
	 * does remove the ad. Without it those tests could pass because the remove
	 * path is broken rather than guarded.
	 */
	public function testRemoveOnPostWithValidTokenDeletesTheAd(): void {
		$admin = $this->makeUser( true );
		$id = $this->makeUnboundAd( $admin );

		// No wpEditToken: SpecialPageExecutor fills in the performer's valid token.
		$request = new FauxRequest(
			[ 'wpaction' => 'remove', "wpapplyTo-$id" => '1' ],
			true
		);
		$this->executeSpecialPage( '', $request, 'qqx', $admin );

		$this->assertSame( 0, $this->countAds(), 'A tokened POST should remove the ad' );
	}
}
