/*
 repmmapcheck: Check if mmap() works.
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Check if mmap() works.
 * \sa fo_RepMmap()
 */
#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

int main(int argc, char* argv[])
{
  RepMmapStruct* M;

  if (argc != 3)
  {
    fprintf(stderr, "Usage: %s type filename > output\n", argv[0]);
    exit(-1);
  }

  M = fo_RepMmap(argv[1], argv[2]);
  if (!M)
  {
    fprintf(stderr, "ERROR: failed to mmap file.\n");
    return (-1);
  }

  printf("Successfully mapped %ld bytes\n", (long) (M->MmapSize));

  fo_RepMunmap(M);
  return (0);
} /* main() */

