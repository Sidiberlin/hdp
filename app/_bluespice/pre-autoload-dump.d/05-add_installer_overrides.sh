#!/bin/bash
# Helper script to load the installer into the proper directory
# Copyright: 2021
# License: GPLv3

GREEN='\033[0;32m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
NC='\033[0m'

BRANCH=$(git branch | cut -f 2 -d " ")
BRANCH=${BRANCH%%[[:space:]]}
BRANCH=${BRANCH##[[:space:]]}
BRANCH="${BRANCH//[$'\t\r\n ']}"

if [ "$BRANCH" == "4.1.x" ] || [ "$BRANCH" == "4.2.x" ]
then
	BRANCH="REL1_35-$BRANCH"
fi
if [ "$BRANCH" == "4.3.x" ] || [ "$BRANCH" == "4.4.x" ] || [ "$BRANCH" == "4.5.x" ]
then
	BRANCH="REL1_39-$BRANCH"
fi
if [ "$BRANCH" == "5.0.x" ] || [ "$BRANCH" == "5.1.x" ]
then
	BRANCH="REL1_43-$BRANCH"
fi

printf "\n${PURPLE}Fetching installer: ${NC}"

# HDP: never destroy a populated, vendored mw-config/overrides.
#
# This repo commits mw-config/overrides (19 files). Upstream's logic below
# `rm -rf`s that directory whenever it is not a git checkout and re-clones it
# from the network — so on every install it deleted 19 tracked files and then
# tried to fetch them back.
#
# Here that fetch cannot succeed, for three compounding reasons:
#
#   1. BRANCH above comes from `git branch`, run in the MediaWiki root. In
#      this deployment app/ is bind-mounted into the container on its own and
#      contains no .git, so git exits "not a git repository" and BRANCH is
#      empty. None of the version mappings above match, and `git clone -b ""`
#      fails.
#   2. The fallback then hardcodes a REL1_39- prefix, even though the mapping
#      above sends 5.1.x to REL1_43-. It asks for REL1_39-5.1.x, which does
#      not exist. (Fixed below as well, so the fallback is at least coherent
#      if it is ever reached.)
#   3. Both remotes are unreachable from an offline or network-restricted
#      build anyway.
#
# Net effect before this guard: 19 committed files deleted on every single
# install, never restored, and `composer dump-autoload` still returned 0.
#
# So: if the directory is present, non-empty and not a git checkout, it is the
# vendored copy. Leave it alone. Upstream's clone/pull behaviour is preserved
# for the two cases where it makes sense — a real git checkout, or nothing
# there at all.
if [ -d "mw-config/overrides" ] && [ ! -d "mw-config/overrides/.git" ] \
   && [ -n "$(ls -A mw-config/overrides 2>/dev/null)" ]
then
	printf "${GREEN}[ VENDORED ]${NC}\n"
	printf "  mw-config/overrides ships with this repo; skipping network fetch.\n"
	exit 0
fi

if ! [ -d "mw-config/overrides/.git" ]
then
	rm -rf mw-config/overrides
	git clone -b $BRANCH --depth 1 https://github.com/wikimedia/bluespice-mw-config-overrides.git mw-config/overrides
else
	git -C mw-config/overrides/ pull
fi

# Check if the git command was successful
if [ $? -ne 0 ]
then
	printf "${RED}[ FAILED ]${NC}\n"
	# Try again by pulling out the default branch
	# Read the value in the BLUESPICE-VERSION at the top of the repo
	# and use that as the default branch using cat
	DEFAULT_BRANCH=$(cat BLUESPICE-VERSION)
	# Replace the last character with and X
	# This is because the default branch is 4.2.x
	# and we want to get the REL1_35-4.2.x branch
	# so we replace the last character with an X
	# and then append the REL1_35- to the front
	# of the string
	DEFAULT_BRANCH="${DEFAULT_BRANCH%?}x"
	# HDP: derive the REL prefix instead of hardcoding REL1_39.
	# This branch always asked for REL1_39-<version>, contradicting the
	# mapping at the top of this file, which sends 5.0.x/5.1.x to REL1_43-.
	# On BlueSpice 5.1.x it requested REL1_39-5.1.x, which does not exist, so
	# the fallback could never succeed on the very version this repo ships.
	case "$DEFAULT_BRANCH" in
		4.1.x|4.2.x)             RELDEFAULT_BRANCH="REL1_35-$DEFAULT_BRANCH" ;;
		4.3.x|4.4.x|4.5.x)       RELDEFAULT_BRANCH="REL1_39-$DEFAULT_BRANCH" ;;
		5.0.x|5.1.x)             RELDEFAULT_BRANCH="REL1_43-$DEFAULT_BRANCH" ;;
		*)                       RELDEFAULT_BRANCH="$DEFAULT_BRANCH" ;;
	esac
	rm -rf mw-config/overrides
	git clone -b $RELDEFAULT_BRANCH --depth 1 https://gerrit.wikimedia.org/r/bluespice/mw-config/overrides mw-config/overrides
fi

if [ $? -ne 0 ]
then
	printf "${RED}[ EXCEPTION FAILED ]${NC}\n"
	exit 1
fi

printf "${GREEN}[ DONE ]${NC}\n"
