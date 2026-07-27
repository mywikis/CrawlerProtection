#!/usr/bin/env bash

set -euo pipefail

if (( $# != 2 )); then
	echo "Usage: $0 <base-revision> <head-revision>" >&2
	exit 2
fi

base_revision=$1
head_revision=$2

mapfile -t changed_files < <(
	git diff --name-only --no-renames --diff-filter=ACDMRT "$base_revision" "$head_revision"
)

content_changed=false
i18n_changed=false
for file in "${changed_files[@]}"; do
	top_level_path=${file%%/*}
	if [[ $top_level_path == .* || $file == extension.json ]]; then
		continue
	fi

	content_changed=true
	if [[ $file == i18n/* ]]; then
		i18n_changed=true
	fi
done

if [[ $content_changed == false ]]; then
	echo "No version bump is required for hidden-path-only changes."
	exit 0
fi

old_version=$(git show "$base_revision:extension.json" | jq -er '.version | strings')
new_version=$(git show "$head_revision:extension.json" | jq -er '.version | strings')
version_pattern='^([0-9]+)\.([0-9]+)\.([0-9]+)$'

if [[ ! $old_version =~ $version_pattern ]]; then
	echo "Base version '$old_version' is not in major.minor.patch format." >&2
	exit 1
fi
old_major=$(( 10#${BASH_REMATCH[1]} ))
old_minor=$(( 10#${BASH_REMATCH[2]} ))
old_patch=$(( 10#${BASH_REMATCH[3]} ))

if [[ ! $new_version =~ $version_pattern ]]; then
	echo "New version '$new_version' is not in major.minor.patch format." >&2
	exit 1
fi
new_major=$(( 10#${BASH_REMATCH[1]} ))
new_minor=$(( 10#${BASH_REMATCH[2]} ))
new_patch=$(( 10#${BASH_REMATCH[3]} ))

if [[ $i18n_changed == true ]]; then
	if (( new_major != old_major || new_minor != old_minor || new_patch <= old_patch )); then
		echo "Changes to i18n/ require a patch version bump (currently $old_version -> $new_version)." >&2
		exit 1
	fi
elif (( new_major < old_major ||
	new_major == old_major && new_minor < old_minor ||
	new_major == old_major && new_minor == old_minor && new_patch <= old_patch
)); then
	echo "Content changes require a version bump (currently $old_version -> $new_version)." >&2
	exit 1
fi

echo "Version bump $old_version -> $new_version is valid."
