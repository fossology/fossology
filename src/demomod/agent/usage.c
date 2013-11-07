/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * \file usage.c
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


