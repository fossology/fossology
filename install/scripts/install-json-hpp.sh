#!/bin/bash

cd "$(dirname "$0")/../.."
FINAL_FILE=./src/copyright/agent/json.hpp
FILE_SHASUM="fbdfec4b4cf63b3b565d09f87e6c3c183bdd45c5be1864d3fcb338f6f02c1733"
FILE_URL="https://github.com/nlohmann/json/releases/download/v3.1.2/json.hpp"
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
