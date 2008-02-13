/********************************************************
 DBinit: Initialize the DB.

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

/*****************************************************
 ReadSQL: Read in an SQL line, stopping at first "\n" after ";".
 Returns: 1 = data, 0 = EOF.
 *****************************************************/
int	ReadSQL(FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;
  int GotSemi=0;

  if (!Fin || feof(Fin)) return(0);
  memset(Line,'\0',MaxLine);
  i=0;
  MaxLine--;
  while(i < MaxLine)
    {
    C = fgetc(Fin);
    if (C < 0)
	{
	if (i > 0)
	  {
	  return(1); /* got some data */
	  }
	return(0); /* got no data */
	}
    if ((i==0) && strchr("#>",C))
	{
	/* pretend comments and echos have semicolons */
	GotSemi=1;
	Line[i++]=C;
	}
    else if ((C==';'))
	{
	/* pretend comments and echos have semicolons */
	GotSemi=1;
	Line[i++]=C;
	}
    else if ((C=='\n') && GotSemi)
	{
	return(1);
	}
    else Line[i++]=C;
    }
  return(1);
} /* ReadSQL() */

/****************************************************/
int	main	(int argc, char *argv[])
{
  char SQL[10240];
  void *DB=NULL;
  int i;
  int rc;
  long LastSelect=-1;
  FILE *Fin;

  if (argc <= 1)
    {
    fprintf(stderr,"Usage: %s file.sql [file.sql [...]]\n",argv[0]);
    fprintf(stderr,"  Files contain one SQL command per line.\n");
    fprintf(stderr,"  Each SQL command must end with a semicolon.\n");
    fprintf(stderr,"  Lines beginning with '#' are unprinted comments.\n");
    fprintf(stderr,"  Lines beginning with '>' are printed comments (echo).\n");
    fprintf(stderr,"  Lines beginning with '!' ignore their SQL return codes.\n");
    fprintf(stderr,"  Lines beginning with '?' are used to seed conditionals (errors ignored).\n");
    fprintf(stderr,"  Lines beginning with '+' are used if last conditional returned no rows.\n");
    fprintf(stderr,"  Lines beginning with '+>' are printed if last conditional returned no rows.\n");
    fprintf(stderr,"  Lines beginning with '-' are used if last conditional returned rows.\n");
    fprintf(stderr,"  Lines beginning with '->' are printed if last conditional returned rows.\n");
    exit(-1);
    }

  DB = DBopen();
  if (!DB)
    {
    printf("ERROR: Failed to open database\n");
    exit(-1);
    }

  for(i=1; i<argc; i++)
    {
    if (!strcmp(argv[i],"-")) Fin=stdin;
    else Fin = fopen(argv[i],"rb");
    if (!Fin)
      {
      fprintf(stderr,"ERROR: Unable to open file '%s'\n",argv[i]);
      exit(1);
      }
    while(ReadSQL(Fin,SQL,sizeof(SQL)))
      {
      if (SQL[0]=='#') { rc=0; }
      else if (SQL[0]=='!') { DBaccess(DB,SQL+1); rc=0; }
      else if (SQL[0]=='>') { printf("%s\n",SQL+1); rc=0; }
      else if (SQL[0]=='?')
	{
	rc = DBaccess(DB,SQL+1);
	if (rc >= 0) LastSelect = DBdatasize(DB);
	else LastSelect=0;
	rc=0;	/* ignore errors */
	}
      else if (SQL[0]=='+')
	{
	if (LastSelect==0)
		{
		if (SQL[1]=='>') { printf("%s\n",SQL+2); rc=0; }
		else rc = DBaccess(DB,SQL+1);
		}
	else rc=0;
	}
      else if (SQL[0]=='-')
	{
	if (LastSelect>0)
		{
		if (SQL[1]=='>') { printf("%s\n",SQL+2); rc=0; }
		else rc = DBaccess(DB,SQL+1);
		}
	else rc=0;
	}
      else rc = DBaccess(DB,SQL);
      switch(rc)
        {
        case 0: /* no data */
        case 1: /* got data */
	  break;
        default:
	  printf("ERROR: BAD SQL: '%s'\n",SQL);
	  exit(1);
        }
      }
    fclose(Fin);
    } /* for each arg */
  DBclose(DB);
  return(0);
} /* main() */

