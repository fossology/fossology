/*
 rephost: display the host to the file.
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Check the host of the file
 *
 * Returns: hostname/localhost
 * \sa fo_RepGetHost()
 */

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

int main(int argc, char* argv[])
{
  char* Host;
  int i;

  if ((argc % 2) != 1)
  {
    fprintf(stderr, "Usage: %s type filename [type filename [...]]\n", argv[0]);
    exit(-1);
  }

  for (i = 1; i < argc; i += 2)
  {
    Host = fo_RepGetHost(argv[i], argv[i + 1]);
    if (!Host) printf("localhost\n");
    else
    {
      printf("%s\n", Host);
      free(Host);
    }
  }
  return (0);
} /* main() */

