#!/bin/sh
# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
# SPDX-FileCopyrightText: © 2018 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

REPODIR=$1

if [ ! -d $REPODIR/localhost/ ]; then
  mkdir -p $REPODIR/localhost/
fi

tar -xf ../testdata/testrepo_gold.tar.gz -C $REPODIR/localhost/
tar -xf ../testdata/testrepo_files.tar.gz -C $REPODIR/localhost/

echo "Create Test Repository success!"
