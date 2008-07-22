/********************************************************
 filter_clean: Remove unnecessary cache files.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**********************
 Here's the problem...
 Cache files created by Filter_License are huge.
 (About 10% of the total disk space.)
 Since they are only needed for bsam, we can remove them if
 bsam is done.

 How do we know if bsam is done?  The agent_lic_status table
 says "true" in the processed column.
 ********************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>
#include <dirent.h>
#include <time.h>
#include <signal.h>

#include <libfossdb.h>
#include <libfossrepo.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int Mode=0;
int Verbose=0;
int Test=0;
#define MAXSQL	1024
#define MAXLINE	1024

/* for DB */
void	*DB=NULL;
char	*Pfile_fk=NULL;
int	Agent_pk=-1;	/* agent ID */

/* forward declarations */
void	ProcessUpload	(long Pid);

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{
  printf("Heartbeat\n");
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/*********************************************
 RemoveCache(): Given a pfile ID and pfile name,
 remove the filter cache (if it exists).
 *********************************************/
void	RemoveCache	(char *PfileId, char *PfileName)
{
  char SQL[MAXSQL];

  if (Verbose > 1) fprintf(stderr,"%s: %s %s\n",__FUNCTION__,PfileId,PfileName);
  /* First, inform the DB that the data no longer exists */
  DBaccess(DB,"BEGIN;");
  memset(SQL,'\0',MAXSQL);
  sprintf(SQL,"SELECT * FROM agent_lic_status WHERE pfile_fk = '%s' FOR UPDATE;",PfileId);
  if (!Test) { DBaccess(DB,SQL); }
  memset(SQL,'\0',MAXSQL);
  sprintf(SQL,"UPDATE agent_lic_status SET inrepository=FALSE WHERE pfile_fk = '%s';",PfileId);
  if (Verbose > 1) fprintf(stderr,"SQL: '%s'\n",SQL);
  if (!Test) { DBaccess(DB,SQL); }
  DBaccess(DB,"COMMIT;");

  /* Now remove the file */
  if (Verbose)
    {
    if (!RepExist("license",PfileName)) fprintf(stderr,"Rep missing: license '%s'\n",PfileName);
    else if (Verbose > 1) fprintf(stderr,"Rep found: license '%s'\n",PfileName);
    }
  if (!Test) RepRemove("license",PfileName);
} /* RemoveCache() */

/*********************************************
 ListUploads(): List every upload ID.
 If flag is set, then call ProcessUpload().
 NOTE: This could have problems if there are
 millions of uploads.
 *********************************************/
void	ListUploads	(int ProcessFlag)
{
  char SQL[MAXSQL];
  int rc;
  int Row,MaxRow;
  void *VDB=NULL;
  long NewPid;

  rc=1;
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"select upload_pk,ufile_fk,upload_desc,upload_filename from upload order by upload_pk;");
  rc = DBaccess(DB,SQL);
  if (rc < 1)
	{
	/* something bad */
	fprintf(stderr,"ERROR pfile %s Database error.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile %s SQL '%s'\n",Pfile_fk,SQL);
	}
  VDB = DBmove(DB);
  /* recurse on each value (but no infinite loops) */
  MaxRow = DBdatasize(VDB);
  for(Row=0; Row < MaxRow; Row++)
      {
      NewPid = atol(DBgetvalue(VDB,Row,0));
      if (NewPid >= 0)
	{
	if (ProcessFlag) ProcessUpload(NewPid);
	else
	  {
	  char *S;
	  printf("%ld :: ",NewPid);
	  S = DBgetvalue(VDB,Row,2);
	  if (!S || !S[0]) S = DBgetvalue(VDB,Row,3);
	  printf("%s\n",S);
	  }
	}
      }
  DBclose(VDB);
} /* ListUploads() */

/*********************************************
 ProcessUpload(): process every cache file related
 to this upload.
 Upload -1 == process all of them!
 *********************************************/
