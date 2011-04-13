/*****************************************************
 DBQ: Standalone tool for playing with the queue.

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
 *****************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXSQL	4096
char SQL[MAXSQL];
int Verbose=0;

/***********************************************
 AddField(): Given a field=value pair, add the field
 to the string.
 ***********************************************/
void	AddField	(char *FV, char *S)
{
  char *Equal;
  Equal = strchr(FV,'=');
  if (!Equal) return;
  strncat(S,FV,Equal-FV);
} /* AddField() */

/***********************************************
 AddValue(): Given a field=value pair, add the value
 to the string.
 ***********************************************/
void	AddValue	(char *FV, char *S)
{
  char *Equal;
  Equal = strchr(FV,'=');
  if (!Equal) return;
  strcat(S,Equal+1);
} /* AddValue() */

/***********************************************
 AddHuman(): Given a field name and optional
 default value, return value.
 Returns static string with value.
 ***********************************************/
char *	AddHuman	(int Num, int Max, char *Field, char *Default)
{
  int i;
  static char S[4096];
  char V[2048];
  int C;
  memset(S,'\0',sizeof(S));
  memset(V,'\0',sizeof(V));
  printf("(%d/%d) ",Num,Max);
  if (Default) printf("%s [%s]: ",Field,Default);
  else printf("%s []: ",Field);
  fflush(stdout); /* make it print */

  i=0;
  while((i < sizeof(V)) && ((C=fgetc(stdin)) > 0) && (C != '\n'))
    {
    V[i++] = C;
    }
  if (i > 0) /* must be at least 3 characters: a=b */
    {
    strcat(S,V);
    }
  else if (Default && (Default[0] != '\0'))
    {
    strcat(S,Default);
    }
  return(S);
} /* AddHuman() */

/***********************************************
 BuildAddRequest(): show the results.
 This creates the SQL "fields" and "values".
 RecNum = -1 for "Add", or PK for modify.
 Returns: Primary key value.
 ***********************************************/
char *	BuildAddRequest	(void *DB, char *Table,
			 int argc, char *argv[],
			 char *RecPKCol, int RecPKNum,
			 char *RecFKCol, char *RecFKVal)
{
  int i,c;
  int DefaultPK;
  int DefaultPKcol;
  int DefaultPFKcol;
  int Default_JQ_Type_col;
  int DefaultFKcol;
  char DefaultPKstring[100];
  char CMD[MAXSQL];
  char *FV;
  static char PKvalue[1024];
  char ListField[MAXSQL];
  char ListValue[MAXSQL];

  memset(ListField,'\0',sizeof(ListField));
  memset(ListValue,'\0',sizeof(ListValue));

  if (argc > 0)
    {
    /* add fields */
    strcat(SQL,"(");
    for(i=0; i < argc; i++)
      {
      if (i > 0) strcat(SQL,",");
      AddField(argv[i],SQL);
      }
    strcat(SQL,") VALUES (");
    for(i=0; i < argc; i++)
      {
      if (i > 0) strcat(SQL,",");
      strcat(SQL,"'");
      AddValue(argv[i],SQL);
      strcat(SQL,"'");
      }
    strcat(SQL,")");
    return(NULL);
    } /* if args */

  /* else if human data entry */
    {
#if 0
    printf("Interactive values...\n");
    printf("  - Leave line blank to use default (no default = leave blank).\n");
    printf("  - Do not use CR except to end line.\n");
#endif

    /* Get the default primary key */
    memset(CMD,'\0',sizeof(CMD));
    sprintf(CMD,"SELECT MAX(%s) from %s;",RecPKCol,Table);
    DBaccess(DB,CMD);
    DefaultPK = atoi(DBgetvalue(DB,0,0))+1;
    sprintf(DefaultPKstring,"%d",DefaultPK);

    /* Get column headings by requesting a job key that does not exist */
    memset(CMD,'\0',sizeof(CMD));
    sprintf(CMD,"SELECT * from %s where %s = '%d' limit 1",
	Table,RecPKCol,RecPKNum);
    DBaccess(DB,CMD);
    /* Get defaults (errors are OK -- ignored) */
    DefaultPKcol = DBgetcolnum(DB,RecPKCol);
    DefaultPFKcol = DBgetcolnum(DB,"job_proj_fk");
    Default_JQ_Type_col = DBgetcolnum(DB,"jq_type");
    if (RecFKCol) DefaultFKcol = DBgetcolnum(DB,RecFKCol);
    else	DefaultFKcol = -1;

    /* add human values */
    for(c=0; c < DBcolsize(DB); c++)
      {
      if (RecPKNum >= 0)
        FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),DBgetvalue(DB,0,c));
      else if (c == DefaultPKcol)
	{
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),DefaultPKstring);
	}
      else if (c == DefaultPFKcol)
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),"0");
      else if (c == DefaultFKcol)
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),RecFKVal);
      else if (c == Default_JQ_Type_col)
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),"test");
      else
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),"");
      if (FV && (FV[0] != '\0'))
	{
        if (ListField[0] != '\0')
	  {
	  strcat(ListField,",");
	  strcat(ListValue,",");
	  }
	strcat(ListField,DBgetcolname(DB,c));
	strcat(ListValue,"'");
	strcat(ListValue,FV);
	strcat(ListValue,"'");
	}
      if (!strcmp(DBgetcolname(DB,c),"job_pk")) sprintf(PKvalue,"%s",FV);
      }

    /* create SQL */
    strcat(SQL,"(");
    strcat(SQL,ListField);
    strcat(SQL,") VALUES (");
    strcat(SQL,ListValue);
    strcat(SQL,")");
    return(PKvalue);
    }
}; /* BuildAddRequest() */

