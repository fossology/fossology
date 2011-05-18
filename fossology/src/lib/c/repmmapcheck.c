/****************************************************************
 repmmapcheck: Check if mmap() works.
 
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
 ****************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int	main	(int argc, char *argv[])
{
  RepMmapStruct *M;

  if (argc != 3)
    {
    fprintf(stderr,"Usage: %s type filename > output\n",argv[0]);
    exit(-1);
    }

  M = fo_RepMmap(argv[1],argv[2]);
  if (!M)
    {
    fprintf(stderr,"ERROR: failed to mmap file.\n");
    return(-1);
    }

  printf("Successfully mapped %ld bytes\n",(long)(M->MmapSize));

  fo_RepMunmap(M);
  return(0);
} /* main() */

