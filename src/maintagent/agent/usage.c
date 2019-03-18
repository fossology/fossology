/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2019 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 ***************************************************************/
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
  printf("  -N   :: Normalize the (internal) priority numbers.\n");
  printf("  -p   :: Verify file permissions (report only).\n");
  printf("  -P   :: Verify and fix file permissions.\n");
  printf("  -R   :: Remove uploads with no pfiles.\n");
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
