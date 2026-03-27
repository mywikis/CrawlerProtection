---
applyTo: "*"
---

# MediaWiki Extension Clean Code Best Practices

## Service container pattern

Use the service container pattern (based on MediaWikiServices) to define how key objects are constructed and wired together.

## Dependency injection

Use this example from PluggableAuth to implement dependency injection.

Below is an `includes/ServiceWiring.php` file.

```php
return [
	'PluggableAuthFactory' =>
		static function ( MediaWikiServices $services ): PluggableAuthFactory {
			return new PluggableAuthFactory(
				new ServiceOptions( PluggableAuthFactory::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				ExtensionRegistry::getInstance(),
				$services->getAuthManager(),
				LoggerFactory::getInstance( 'PluggableAuth' ),
				$services->getObjectFactory()
			);
		},
	'PluggableAuthService' =>
		static function ( MediaWikiServices $services ): PluggableAuthService {
			return new PluggableAuthService(
				new ServiceOptions( PluggableAuthService::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				ExtensionRegistry::getInstance(),
				$services->getUserFactory(),
				$services->get( 'PluggableAuthFactory' ),
				$services->get( 'PluggableAuth.GroupProcessorRunner' ),
				$services->getPermissionManager(),
				$services->getAuthManager(),
				LoggerFactory::getInstance( 'PluggableAuth' ),
				$services->getUrlUtils()
			);
		},
	'PluggableAuth.GroupProcessorFactory' =>
		static function ( MediaWikiServices $services ): GroupProcessorFactory {
			$factory = new GroupProcessorFactory(
				ExtensionRegistry::getInstance()->getAttribute( 'PluggableAuthGroupSyncs' ),
				$services->getObjectFactory()
			);
			$factory->setLogger( LoggerFactory::getInstance( 'PluggableAuth' ) );
			return $factory;
		},
	'PluggableAuth.GroupProcessorRunner' =>
		static function ( MediaWikiServices $services ): GroupProcessorRunner {
			$factory = new GroupProcessorRunner(
				$services->getService( 'PluggableAuth.GroupProcessorFactory' )
			);
			$factory->setLogger( LoggerFactory::getInstance( 'PluggableAuth' ) );
			return $factory;
		},
];
```

The ServiceWiring.php file returns an associative array where:

- Keys are service names (e.g., 'PluggableAuthFactory', 'PluggableAuth.GroupProcessorRunner')
- Values are factory closures that receive the MediaWikiServices container and return a fully constructed service instance
- When any code in the extension (or elsewhere) calls $services->get('PluggableAuthFactory'), MediaWiki invokes the corresponding closure, injects all dependencies, and returns the object. Services are typically lazily instantiated and cached (singleton per request).

## Loading LocalSettings.php config values into the extension

All configs should be loaded with dependency injection.

## Consuming services

### 1. Declaratively via extension.json

#### Hook handlers

MediaWiki's ObjectFactory reads service names from `extension.json` and automatically injects them as constructor arguments.

```json
"HookHandlers": {
    "main": {
        "class": "MediaWiki\\Extension\\PluggableAuth\\PluggableAuthHooks",
        "services": [
            "PluggableAuthService",
            "UrlUtils"
        ]
    }
},
```

### 2. Internally between services

Services reference each other via `$services->get(...)`.

For example, in the MediaWiki PluggableAuth extension:

- PluggableAuthService depends on PluggableAuthFactory and PluggableAuth.GroupProcessorRunner
- PluggableAuth.GroupProcessorRunner depends on PluggableAuth.GroupProcessorFactory

## Testing services

Dependency injection means code is easily testable. Below is an example of a test file for services.

```php
class PluggableAuthServiceTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $links
	 * @param array $expectedLinks
	 * @param array $options
	 * @param bool $shouldOverrideDefaultLogout
	 * @param string $msg
	 * @return void
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @covers       MediaWiki\Extension\PluggableAuth\PluggableAuthService::modifyLogoutLink
	 * @dataProvider provideTestModifyLogoutLinkData
	 */
	public function testModifyLogoutLink( $links, $expectedLinks, $options, $shouldOverrideDefaultLogout, $msg ) {
		$serviceOptions = new ServiceOptions(
			PluggableAuthService::CONSTRUCTOR_OPTIONS,
			$options
		);
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$userFactory = $this->createMock( UserFactory::class );
		$pluggableAuthPlugin = $this->createMock( PluggableAuthPlugin::class );
		$pluggableAuthPlugin->method( 'shouldOverrideDefaultLogout' )->willReturn( $shouldOverrideDefaultLogout );
		$pluggableAuthFactory = $this->createMock( PluggableAuthFactory::class );
		$pluggableAuthFactory->method( 'getInstance' )->willReturn( $pluggableAuthPlugin );
		$groupProcessorRunner = $this->createMock( GroupProcessorRunner::class );
		$permissionManager = $this->createMock( PermissionManager::class );
		$authManager = $this->createMock( AuthManager::class );
		$logger = $this->createMock( LoggerInterface::class );
		$service = new PluggableAuthService(
			$serviceOptions,
			$extensionRegistry,
			$userFactory,
			$pluggableAuthFactory,
			$groupProcessorRunner,
			$permissionManager,
			$authManager,
			$logger,
			$this->getServiceContainer()->getUrlUtils()
		);

		$service->modifyLogoutLink( $links );

		$this->assertEquals( $expectedLinks, $links, $msg );
	}

}
```