void	ProcessUpload	(long Pid)
{
  char SQL[MAXSQL];
  int rc;
  int Row;
  void *VDB=NULL;

  if (Verbose) fprintf(stderr,"Processing: %ld\n",Pid);
  memset(SQL,'\0',MAXSQL);
  if (Pid == -1)
    {
    /* Don't select "everything" because that will take WAY too long */
    /* Instead, iterate over every upload. */
    ListUploads(1);
    return;
    }


  /* if it gets here, then there is a real upload ID (Pid) */
/**
 SELECT DISTINCT(pfile_pk) AS Akey,
        pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
                    FROM containers
                    INNER JOIN ufile ON ufile_container_fk = contained_fk
                    INNER JOIN pfile ON pfile_pk = pfile_fk
                    INNER JOIN agent_lic_status
                        ON agent_lic_status.pfile_fk = pfile.pfile_pk
                    WHERE agent_lic_status.processed IS TRUE
                    AND agent_lic_status.inrepository IS TRUE
                    AND container_fk = %ld
		    LIMIT 1000;
  %ld = 1223021
 **/
  snprintf(SQL,MAXSQL,
    "SELECT DISTINCT(pfile_pk) AS Akey, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A FROM containers INNER JOIN ufile ON ufile_container_fk = contained_fk INNER JOIN pfile ON pfile_pk = pfile_fk INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = pfile.pfile_pk WHERE agent_lic_status.processed IS TRUE AND agent_lic_status.inrepository IS TRUE AND container_fk = %ld LIMIT 1000;"
      ,Pid);

  /* while there are records, process each record */
  rc=DBaccess(DB,SQL);
  VDB = DBmove(DB);
  while((rc>0) && (DBdatasize(VDB) > 0))
    {
    /* process each entry */
    for(Row=DBdatasize(VDB)-1; Row >= 0; Row--)
      {
      RemoveCache(DBgetvalue(VDB,Row,0),DBgetvalue(VDB,Row,1));
      }

    /* repeat */
    DBclose(VDB);
    rc = DBaccess(DB,SQL);
    VDB = DBmove(DB);
    if (Test) rc=0; /* only process the first set (no infinite loop) */
    }
  DBclose(VDB);
} /* ProcessUpload() */

