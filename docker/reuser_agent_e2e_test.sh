#!/bin/bash
# End-to-end test for C++ reuser_agent
set -e

UPLOAD_ID=1
AGENT_BIN="/usr/local/bin/reuser_agent"
DB="${DB:-fossology}"
DBUSER="${DBUSER:-shiva}"
DBHOST="${DBHOST:-localhost}"

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

echo "Checking database state..."
psql -h $DBHOST -U $DBUSER -d $DB -c "SELECT * FROM main_licenses WHERE upload_id = $UPLOAD_ID;"
psql -h $DBHOST -U $DBUSER -d $DB -c "SELECT * FROM report_conf_reuse WHERE upload_id = $UPLOAD_ID;"
psql -h $DBHOST -U $DBUSER -d $DB -c "SELECT * FROM copyright_events WHERE upload_id = $UPLOAD_ID;"

echo "Check logs for errors or unexpected behavior."
