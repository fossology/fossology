/****************************************************************
repwrite: Create a file.

Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License version 2.1 as published by the Free Software Foundation.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this library; if not, write to the Free Software Foundation, Inc.0
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

*************************
stdin = data to write.
****************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int main(int argc, char* argv[])
{
  int LenIn, LenOut;
  int i;
  char Buf[10240];
  FILE* F;

  if (argc != 3)
  {
    fprintf(stderr, "Usage: %s type filename < input\n", argv[0]);
    exit(-1);
  }

  F = fo_RepFwrite(argv[1], argv[2]);
  if (!F)
  {
    fprintf(stderr, "ERROR: Invalid -- type='%s' filename='%s'\n",
      argv[1], argv[2]);
    return (-1);
  }

  LenIn = 1;
  while (LenIn > 0)
  {
    LenIn = fread(Buf, 1, sizeof(Buf), stdin);
    if (LenIn > 0)
    {
      LenOut = 0;
      while (LenOut < LenIn)
      {
        i = fwrite(Buf + LenOut, 1, LenIn - LenOut, F);
        LenOut += i;
        if (i == 0) break;
      }
    }
  }
  fo_RepFclose(F);
  return (0);
} /* main() */