/**********************************************************************/
/**********************************************************************/
/**********************************************************************/

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
char *  GetFieldValue   (char *Sin, char *Field, int FieldMax,
                         char *Value, int ValueMax)
{
  int s,f,v;
  int GotQuote;

  memset(Field,0,FieldMax);
  memset(Value,0,ValueMax);

  while(isspace(Sin[0])) Sin++; /* skip initial spaces */
  if (Sin[0]=='\0') return(NULL);
  f=0; v=0;

  for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != '='); s++)
    {
    Field[f++] = Sin[s];
    }
  while(isspace(Sin[s])) s++; /* skip spaces after field name */
  if (Sin[s] != '=') /* if it is not a field, then just return it. */
    {
    return(Sin+s);
    }
  if (Sin[s]=='\0') return(NULL);
  s++; /* skip '=' */
  while(isspace(Sin[s])) s++; /* skip spaces after '=' */
  if (Sin[s]=='\0') return(NULL);

  GotQuote='\0';
  if ((Sin[s]=='\'') || (Sin[s]=='"'))
    {
    GotQuote = Sin[s];
    s++; /* skip quote */
    if (Sin[s]=='\0') return(NULL);
    }
  if (GotQuote)
    {
    for( ; (Sin[s] != '\0') && (Sin[s] != GotQuote); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    s++; /* move past the quote */
    }
  else
    {
    /* if it gets here, then there is no quote */
    for( ; (Sin[s] != '\0') && !isspace(Sin[s]); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  while(isspace(Sin[s])) s++; /* skip spaces */
  return(Sin+s);
} /* GetFieldValue() */

/**********************************************
 ReadLine(): Read a single line from a file.
 Used to read from stdin.
 Process line elements.
 Returns: 1 of read data, 0=no data, -1=EOF.
 NOTE: It only returns 1 if a filename changes!
 **********************************************/
int     ReadLine        (FILE *Fin)
{
  int C='@';
  int i=0;      /* index */
  char FullLine[MAXLINE];
  char Field[MAXLINE];
  char Value[MAXLINE];
  char A[MAXLINE];
  char Akey[MAXLINE];
  char *FieldInset;
  int rc=0;     /* assume no data */

  memset(FullLine,0,MAXLINE);
  /* inform scheduler that we're ready for data */
  printf("OK\n");
  alarm(60);
  fflush(stdout);

  if (feof(Fin))
    {
    return(-1);
    }

  /* read a line */
  while(!feof(Fin) && (i < MAXLINE-1) && (C != '\n') && (C>0))
    {
    C=fgetc(Fin);
    if ((C>0) && (C!='\n'))
      {
      FullLine[i]=C;
      i++;
      }
    else if ((C=='\n') && (i==0))
      {
      C='@';  /* ignore blank lines */
      }
    }
  if ((i==0) && feof(Fin)) return(-1);
  if (Verbose > 1) fprintf(stderr,"DEBUG: Line='%s'\n",FullLine);

  /* process the line. */
  switch(Mode)
    {
    case 1: /* line contains process ID */
	ProcessUpload(atol(FullLine));
	break;
    case 2: /* line contains "field=value" pairs: Akey=uploadkey A=pfile */
	/** line format: field='value' **/
	/** Known fields:
	    Akey='pfile key for A'
	    A='Afilename in repository'
	 **/
	FieldInset = FullLine;
	memset(A,'\0',sizeof(A));
	memset(Akey,'\0',sizeof(Akey));
	Pfile_fk = NULL;
	while((FieldInset = GetFieldValue(FieldInset,Field,MAXLINE,Value,MAXLINE)) != NULL)
	  {
	  /* process field/value */
	  if (!strcasecmp(Field,"A")) strcpy(A,Value);
	  if (!strcasecmp(Field,"Akey")) { strcpy(Akey,Value); Pfile_fk=Akey; }
	  }
	if (Verbose) fprintf(stderr,"DEBUG: '%s' '%s'\n",Akey,A);
	if (A[0] && Akey[0]) RemoveCache(Akey,A);
    } /* switch(Mode) */

  return(rc);
} /* ReadLine() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='filter_clean' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'filter_clean' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('filter_clean','unknown','Remove unneeded bsam license cache files');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'filter_clean' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='filter_clean' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'filter_clean' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/**********************************************************************/
/**********************************************************************/
/**********************************************************************/

/*********************************************
 Usage():
 *********************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Usage: %s [options] [uploads]\n",Name);
  fprintf(stderr,"  For each processed file, remove the cache.\n");
  fprintf(stderr,"  Options\n");
  fprintf(stderr,"  -i :: Initialize the DB, then exit.\n");
  fprintf(stderr,"  -L :: List upload IDs.\n");
  fprintf(stderr,"  -s :: operate via the scheduler.  Stdin contains each record to process.\n");
  fprintf(stderr,"  -S :: operate via the scheduler.  Stdin contains each upload ID.\n");
  fprintf(stderr,"  -v :: Verbose (-vv for more verbose)\n");
  fprintf(stderr,"  -T :: TEST -- do not update the DB or delete any files (just pretend)\n");
  fprintf(stderr,"  You can also list one or more upload IDs for processing.\n");
  fprintf(stderr,"  Upload ID of '-1' will process all uploads.\n");
} /* Usage() */

/**********************************************************************/
int	main	(int argc, char *argv[])
{
  int c;
  int ListProj=0;
  int GotArg=0;

  while((c = getopt(argc,argv,"iLSsTv")) != -1)
    {
    switch(c)
      {
      case 'i':
	DB = DBopen();
	if (!DB)
	  {
	  fprintf(stderr,"ERROR: Unable to open DB\n");
	  exit(-1);
	  }
	GetAgentKey();
	DBclose(DB);
	return(0);
      case 'L': ListProj=1; GotArg=1; break;
      case 'S': Mode=1; GotArg=1; break;
      case 's': Mode=2; GotArg=1; break;
      case 'T': Test++; break;
      case 'v': Verbose++; break;
      default:	Usage(argv[0]); exit(-1);
      }
    }

  if (!GotArg)
    {
    Usage(argv[0]);
    exit(-1);
    }

  DB = DBopen();
  if (!DB)
	{
	fprintf(stderr,"ERROR: Unable to open DB\n");
	exit(-1);
	}
  GetAgentKey();

  if (ListProj) ListUploads(0);

  /* Process the command-line */
  for( ; optind < argc; optind++)
    {
    ProcessUpload(atol(argv[optind]));
    }

  /* process from the scheduler */
  if (Mode > 0)
    {
    signal(SIGALRM,ShowHeartbeat);
    while(ReadLine(stdin) >= 0) ;
    }

  DBclose(DB);
  return(0);
} /* main() */

