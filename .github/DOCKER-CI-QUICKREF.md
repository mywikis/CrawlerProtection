# Docker CI Quick Reference

## Setup (One Time)
```bash
git submodule add https://github.com/gesinn-it-pub/docker-compose-ci.git build
git add .gitignore .gitmodules build Makefile TESTING.md
git commit -m "Add docker-compose-ci for local testing"
```

## Daily Use

```bash
make ci              # Run all checks (before commit)
make bash            # Fix issues manually
  > composer phpcbf  # Auto-fix code style
make down            # Clean up
```

## All Commands

| Command | Purpose |
|---------|---------|
| `make ci` | Run all CI checks (lint, phpcs, phpunit) |
| `make bash` | Enter container shell |
| `make up` | Start wiki (http://localhost:8080) |
| `make down` | Stop all containers |
| `make clean` | Remove all containers and volumes |

## Inside Container (`make bash`)

| Command | Purpose |
|---------|---------|
| `composer test` | Run phpcs + phpunit |
| `composer phpcs` | Check code style |
| `composer phpcbf` | Fix code style automatically |
| `composer phpunit` | Run unit tests |

## Test Different Versions

```bash
MW_VERSION=1.39 PHP_VERSION=8.1 make ci    # Test MW 1.39 + PHP 8.1
MW_VERSION=1.43 PHP_VERSION=8.3 make ci    # Test MW 1.43 + PHP 8.3
```

## Troubleshooting

```bash
make down && make clean    # Nuclear option: clean everything
git submodule update --init --remote    # Update docker-compose-ci
```

## See Also

- Full docs: `.github/CI-SETUP.md`
- Testing guide: `TESTING.md`
- Your CI runs: https://github.com/freephile/CrawlerProtection/actions
