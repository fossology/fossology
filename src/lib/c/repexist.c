/*
 repexist: Check if a file exists
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Check if a file exists
 *
 * Returns: 0=exist, 1=not exist
 * \sa fo_RepExist()
 */

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

int main(int argc, char* argv[])
{
  int rc;

  if (argc != 3)
  {
    fprintf(stderr, "Usage: %s type filename > output\n", argv[0]);
    fprintf(stderr, "  Returns: 0 if exists in repository, 1 if not in repository.\n");
    exit(-1);
  }

  rc = fo_RepExist(argv[1], argv[2]);
  if (rc == 1)
  {
    printf("0\n");
    return (0);
  }
  printf("1\n");
  return (1);
} /* main() */