/***********************************************
 DisplayResults(): show the results.
 ***********************************************/
void	DisplayResults	(void *DB)
{
  int r,c;
  for(r=0; r<DBdatasize(DB); r++)
    {
    for(c=0; c<DBcolsize(DB); c++)
      {
      printf("[%d,%d] '%s' = '%s'\n",
	r,c,DBgetcolname(DB,c),DBgetvalue(DB,r,c));
      }
    printf("\n");
    }
} /* DisplayResults() */

/***********************************************
 ProcessList(): List all items in the queue.
 SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue
   LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
   LEFT JOIN jobqueue AS depends
     ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
   LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk
 ***********************************************/
void	ProcessList	(void *DB, int argc, char *argv[])
{
  int i;

  strcat(SQL,"SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk LEFT JOIN jobqueue AS depends ON depends.jq_pk = jobdepends.jdep_jq_depends_fk LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk");
  if (argc > 0)
    {
    strcat(SQL," WHERE jobqueue.jq_pk='");
    strcat(SQL,argv[0]);
    strcat(SQL,"'");
    for(i=1; i<argc; i++)
      {
      strcat(SQL," OR jobqueue.jq_pk='");
      strcat(SQL,argv[i]);
      strcat(SQL,"'");
      }
    }
  strcat(SQL,";");
  if (Verbose) printf("SQL='%s'\n",SQL);
  if (DBaccess(DB,SQL) == 1) DisplayResults(DB);
} /* ProcessList() */

/***********************************************
 ProcessTop(): List all top items in the queue.
 SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue
   LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
   LEFT JOIN jobqueue AS depends
     ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
   LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk
   WHERE 
     jobqueue.jq_starttime IS NULL
     AND ( 
       (depends.jq_endtime IS NOT NULL AND
       (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
       OR jobdepends.jdep_jq_depends_fk IS NULL
       ) 
   ORDER BY job.job_priority,job.job_queued DESC;
 ***********************************************/
