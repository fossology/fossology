/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Maintenance agent validation, and usage functions
 */

#include "maintagent.h"

/**
 * \biref Print usage message to user
 * \param name absolute path to the binary
 */
FUNCTION void usage(char *name)
{
  printf("Usage: %s [options]\n", name);
  printf("  -a   :: Run all non slow maintenance operations.\n");
  printf("  -A   :: Run all maintenance operations.\n");
  printf("  -D   :: Vacuum Analyze the database.\n");
  printf("  -F   :: Validate folder contents.\n");
  printf("  -g   :: Delete orphan gold files.\n");
  printf("  -h   :: Print help (usage).\n");
  printf("  -l # :: Remove log from file system older than # in YYYY-MM-DD.\n");
  printf("  -L   :: Remove orphaned logs from file system.\n");
  printf("  -N   :: Normalize the (internal) priority numbers.\n");
  printf("  -p   :: Verify file permissions (report only).\n");
  printf("  -P   :: Verify and fix file permissions.\n");
  printf("  -R   :: Remove uploads with no pfiles.\n");
  printf("  -t # :: Remove personal access tokens expired # days ago.\n");
  printf("  -T   :: Remove orphaned temp tables.\n");
  printf("  -U   :: Process expired uploads (slow).\n");
  printf("  -Z   :: Remove orphaned files from the repository (slow).\n");
  printf("  -E   :: Remove orphaned rows from database (slow).\n");
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -I   :: Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use).\n");
  printf("  -v   :: verbose (turns on debugging output)\n");
  printf("  -V   :: print the version info, then exit.\n");
  printf("  -c SYSCONFDIR :: Specify the directory for the system configuration. \n");
} /* Usage() */
