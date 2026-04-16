#!/bin/bash
# Basic integration test for reuser_agent
set -e

UPLOAD_ID=1
AGENT_BIN="/usr/local/bin/reuser_agent"

if [ ! -x "$AGENT_BIN" ]; then
  echo "reuser_agent binary not found at $AGENT_BIN"
  exit 1
fi

echo "Running reuser_agent with upload ID $UPLOAD_ID..."
$AGENT_BIN $UPLOAD_ID
RESULT=$?

if [ $RESULT -eq 0 ]; then
  echo "reuser_agent ran successfully."
else
  echo "reuser_agent failed with exit code $RESULT."
  exit $RESULT
fi

echo "Check the database for expected changes."
