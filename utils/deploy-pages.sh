#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: © 2018 Siemens AG
# Author: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only
#
# The script helps in creating and publishing doxygen generated documents to
# GitHub Pages

set -o errexit -o nounset -o pipefail
shopt -s extglob

TOP="$(dirname ${BASH_SOURCE[0]})/../"

pushd ${TOP}
# Get output directory from doxygen conf file
OPDIR=$(gawk 'match($0, /^OUTPUT_DIRECTORY[[:space:]]*=[[:space:]]*[[:punct:]]?([^ '\"\''\\]+)[[:punct:]]?$/, m) { print m[1]; }' fossology_doxygen.conf)

# Fetch latest tag for versioning
git fetch --tags
CURR_TAG=$(git describe --abbrev=0 --tags || echo "unknown")

# Set the project version in doxygen conf file
sed -i -e "s/^PROJECT_NUMBER.*=/PROJECT_NUMBER = \"${CURR_TAG}\"/g" fossology_doxygen.conf

# Make docs
doxygen fossology_doxygen.conf

# Clone repo to preserve extra files
git clone https://github.com/${GH_REPO_REF} code_docs
pushd code_docs
rm -rf !(atarashi|.nojekyll|README.md|LICENSE|.|..)
popd

# Copy fresh docs
cp -r ${OPDIR}/html/* ./code_docs/
touch ./code_docs/.nojekyll

# Copy favicon
cp src/www/ui/favicon.ico ./code_docs/favicon.ico
popd
