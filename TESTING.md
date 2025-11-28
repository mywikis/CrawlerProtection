# Local CI Testing with Docker

This extension uses [docker-compose-ci](https://github.com/gesinn-it-pub/docker-compose-ci) for local testing.

## Quick Start

```bash
# One-time setup (if not already done)
git submodule update --init --recursive

# Run all CI checks (lint, phpcs, phpunit)
make ci

# Auto-fix code style issues
make bash
> composer phpcbf

# Stop containers
make down
```

## Why Docker?

- ✅ Same environment as GitHub Actions CI
- ✅ Correct PHP version, extensions, and MediaWiki automatically
- ✅ No need to install MediaWiki locally
- ✅ Test against multiple MW/PHP versions easily
- ✅ Isolated from your local system

## Common Commands

```bash
make ci              # Run all CI checks
make bash            # Enter container shell
make up              # Start wiki (http://localhost:8080)
make down            # Stop containers
make clean           # Remove containers and volumes
```

## Test Different Versions

```bash
# Test with MediaWiki 1.39 and PHP 8.1
MW_VERSION=1.39 PHP_VERSION=8.1 make ci

# Test with MediaWiki 1.43 and PHP 8.3
MW_VERSION=1.43 PHP_VERSION=8.3 make ci
```

## Available Composer Scripts

Inside the container (`make bash`):

```bash
composer test        # Run phpcs + phpunit
composer phpcs       # Check code style
composer phpcbf      # Fix code style
composer phpunit     # Run unit tests
```

## Update Docker CI

```bash
git submodule update --init --remote
```

See `.github/CI-SETUP.md` and `.github/DOCKER-CI-QUICKREF.md` for more details.
