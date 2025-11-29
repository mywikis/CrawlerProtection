# CI Setup - Docker-Based Local Testing

## ✅ Prerequisites

- Docker
- Docker Compose  
- Make
- Git

## 🚀 Quick Start (Recommended)

### 1. Add docker-compose-ci as submodule
```bash
cd /home/greg/src/CrawlerProtection

# Remove build/ from .gitignore if present (it's a submodule now)
sed -i '/^build\/$/d' .gitignore

# Add the submodule
git submodule add https://github.com/gesinn-it-pub/docker-compose-ci.git build
git add .gitignore .gitmodules build Makefile
git commit -m "Add docker-compose-ci for local testing"
```

### 2. Initialize submodule (for fresh clones)
```bash
# When cloning the repo in the future, use:
git clone --recursive https://github.com/freephile/CrawlerProtection.git

# Or if already cloned without --recursive:
git submodule update --init --recursive
```

### 3. Run CI Tests
The `Makefile` is already configured. Just run:
```bash
make ci              # Run all CI checks
make ci-coverage     # Run with coverage
make bash            # Enter container to run commands manually
make down            # Stop containers
```

## 🔧 What Gets Tested

The `make ci` command runs:
- **Lint** - PHP syntax checking (parallel-lint)
- **PHPCS** - Code style validation (MediaWiki standards)
- **PHPUnit** - Unit tests

All in a container with the correct PHP version, extensions, and MediaWiki setup!

## 📋 Common Commands

```bash
# Run all tests
make ci

# Run specific tests inside container
make bash
> composer phpcs                    # Code style check
> composer phpcbf                   # Auto-fix code style
> composer phpunit                  # Run PHPUnit tests
> composer test                     # Run phpcs + phpunit

# Test with different MediaWiki versions
MW_VERSION=1.39 make ci
MW_VERSION=1.43 PHP_VERSION=8.3 make ci

# Clean up
make down
make clean
```

## 🌐 Access Wiki in Browser

Create `build/docker-compose.override.yml`:
```yaml
services:
  wiki:
    ports:
      - 8080:8080
```

Then start: `make up` and visit http://localhost:8080

## 🔄 Update Docker CI

```bash
git submodule update --init --remote
```

## 📝 Environment Variables

Create `.env` file to customize:
```bash
MW_VERSION=1.43
PHP_VERSION=8.2
DB_TYPE=sqlite
EXTENSION=CrawlerProtection
```

## ⚡ Quick Fixes Before Commit

```bash
# Auto-fix code style issues
make bash
> composer phpcbf

# Check what will fail in CI
make ci
```

## 🐛 Troubleshooting

**"build directory not found"**
```bash
git submodule update --init --remote
```

**"Container keeps restarting"**
```bash
make down
make clean
make ci
```

**"Permission denied"**
```bash
sudo chmod -R 777 cache/
```

## 🎯 GitHub Actions Setup

Your `.github/workflows/ci.yml` already exists and will run automatically on:
- Pushes to `main` or `specialPageList` branches
- All pull requests

Check results at: https://github.com/freephile/CrawlerProtection/actions

## 🔗 Resources

- [docker-compose-ci documentation](https://github.com/gesinn-it-pub/docker-compose-ci)
- [MediaWiki coding conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions)
- Your GitHub Actions: https://github.com/freephile/CrawlerProtection/actions
