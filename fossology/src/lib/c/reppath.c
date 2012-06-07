/****************************************************************
 reppath: display the path to the file.
  
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
#include "libfossology.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int	main	(int argc, char *argv[])
{
  char *Path;
  int i;
  char  fname[FILENAME_MAX + 1];
  char* sysconfigdir;
  GError* error = NULL;

  sysconfigdir = DEFAULT_SETUP;  /* defined in Makefile */
  if(sysconfigdir) 
  {
    snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir, "fossology.conf");
    sysconfig = fo_config_load(fname, &error);
    if(error)
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

  if ((argc%2) != 1)
  {
    fprintf(stderr,"Usage: %s type filename [type filename [...]]\n",argv[0]);
    exit(-1);
  }

  for(i=1; i<argc; i+=2)
    {
    Path = fo_RepMkPath(argv[i],argv[i+1]);
    if (Path)
	{
	printf("%s\n",Path);
	free(Path);
	}
    else
	{
	fprintf(stderr,"ERROR: type='%s' filename='%s' invalid.\n",
		argv[i],argv[i+1]);
	}
    }
  return(0);
} /* main() */
