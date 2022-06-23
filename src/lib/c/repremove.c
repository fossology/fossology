/*
 repremove: Delete a repository entry.
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Delete a repository entry.
 * \sa fo_RepRemove()
 */

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

int main(int argc, char* argv[])
{
  if (argc != 3)
  {
    fprintf(stderr, "Usage: %s type filename\n", argv[0]);
    exit(-1);
  }

  return (fo_RepRemove(argv[1], argv[2]));
} /* main() */

