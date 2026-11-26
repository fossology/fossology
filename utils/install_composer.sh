#!/usr/bin/env bash
# SPDX-FileCopyrightText: © 2017 Maximilian Huber
# SPDX-FileCopyrightText: © 2017 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only
# based on documentation found at https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md

################################################################################
## Configuration:
# commit hash of https://github.com/composer/getcomposer.org

current_github_hash="93dcb87e6dec5783a15fff51da1aa79fc4179c46"
# version of composer (must be present in https://github.com/composer/getcomposer.org/tree/${current_github_hash}/web/download)
version="2.8.9"
# sha256 of composer.phar ${version}, as published at https://getcomposer.org/download/
sha256="8e8829ec2b97fcb05158236984bc252bef902e7b8ff65555a1eeda4ec13fb82b"

##
################################################################################

################################################################################
# prepare

pushd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [[ $1 == '-h' ]]; then
    cat <<EOF
This script is used to install composer in version=${version}.
The composer artifact is downloaded from https://github.com/composer/getcomposer.org with commit_hash=${current_github_hash}.
(These values are hardcoded in the script)

It should be called like
   \$ ${BASH_SOURCE[0]} [-h] [/target/install/path] [-f]
where the optionional path argument is internally called \`install_dir\`

Note: If one passes an relative path (e.g. install_dir=./utils) this gets resolved relative to the project root

The \`install_dir\` defaults to
  - /usr/local/bin  - if the script is called by root or via sudo
  - ./utils   - otherwise
EOF
    exit 0
fi

set -o errexit -o nounset -o pipefail

# if run as root, install by default to /usr/bin, otherwise install to ./utils
if [[ $EUID -ne 0 ]]; then
    install_dir="${1:-utils}"
else
    install_dir="${1:-/usr/local/bin}"
fi

if [[ ! -d "$install_dir" ]]; then
    echo "install_dir=\"${install_dir}\" is not present (currently at \"$(pwd)\")"
    exit 1
fi

filename="composer"
target="$install_dir/$filename"

################################################################################
# do the install

echo "composer $version will be installed to: $target (this will override any old executable)"
tmpfile="$(mktemp "${install_dir}/.composer.XXXXXX")"
trap 'rm -f "$tmpfile"' EXIT

downloaded=""
for url in \
    "https://getcomposer.org/download/$version/composer.phar" \
    "https://github.com/composer/getcomposer.org/raw/$current_github_hash/web/download/$version/composer.phar"; do
  if curl --silent --show-error --fail --location --retry 3 --output "$tmpfile" "$url" \
     && echo "$sha256  $tmpfile" | sha256sum --check --status; then
    downloaded="$url"
    break
  fi
  echo "WARNING: download from $url failed or did not match the expected checksum" >&2
done

if [[ -z "$downloaded" ]]; then
  echo "ERROR: could not download a verified composer $version; $target was left unchanged" >&2
  exit 1
fi

chmod 0755 "$tmpfile"
mv -f "$tmpfile" "$target"
trap - EXIT

popd
