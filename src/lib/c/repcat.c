/*
 repcat: Cat a file.
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only

 stdout = data from file.
*/
/**
 * \file
 * \brief Cat a file from fo_repo to stdout
 */
#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif

/**
 * @brief Read a file and print to stdout
 * @param argv Requires two arguments (file type and filename)
 */
int main(int argc, char* argv[])
{
  int LenIn, LenOut;
  int i;
  char Buf[10240];
  FILE* F;

  if (argc != 3)
  {
    fprintf(stderr, "Usage: %s type filename > output\n", argv[0]);
    exit(-1);
  }

  F = fo_RepFread(argv[1], argv[2]);
  if (!F)
  {
    fprintf(stderr, "ERROR: Invalid -- type='%s' filename='%s'\n",
      argv[1], argv[2]);
    return (-1);
  }

  LenIn = 1;
  while (LenIn > 0)
  {
    LenIn = fread(Buf, 1, sizeof(Buf), F);
    if (LenIn > 0)
    {
      LenOut = 0;
      while (LenOut < LenIn)
      {
        i = fwrite(Buf + LenOut, 1, LenIn - LenOut, stdout);
        LenOut += i;
        if (i == 0) break;
      }
    }
  }
  fo_RepFclose(F);
  return (0);
} /* main() */

