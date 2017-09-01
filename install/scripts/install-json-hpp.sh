#!/bin/bash

cd "$(dirname "$0")/../.."
FINAL_FILE=./src/copyright/agent/json.hpp
SHASUM=faa2321beb1aa7416d035e7417fcfa59692ac3d8c202728f9bcc302e2d558f57
TMP=$(mktemp)
CHECKSUMFILE=$(mktemp)

curl -s -o "$TMP" -L https://github.com/nlohmann/json/releases/download/v2.1.1/json.hpp
echo "$SHASUM $TMP" > "$CHECKSUMFILE"
sha256sum --quiet -c "$CHECKSUMFILE"
SUCCESS=$?

if [[ $SUCCESS -eq 0 ]]
then
  mv "$TMP" "$FINAL_FILE"
fi

rm "$CHECKSUMFILE"

exit $SUCCESS
