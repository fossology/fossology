/****************************************************************
 repexist: Check if a file exists
  
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

 ***********************
 Returns: 0=exist, 1=not exist
 ****************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include "libfossrepo.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int	main	(int argc, char *argv[])
{
  int rc;

  if (argc != 3)
    {
    fprintf(stderr,"Usage: %s type filename > output\n",argv[0]);
    fprintf(stderr,"  Returns: 0 if exists in repository, 1 if not in repository.\n");
    exit(-1);
    }

  rc = fo_RepExist(argv[1],argv[2]);
  if (rc==1) { printf("0\n"); return(0); }
  printf("1\n");
  return(1);
} /* main() */

