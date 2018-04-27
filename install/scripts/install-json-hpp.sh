#!/bin/bash

cd "$(dirname "$0")/../.."
FINAL_FILE=./src/copyright/agent/json.hpp
SHASUM=fbdfec4b4cf63b3b565d09f87e6c3c183bdd45c5be1864d3fcb338f6f02c1733
TMP=$(mktemp)
CHECKSUMFILE=$(mktemp)

curl -s -o "$TMP" -L https://github.com/nlohmann/json/releases/download/v3.1.2/json.hpp
echo "$SHASUM $TMP" > "$CHECKSUMFILE"
sha256sum --quiet -c "$CHECKSUMFILE"
SUCCESS=$?

if [[ $SUCCESS -eq 0 ]]
then
  mv "$TMP" "$FINAL_FILE"
fi

rm "$CHECKSUMFILE"

exit $SUCCESS