void	ProcessTop	(void *DB, int argc, char *argv[])
{
  int i;

  strcat(SQL,"SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk LEFT JOIN jobqueue AS depends ON depends.jq_pk = jobdepends.jdep_jq_depends_fk LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk");
  strcat(SQL," WHERE jobqueue.jq_starttime IS NULL AND ( (depends.jq_endtime IS NOT NULL AND (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 ) OR jobdepends.jdep_jq_depends_fk IS NULL)");

  if (argc > 0)
    {
    strcat(SQL," AND ( jobqueue.jq_pk='");
    strcat(SQL,argv[0]);
    strcat(SQL,"'");
    for(i=1; i<argc; i++)
      {
      strcat(SQL," OR jobqueue.jq_pk='");
      strcat(SQL,argv[i]);
      strcat(SQL,"'");
      }
    strcat(SQL," )");
    }
  strcat(SQL," ORDER BY job.job_priority,job.job_queued DESC");

  strcat(SQL,";");
  if (Verbose) printf("SQL='%s'\n",SQL);
  if (DBaccess(DB,SQL) == 1) DisplayResults(DB);
} /* ProcessTop() */

/***********************************************
 ProcessAdd(): Add a new record to the queue.
 ***********************************************/
void	ProcessAdd	(void *DB, int argc, char *argv[])
{
  int rc;
  char *PK;

  strcat(SQL,"INSERT INTO job ");
  PK=BuildAddRequest(DB,"job",argc,argv,"job_pk",-1,NULL,NULL);
  strcat(SQL,"; ");

  if (Verbose) printf("SQL='%s'\n",SQL);
  strcat(SQL,"INSERT INTO jobqueue ");
  BuildAddRequest(DB,"jobqueue",argc,argv,"jq_pk",-1,"jq_job_fk",PK);
  strcat(SQL,"; ");

  rc = DBaccess(DB,SQL);
  switch(rc)
    {
    case 1: DisplayResults(DB); break;
    case 0: printf("Insert OK\n"); break;
    default: printf("Insert ERROR:\n%s\n",SQL); break;
    }
} /* ProcessAdd() */

/***********************************************
 ProcessUpdate(): Update a queue record.
 ***********************************************/
void	ProcessUpdate	(void *DB, int argc, char *argv[])
{
  int rc;
  int i,c;
  char CMD[MAXSQL];
  char KEYNAME[MAXSQL];
  char *FV;

  strcat(SQL,"UPDATE ");
  strcat(SQL,argv[0]);

  memset(KEYNAME,'\0',sizeof(KEYNAME));
  if (!strcmp(argv[0],"job")) strcpy(KEYNAME,"job_pk");
  else if (!strcmp(argv[0],"jobqueue")) strcpy(KEYNAME,"jq_pk");
  else
	{
	fprintf(stderr,"ERROR: Unknown table: '%s'\n",argv[0]);
	exit(-1);
	}

  if (argc > 2)
    {
    for(i=2; i<argc; i++)
      {
      if (i > 2) strcat(SQL,",");
      strcat(SQL," SET ");
      strcat(SQL,argv[i]);
      }
    }
  else /* Human */
    {
    memset(CMD,'\0',sizeof(CMD));
    /* Get defaults */
    sprintf(CMD,"SELECT * from %s where %s = '%s' limit 1;",
	argv[0],KEYNAME,argv[1]);
    if (DBaccess(DB,CMD) != 1)
	{
	fprintf(stderr,"ERROR: Bad SQL: '%s'\n",CMD);
	exit(1);
	}
    rc=0;
    for(c=0; c < DBcolsize(DB); c++)
	{
	FV=AddHuman(c+1,DBcolsize(DB),DBgetcolname(DB,c),DBgetvalue(DB,0,c));
	if (FV && (FV[0] != '\0'))
	  {
	  if (rc == 0) strcat(SQL," SET ");
	  else strcat(SQL,", ");
	  strcat(SQL,DBgetcolname(DB,c));
	  strcat(SQL," = '");
	  strcat(SQL,FV);
	  strcat(SQL,"'");
	  rc=1;
	  }
	}
    } /* Human */

  strcat(SQL," WHERE ");
  strcat(SQL,KEYNAME);
  strcat(SQL," = '");
  strcat(SQL,argv[1]);
  strcat(SQL,"';");

  rc = DBaccess(DB,SQL);
  switch(rc)
    {
    case 1: DisplayResults(DB); break;
    case 0: printf("Update OK\n"); break;
    default: printf("Update ERROR:\n%s\n",SQL); break;
    }
} /* ProcessUpdate() */

