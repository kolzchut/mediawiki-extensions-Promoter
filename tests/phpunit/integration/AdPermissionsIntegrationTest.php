<?php

namespace MediaWiki\Extension\Promoter\Tests\Integration;

use MediaWiki\Extension\Promoter\Ad;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use User;

/**
 * Full-stack permission tests for the Ad model.
 *
 * Tests cover both:
 *   - authorized users can perform the write (verified by reading state back)
 *   - unauthorized users are blocked with PermissionsError, and DB state
 *     is unchanged after the failed attempt.
 *
 * @group Database
 * @covers \MediaWiki\Extension\Promoter\Ad
 */
class AdPermissionsIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// We write directly to these custom Promoter tables.
		$this->tablesUsed = array_merge(
			$this->tablesUsed ?? [],
			[ 'pr_ads', 'pr_adlinks', 'pr_ad_log' ]
		);
	}

	/**
	 * Make a registered test user, optionally granting promoter-admin.
	 */
	private function makeUser( bool $admin ): User {
		$user = $this->getMutableTestUser( $admin ? [ 'promoter-admin-group' ] : [] )->getUser();
		if ( $admin ) {
			// Grant the right directly so we don't need to register a group.
			$this->overrideUserPermissions( $user, [ 'promoter-admin', 'edit' ] );
		} else {
			$this->overrideUserPermissions( $user, [ 'edit' ] );
		}
		return $user;
	}

	private function countAds( string $name ): int {
		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'pr_ads' )
			->where( [ 'ad_name' => $name ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::addAd
	 */
	public function testAddAdSucceedsForAuthorizedUser(): void {
		$user = $this->makeUser( true );

		$result = Ad::addAd( 'AuthorizedAd', 'body', 'caption', '', $user );

		$this->assertTrue( $result, 'addAd should return true on success' );
		$this->assertSame( 1, $this->countAds( 'AuthorizedAd' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::addAd
	 */
	public function testAddAdBlockedForUnauthorizedUserAndDbUnchanged(): void {
		$user = $this->makeUser( false );

		try {
			Ad::addAd( 'BlockedAd', 'body', 'caption', '', $user );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}

		$this->assertSame( 0, $this->countAds( 'BlockedAd' ),
			'No ad row should have been created when permission was denied' );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::removeAd
	 */
	public function testRemoveAdSucceedsForAuthorizedUser(): void {
		$admin = $this->makeUser( true );
		Ad::addAd( 'AdToRemove', 'body', 'caption', '', $admin );
		$this->assertSame( 1, $this->countAds( 'AdToRemove' ) );

		Ad::removeAd( 'AdToRemove', $admin );
		$this->assertSame( 0, $this->countAds( 'AdToRemove' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::removeAd
	 */
	public function testRemoveAdBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		Ad::addAd( 'AdProtected', 'body', 'caption', '', $admin );
		$this->assertSame( 1, $this->countAds( 'AdProtected' ) );

		$nonAdmin = $this->makeUser( false );

		try {
			Ad::removeAd( 'AdProtected', $nonAdmin );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}

		$this->assertSame( 1, $this->countAds( 'AdProtected' ),
			'Ad should still exist after failed unauthorized removal' );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::save
	 */
	public function testSaveBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		Ad::addAd( 'AdSaveTest', 'body', 'old-caption', '', $admin );

		$ad = Ad::fromName( 'AdSaveTest' );
		$ad->setCaption( 'NEW-CAPTION' );

		$this->expectException( PermissionsError::class );
		$ad->save( $this->makeUser( false ) );
	}

	/**
	 * @covers \MediaWiki\Extension\Promoter\Ad::cloneAd
	 */
	public function testCloneAdBlockedForUnauthorizedUser(): void {
		$admin = $this->makeUser( true );
		Ad::addAd( 'AdToClone', 'body', 'caption', '', $admin );

		$src = Ad::fromName( 'AdToClone' );

		try {
			$src->cloneAd( 'AdCloneDest', $this->makeUser( false ) );
			$this->fail( 'Expected PermissionsError was not thrown' );
		} catch ( PermissionsError $e ) {
			// Exception class is enough; PermissionsError::$permission was deprecated in MW 1.43.
		}

		$this->assertSame( 0, $this->countAds( 'AdCloneDest' ),
			'Clone destination should not exist when caller is unauthorized' );
	}
}
