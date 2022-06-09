/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Print agent usage statement
 */

#include "demomod.h"

FUNCTION void Usage(char *Name)
{
  printf("Usage: %s [options] file1 file2 ...\n", Name);
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -v   :: verbose (turns on debugging output)\n");
  printf("  -V   :: print the version info, then exit.\n");
  printf("  -c SYSCONFDIR :: System Config directory (used by testing system). \n");
} /* Usage() */


