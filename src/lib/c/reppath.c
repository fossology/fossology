/*
 reppath: display the path to the file.
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Display the path to the file.
 * \sa fo_RepMkPath()
 */

#include <stdlib.h>
#include <stdio.h>
#include "libfossology.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

int main(int argc, char* argv[])
{
  char* Path;
  int i;
  char fname[FILENAME_MAX + 1];
  char* sysconfigdir;
  GError* error = NULL;

  sysconfigdir = DEFAULT_SETUP;  /* defined in Makefile */
  if (sysconfigdir)
  {
    snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir, "fossology.conf");
    sysconfig = fo_config_load(fname, &error);
    if (error)
    {
      fprintf(stderr, "FATAL %s.%d: unable to open system configuration: %s\n",
        __FILE__, __LINE__, error->message);
      exit(-1);
    }
  }
  else
  {
    fprintf(stderr, "FATAL %s.%d: Build error, unspecified system configuration location.\n",
      __FILE__, __LINE__);
    exit(-1);
  }

  if ((argc % 2) != 1)
  {
    fprintf(stderr, "Usage: %s type filename [type filename [...]]\n", argv[0]);
    exit(-1);
  }

  for (i = 1; i < argc; i += 2)
  {
    Path = fo_RepMkPath(argv[i], argv[i + 1]);
    if (Path)
    {
      printf("%s\n", Path);
      free(Path);
    }
    else
    {
      fprintf(stderr, "ERROR: type='%s' filename='%s' invalid.\n",
        argv[i], argv[i + 1]);
    }
  }
  return (0);
} /* main() */
