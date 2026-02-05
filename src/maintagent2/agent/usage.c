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
 * \brief Print usage message to user
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
  printf("  -o # :: Remove older gold files from repository older than # in YYYY-MM-DD.\n");
  printf("  -h   :: Print help (usage).\n");
  printf("  -l # :: Remove log files from file system older than # in YYYY-MM-DD.\n");
  printf("  -L   :: Remove orphaned logs from file system.\n");
  printf("  -N   :: Normalize the (internal) priority numbers.\n");
  printf("  -p   :: Verify file permissions (report only) (not implemented).\n");
  printf("  -P   :: Verify and fix file permissions (not implemented).\n");
  printf("  -R   :: Remove uploads with no pfiles.\n");
  printf("  -t # :: Remove personal access tokens expired # days ago.\n");
  printf("  -T   :: Remove orphaned temp tables.\n");
  printf("  -U   :: Process expired uploads (slow) (not implemented).\n");
  printf("  -Z   :: Remove orphaned files from the repository (slow).\n");
  printf("  -E   :: Remove orphaned rows from database (slow).\n");
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -I   :: Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use).\n");
  printf("  -v   :: verbose (repeatable; use multiple -v for increased verbosity, e.g. -vv)\n");
  printf("  -V   :: print the version info, then exit.\n");
  printf("  -c SYSCONFDIR :: Specify the directory for the system configuration. \n");
} /* Usage() */