/***********************************************
 ProcessDelete(): Delete a queue record.
 ***********************************************/
void	ProcessDelete	(void *DB, int argc, char *argv[])
{
  int i;
  int rc;
  for(i=0; i < argc; i++)
    {
    memset(SQL,'\0',sizeof(SQL));
    sprintf(SQL,"DELETE FROM jobqueue WHERE jq_job_fk = '%s';",argv[i]);
    rc = DBaccess(DB,SQL);
    switch(rc)
      {
      case 1: DisplayResults(DB); break;
      case 0: printf("Delete OK\n"); break;
      default: printf("Delete ERROR:\n%s\n",SQL); break;
      }
    memset(SQL,'\0',sizeof(SQL));
    sprintf(SQL,"DELETE FROM job WHERE job_pk = '%s';",argv[i]);
    rc = DBaccess(DB,SQL);
    switch(rc)
      {
      case 1: DisplayResults(DB); break;
      case 0: printf("Delete OK\n"); break;
      default: printf("Delete ERROR:\n%s\n",SQL); break;
      }
    }
} /* ProcessDelete() */

/***********************************************
 ProcessClean(): Vacuum and analyze the DB for performance.
 ***********************************************/
void	ProcessClean	(void *DB)
{
  DBaccess(DB,"vacuum; analyze;");
} /* ProcessClean() */

/***********************************************
 Usage():
 ***********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s <command> [args]\n",Name);
  printf("  Commands:\n");
  printf("    list :: list ALL elements in the queue.\n");
  printf("      If args are provided then only list those queue items\n");
  printf("    top :: list top elements in the queue.\n");
  printf("      If args are provided then only list those queue items\n");
  printf("    add  :: add a queue item.\n");
  printf("      Args are field=value pairs to be inserted.  They should be SQL compliant\n");
  printf("      If no args, then you will be prompted for every value\n");
  printf("    update :: update an existing queue item.\n");
  printf("      1st arg type of record modify: 'job' or 'jobqueue'.\n");
  printf("      2nd arg is the ID of the record to modify.\n");
  printf("      Remaining Args are field=value pairs to be modified.\n");
  printf("      They should be SQL compliant\n");
  printf("      If no args, then you will be prompted for every value\n");
  printf("    delete :: remove an existing queue item.\n");
  printf("      Args are JOB IDs of the queue item to delete.\n");
  printf("      This also removes all associated JOBQUEUE records.\n");
#if 0
  printf("    flush :: remove all queue items.\n");
  printf("      No args.\n");
  printf("    clean :: vacuum and analyze the DB for performance.\n");
#endif
} /* Usage() */

/*****************************************************************/
int	main	(int argc, char *argv[])
{
  void *DB;

  memset(SQL,'\0',sizeof(SQL));
  DB=DBopen();
  if (!DB)
    {
    fprintf(stderr,"ERROR: Unable to open database\n");
    exit(-1);
    }

  /* process args */
  if (argc < 2) Usage(argv[0]);
  else if (!strcmp(argv[1],"list")) ProcessList(DB,argc-2,argv+2);
  else if (!strcmp(argv[1],"top")) ProcessTop(DB,argc-2,argv+2);
  else if (!strcmp(argv[1],"add")) ProcessAdd(DB,argc-2,argv+2);
  else if (!strcmp(argv[1],"update") && (argc >= 4)) ProcessUpdate(DB,argc-2,argv+2);
  else if (!strcmp(argv[1],"delete")) ProcessDelete(DB,argc-2,argv+2);
#if 0
  else if (!strcmp(argv[1],"flush")) ProcessFlush(DB);
#endif
  else if (!strcmp(argv[1],"clean")) ProcessClean(DB);
  else Usage(argv[0]);

  /* cleanup */
  DBclose(DB);
  return(0);
} /* main() */

