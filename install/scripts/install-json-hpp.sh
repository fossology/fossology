#!/bin/bash

cd "$(dirname "$0")/../.."
FINAL_FILE=./src/copyright/agent/json.hpp
FILE_SHASUM="faa2321beb1aa7416d035e7417fcfa59692ac3d8c202728f9bcc302e2d558f57"
FILE_URL="https://github.com/nlohmann/json/releases/download/v2.1.1/json.hpp"
CURL_OPTIONS="--max-time 10 --retry 4"

_check_file() {
  [[ -r "$2" ]] || return 1
  local checksum_file=$(mktemp)
  echo "$1 $2" > "$checksum_file"
  sha256sum --quiet -c "$checksum_file"
  local res=$?
  rm "$checksum_file"
  return $res
}

# Check if file is already available
_check_file "$SHASUM" "$FINAL_FILE" && exit 0

# Proxy Setup: If needed, configure the proxy variables in the appropriate config file
proxy_file=/etc/fossology/fossology-proxy.conf
[ -r $proxy_file ] && . $proxy_file

TMP=$(mktemp)
curl -s $CURL_OPION -o "$TMP" -L $FILE_URL
SUCCESS=$?

if [[ $SUCCESS -ne 0 ]]
then
  echo "Failed to download file $FINAL_FILE"
else
  _check_file "$SHASUM" "$TMP"
  SUCCESS=$?
fi

if [[ $SUCCESS -eq 0 ]]
then
  mv "$TMP" "$FINAL_FILE"
fi

exit $SUCCESS
