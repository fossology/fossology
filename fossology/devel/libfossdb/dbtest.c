/********************************************************
 DBtest: Test the API.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
  
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
 ********************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

/*****************************************************
 ReadSQL: Read in an SQL line, stopping at first "\n" after ";".
 Returns: 1 = data, 0 = EOF.
 *****************************************************/
int	ReadSQL(char *Line, int MaxLine)
{
  int C;
  int i;
  int ShowCR=1;
  int GotSemi=0;

  if (feof(stdin)) return(0);
  memset(Line,'\0',MaxLine);
  printf("DB> "); /* prompt */
  fflush(stdout);
  i=0;
  while(i+1 < MaxLine)
    {
    C = fgetc(stdin);
    if (C < 0)
      {
      if (i > 0) return(1); /* got some data */
      printf("\n"); /* past the final prompt */
      return(0); /* got no data */
      }
    else if ((C=='\n') && (i==0))
      {
      if (!ShowCR) printf("DB> "); /* skip blank CR */
      ShowCR=0;
      }
    else Line[i++]=C;
    if (C==';') GotSemi=1;
    else if (GotSemi && (C=='\n')) return(1);
    }
  return(1);
} /* ReadSQL() */

/****************************************************/
int	main	()
{
  char SQL[10240];
  int r,c;
  void *DB=NULL;

  DB = DBopen();
  if (!DB)
    {
    printf("ERROR: Failed to open database\n");
    exit(-1);
    }

  while(ReadSQL(SQL,sizeof(SQL)))
    {
    switch(DBaccess(DB,SQL))
      {
      case 0: /* no data */
	printf("OK\n");
	break;
      case 1: /* got data */
	for(r=0; r<DBdatasize(DB); r++)
	  {
	  for(c=0; c<DBcolsize(DB); c++)
	    {
	    printf("[%d,%d] '%s' = '%s'\n",
		r,c,DBgetcolname(DB,c),DBgetvalue(DB,r,c));
	    }
	  printf("\n");
	  }
	break;
      default:
	printf("ERROR: BAD SQL: '%s'\n",SQL);
      }
    }
  DBclose(DB);
  return(0);
} /* main() */

