#!/usr/bin/env bash

set -euo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
checker="$script_dir/check-version-bump.sh"
temporary_directory=$(mktemp -d)
trap 'rm -rf "$temporary_directory"' EXIT

create_repository() {
	rm -rf "$temporary_directory/repository"
	mkdir "$temporary_directory/repository"
	cd "$temporary_directory/repository"
	git init --quiet
	git config user.email test@example.com
	git config user.name "Version Check Test"
	mkdir -p .github i18n includes tests
	write_version 1.4.1
	echo "Initial content" > README.md
	echo "{}" > i18n/en.json
	echo "<?php" > includes/Example.php
	echo "<?php" > tests/ExampleTest.php
	git add .
	git commit --quiet -m "Initial content"
	base_revision=$(git rev-parse HEAD)
}

write_version() {
	printf '{ "version": "%s" }\n' "$1" > extension.json
}

commit_changes() {
	git add -A
	git commit --quiet -m "Test change"
	head_revision=$(git rev-parse HEAD)
}

expect_pass() {
	local description=$1
	if ! "$checker" "$base_revision" "$head_revision"; then
		echo "FAIL: $description should pass." >&2
		exit 1
	fi
}

expect_fail() {
	local description=$1
	if "$checker" "$base_revision" "$head_revision"; then
		echo "FAIL: $description should fail." >&2
		exit 1
	fi
}

create_repository
echo "Workflow update" > .github/config
commit_changes
expect_pass "hidden-path-only changes"

create_repository
echo "Updated content" > README.md
commit_changes
expect_fail "content changes without a bump"

create_repository
echo '{"updated":true}' > i18n/en.json
write_version 1.4.2
commit_changes
expect_pass "i18n changes with a patch bump"

create_repository
echo '{"updated":true}' > i18n/en.json
write_version 1.5.0
commit_changes
expect_fail "i18n changes with a minor bump"

create_repository
echo "<?php // Updated" > includes/Example.php
write_version 2.0.0
commit_changes
expect_pass "includes changes with a major bump"

create_repository
echo "<?php // Updated" > tests/ExampleTest.php
write_version 1.5.0
commit_changes
expect_pass "test changes with a minor bump"

create_repository
mv README.md .github/README.md
commit_changes
expect_fail "moves from visible to hidden paths without a bump"

echo "All version bump checks passed."
