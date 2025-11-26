-include .env
export

# setup for docker-compose-ci build directory
ifeq (,$(wildcard ./build/))
    $(shell git submodule update --init --remote)
endif

EXTENSION=CrawlerProtection

# docker images
MW_VERSION?=1.43
PHP_VERSION?=8.2
DB_TYPE?=mysql
DB_IMAGE?="mariadb:11.2"

# composer
# Enables "composer update" inside of extension
# Leave empty/unset to disable, set to "true" to enable
COMPOSER_EXT?=

# nodejs
# Enables node.js related tests and "npm install"
# Leave empty/unset to disable, set to "true" to enable
NODE_JS?=

include build/Makefile
