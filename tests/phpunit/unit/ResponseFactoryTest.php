<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\ResponseFactory
 */
class ResponseFactoryTest extends TestCase {
	/** @var string */
	private static string $outputPageClassName;

	/** @var string */
	private static string $webRequestClassName;

	/** @var string */
	private static string $webResponseClassName;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$outputPageClassName = class_exists( '\MediaWiki\Output\OutputPage' )
			? '\MediaWiki\Output\OutputPage'
			: '\OutputPage';
		self::$webRequestClassName = class_exists( '\MediaWiki\Request\WebRequest' )
			? '\MediaWiki\Request\WebRequest'
			: '\WebRequest';
		self::$webResponseClassName = class_exists( '\MediaWiki\Request\WebResponse' )
			? '\MediaWiki\Request\WebResponse'
			: '\WebResponse';
	}

	/**
	 * Build an OutputPage mock whose getRequest()->response() is a mock too.
	 *
	 * @param MockObject|null &$response Receives the WebResponse mock
	 * @return MockObject OutputPage mock
	 */
	private function buildOutputMock( &$response = null ) {
		$response = $this->createMock( self::$webResponseClassName );
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'response' )->willReturn( $response );

		$output = $this->createMock( self::$outputPageClassName );
		$output->method( 'getRequest' )->willReturn( $request );

		return $output;
	}

	/**
	 * Build a ResponseFactory with given config values.
	 *
	 * @param array $overrides Config overrides
	 * @return ResponseFactory
	 */
	private function buildFactory( array $overrides = [] ): ResponseFactory {
		$defaults = [
			'CrawlerProtectionUse418' => false,
			'CrawlerProtectionRawDenial' => false,
			'CrawlerProtectionRawDenialHeader' => 'HTTP/1.0 403 Forbidden',
			'CrawlerProtectionRawDenialText' => '403 Forbidden',
		];

		$config = array_merge( $defaults, $overrides );

		return new ResponseFactory(
			new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, $config )
		);
	}

	/**
	 * @covers ::denyAccess
	 * @covers ::denyAccessPretty
	 */
	public function testDenyAccessPrettySetStatusCode() {
		if ( defined( 'MEDIAWIKI' ) ) {
			$this->markTestSkipped(
				'Skipped in MediaWiki integration environment: wfMessage() requires service container'
			);
		}

		$output = $this->buildOutputMock();
		$output->expects( $this->once() )
			->method( 'setStatusCode' )
			->with( 403 );
		$output->expects( $this->once() )
			->method( 'setRobotPolicy' )
			->with( 'noindex,nofollow' );
		$output->expects( $this->once() )
			->method( 'addWikiTextAsInterface' );

		$factory = $this->buildFactory();
		$factory->denyAccess( $output );
	}

	/**
	 * The pretty denial must ask crawlers not to index or follow the URL.
	 *
	 * @covers ::denyAccessPretty
	 */
	public function testPrettyDenialSendsRobotsHeader() {
		if ( defined( 'MEDIAWIKI' ) ) {
			$this->markTestSkipped(
				'Skipped in MediaWiki integration environment: wfMessage() requires service container'
			);
		}

		$output = $this->buildOutputMock( $response );
		$response->expects( $this->once() )
			->method( 'header' )
			->with( 'X-Robots-Tag: noindex,nofollow' );

		$factory = $this->buildFactory();
		$factory->denyAccess( $output );
	}

	/**
	 * @covers ::denyAccess
	 */
	public function testDenyAccessChooses418WhenBothRawAndUse418() {
		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => true,
					'CrawlerProtectionRawDenial' => true,
					'CrawlerProtectionRawDenialHeader' => '',
					'CrawlerProtectionRawDenialText' => '',
				] )
			] )
			->onlyMethods( [ 'denyAccessWith418' ] )
			->getMock();

		$factory->expects( $this->once() )->method( 'denyAccessWith418' );

		$output = $this->createMock( self::$outputPageClassName );
		$factory->denyAccess( $output );
	}

	/**
	 * When RawDenial is false, Use418 should be a no-op and the factory
	 * must fall through to the pretty 403 page.
	 *
	 * @covers ::denyAccess
	 */
	public function testDenyAccessIgnoresUse418WhenRawDenialDisabled() {
		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => true,
					'CrawlerProtectionRawDenial' => false,
					'CrawlerProtectionRawDenialHeader' => '',
					'CrawlerProtectionRawDenialText' => '',
				] )
			] )
			->onlyMethods( [ 'denyAccessPretty', 'denyAccessWith418' ] )
			->getMock();

		$factory->expects( $this->never() )->method( 'denyAccessWith418' );

		$output = $this->createMock( self::$outputPageClassName );
		$factory->expects( $this->once() )
			->method( 'denyAccessPretty' )
			->with( $output );

		$factory->denyAccess( $output );
	}

	/**
	 * @covers ::denyAccess
	 */
	public function testDenyAccessChoosesRawWhenConfigured() {
		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => false,
					'CrawlerProtectionRawDenial' => true,
					'CrawlerProtectionRawDenialHeader' => 'HTTP/1.0 403 Forbidden',
					'CrawlerProtectionRawDenialText' => '403 Forbidden',
				] )
			] )
			->onlyMethods( [ 'denyAccessRaw' ] )
			->getMock();

		$factory->expects( $this->once() )
			->method( 'denyAccessRaw' )
			->with( 'HTTP/1.0 403 Forbidden', '403 Forbidden' );

		$output = $this->createMock( self::$outputPageClassName );
		$factory->denyAccess( $output );
	}

	/**
	 * @covers ::denyAccess
	 */
	public function testDenyAccessFallsThroughToPretty() {
		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => false,
					'CrawlerProtectionRawDenial' => false,
					'CrawlerProtectionRawDenialHeader' => '',
					'CrawlerProtectionRawDenialText' => '',
				] )
			] )
			->onlyMethods( [ 'denyAccessPretty' ] )
			->getMock();

		$output = $this->createMock( self::$outputPageClassName );
		$factory->expects( $this->once() )
			->method( 'denyAccessPretty' )
			->with( $output );

		$factory->denyAccess( $output );
	}

	/**
	 * When CrawlerProtectionRawDenialText is empty, denyAccess must fall back
	 * to the i18n message for the raw-denial body instead of passing an empty
	 * string to denyAccessRaw.
	 *
	 * @covers ::denyAccess
	 */
	public function testDenyAccessRawUsesI18nWhenOverrideIsEmpty() {
		if ( defined( 'MEDIAWIKI' ) ) {
			$this->markTestSkipped(
				'Skipped in MediaWiki integration environment: wfMessage() requires service container'
			);
		}

		// Arrange
		$capturedHeader = null;
		$capturedBody = null;

		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => false,
					'CrawlerProtectionRawDenial' => true,
					'CrawlerProtectionRawDenialHeader' => 'HTTP/1.0 403 Forbidden',
					'CrawlerProtectionRawDenialText' => '',
				] )
			] )
			->onlyMethods( [ 'denyAccessRaw' ] )
			->getMock();

		$factory->method( 'denyAccessRaw' )
			->willReturnCallback(
				static function ( string $header, string $message ) use ( &$capturedHeader, &$capturedBody ) {
					$capturedHeader = $header;
					$capturedBody = $message;
				}
			);

		$output = $this->createMock( self::$outputPageClassName );

		// Act
		$factory->denyAccess( $output );

		// Assert
		$this->assertSame( 'HTTP/1.0 403 Forbidden', $capturedHeader );
		$this->assertSame( 'Mock message', $capturedBody );
	}

	/**
	 * When both Use418 and RawDenial are enabled, denyAccess should produce a
	 * 418 response whose body comes from the i18n teapot message rather than a
	 * hardcoded string.
	 *
	 * @covers ::denyAccess
	 * @covers ::denyAccessWith418
	 */
	public function testDenyAccessWith418UsesI18nMessage() {
		if ( defined( 'MEDIAWIKI' ) ) {
			$this->markTestSkipped(
				'Skipped in MediaWiki integration environment: wfMessage() requires service container'
			);
		}

		// Arrange
		$capturedHeader = null;
		$capturedBody = null;

		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => true,
					'CrawlerProtectionRawDenial' => true,
					'CrawlerProtectionRawDenialHeader' => '',
					'CrawlerProtectionRawDenialText' => '',
				] )
			] )
			->onlyMethods( [ 'denyAccessRaw' ] )
			->getMock();

		$factory->method( 'denyAccessRaw' )
			->willReturnCallback(
				static function ( string $header, string $message ) use ( &$capturedHeader, &$capturedBody ) {
					$capturedHeader = $header;
					$capturedBody = $message;
				}
			);

		$output = $this->createMock( self::$outputPageClassName );

		// Act
		$factory->denyAccess( $output );

		// Assert
		$this->assertSame( 'HTTP/1.0 418 I\'m a teapot', $capturedHeader );
		$this->assertSame( 'Mock message', $capturedBody );
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstructorAcceptsValidOptions() {
		$factory = $this->buildFactory();
		$this->assertInstanceOf( ResponseFactory::class, $factory );
	}
}
