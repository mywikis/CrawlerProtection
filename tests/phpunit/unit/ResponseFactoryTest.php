<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\ResponseFactory
 */
class ResponseFactoryTest extends TestCase {
	/** @var string */
	private static string $outputPageClassName;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$outputPageClassName = class_exists( '\MediaWiki\Output\OutputPage' )
			? '\MediaWiki\Output\OutputPage'
			: '\OutputPage';
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
		$output = $this->createMock( self::$outputPageClassName );
		$output->expects( $this->once() )
			->method( 'setStatusCode' )
			->with( 403 );
		$output->expects( $this->once() )
			->method( 'addWikiTextAsInterface' );

		$factory = $this->buildFactory();
		$factory->denyAccess( $output );
	}

	/**
	 * @covers ::denyAccess
	 */
	public function testDenyAccessChooses418WhenConfigured() {
		$factory = $this->getMockBuilder( ResponseFactory::class )
			->setConstructorArgs( [
				new ServiceOptions( ResponseFactory::CONSTRUCTOR_OPTIONS, [
					'CrawlerProtectionUse418' => true,
					'CrawlerProtectionRawDenial' => false,
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
	 * @covers ::__construct
	 */
	public function testConstructorAcceptsValidOptions() {
		$factory = $this->buildFactory();
		$this->assertInstanceOf( ResponseFactory::class, $factory );
	}
}
