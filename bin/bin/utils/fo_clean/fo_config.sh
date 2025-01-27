#!/bin/bash

TMPDIR=$(mktemp -d /tmp/fossology-configs.XXXXXX) || exit 1

# Old config files to clean up
OLDSAVE="/usr/local/share/fossology/agents/proxy.conf ... "
NEWSAVE="/etc/cron.d/fossology ... "

echo "*** Searching for old fossology config files ***"
for conffile in $OLDSAVE; do
  if [ -e "$conffile" -o -L "$conffile" ]; then
    echo "NOTE: found old $conffile saving to $TMPDIR/ and deleting"
    cp "$conffile" "$TMPDIR/"
    rm -f "$conffile"
  fi
done

echo "*** Searching for new fossology config files ***"
for conffile in $NEWSAVE; do
  if [ -f "$conffile" -o -L "$conffile" ]; then
    path=$(dirname "$conffile")
    destpath="$TMPDIR/$path"
    mkdir -p "$destpath"
    echo "NOTE: found $conffile saving to $destpath/ and deleting as requested"
    mv "$conffile" "$destpath/"
  fi
done

if [ -d /usr/local/etc/fossology ]; then
  rm -rf /usr/local/etc/fossology
fi
