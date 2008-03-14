/********************************************************
 delagent: Remove an upload from the DB and repository

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

int Verbose=0;
int Test=0;
#define MAXSQL	1024
#define MAXLINE	1024

/* for DB */
void	*DB=NULL;
char	*Pfile_fk=NULL;
int	Agent_pk=-1;	/* agent ID */
char	SQL[MAXSQL];

/* For heartbeats */
long	ItemsProcessed=0;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{
  if (ItemsProcessed > 0)
    {
    printf("ItemsProcessed %ld\n",ItemsProcessed);
    ItemsProcessed=0;
    }
  else
    {
    printf("Heartbeat\n");
    }
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/*********************************************
 MyDBaccess(): DBaccess with debugging.
 *********************************************/
int	MyDBaccess	(void *V, char *S)
{
  int rc;
  if (Verbose) printf("%s\n",S);
  rc = DBaccess(V,S);
  if (rc < 0)
	{
	fprintf(stderr,"FATAL: SQL failed: '%s'.\n",SQL);
	DBclose(DB);
	exit(-1);
	}
  return(rc);
} /* MyDBaccess() */

/*********************************************
 DeleteLicense(): Given an upload ID, delete all
 licenses associated with it.
 The DoBegin flag determines whether BEGIN/COMMIT
 should be called.
 Do this if you want to reschedule license analysis.
 *********************************************/
void	DeleteLicense	(long UploadId)
{
  void *VDB;
  char *S;
  int Row,MaxRow;

  DBaccess(DB,"SET statement_timeout = 0;"); /* no timeout */
  MyDBaccess(DB,"BEGIN;");

  /* Get the list of pfiles to process */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT DISTINCT(pfile_fk) FROM uptreeup WHERE upload_fk=%ld;",UploadId);
  MyDBaccess(DB,SQL);
  VDB = DBmove(DB);

  /***********************************************/
  /* delete pfile licenses */
  MaxRow = DBdatasize(VDB);
  for(Row=0; Row<MaxRow; Row++)
    {
    S = DBgetvalue(VDB,Row,0);
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_status WHERE pfile_fk = '%s';",S);
    MyDBaccess(DB,SQL);

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_meta where pfile_fk = '%s';",S);
    MyDBaccess(DB,SQL);
    ItemsProcessed++;
    }

  /***********************************************/
  /* Commit the change! */
  if (Test) MyDBaccess(DB,"ROLLBACK;");
  else
	{
	MyDBaccess(DB,"COMMIT;");
	MyDBaccess(DB,"VACUUM ANALYZE agent_lic_status;");
	MyDBaccess(DB,"VACUUM ANALYZE agent_lic_meta;");
	}
  DBaccess(DB,"SET statement_timeout = 120000;");

  DBclose(VDB);
  if (ItemsProcessed > 0)
	{
	/* use heartbeat to say how many are completed */
	raise(SIGALRM);
	}
} /* DeleteLicense() */

/*********************************************
 DeleteUpload(): Given an upload ID, delete it.
 *********************************************/
void	DeleteUpload	(long UploadId)
{
  void *VDB;
  char *S;
  int Row,MaxRow;

  DBaccess(DB,"SET statement_timeout = 0;"); /* no timeout */
  MyDBaccess(DB,"BEGIN;");

  /* Get the list of pfiles to delete */
  /** These are all pfiles in the upload_fk that only appear once. **/
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT DISTINCT(pfile_fk),pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile FROM uptreeup WHERE upload_fk = %ld AND pfile_fk NOT IN (SELECT DISTINCT(pfile_fk) FROM uptreeup WHERE upload_fk != %ld) ORDER BY pfile_fk;",UploadId,UploadId);
  MyDBaccess(DB,SQL);
  VDB = DBmove(DB);

  /***********************************************/
  /* Delete the upload from the folder-contents table */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM foldercontents WHERE (foldercontents_mode & 2) != 0 AND child_id = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  /***********************************************/
  /* Blow away jobs */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM jobdepends WHERE jdep_jq_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk IN (SELECT job_pk FROM job WHERE job_upload_fk = %ld));",UploadId);
  MyDBaccess(DB,SQL);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM jobqueue WHERE jq_job_fk IN (SELECT job_pk FROM job WHERE job_upload_fk = %ld);",UploadId);
  MyDBaccess(DB,SQL);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM job WHERE job_upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  /***********************************************/
  /* Blow upload tree */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM uploadtree WHERE upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM upload WHERE upload_pk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  /***********************************************/
  /* Delete unused ufiles */

/*** Commented out because -- for huge uploads -- this can take more
     than two minutes.  The result is a ton of locked rows and a flood
     of DB timeouts and delays.
 ***/
#if 0
  memset(SQL,'\0',sizeof(SQL));
  /** All of the project info has been deleted.  This simply deletes any
      ufile that is no longer associated with any projects. **/
  /** Delete any ufile not used by uploadtree AND not used by upload. **/
  snprintf(SQL,sizeof(SQL),"DELETE FROM ufile WHERE ufile_pk NOT IN (SELECT DISTINCT(ufile_fk) FROM uploadtree) AND ufile_pk NOT IN (SELECT DISTINCT(ufile_fk) FROM upload);");
  MyDBaccess(DB,SQL);
#endif

  /***********************************************/
  /* delete pfiles that are missing reuse in the DB */
  MaxRow = DBdatasize(VDB);
  for(Row=0; Row<MaxRow; Row++)
    {
    S = DBgetvalue(VDB,Row,0);
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_status WHERE pfile_fk = '%s';",S);
    MyDBaccess(DB,SQL);

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_meta where pfile_fk = '%s';",S);
    MyDBaccess(DB,SQL);

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM attrib WHERE pfile_fk = '%s';",S);
    MyDBaccess(DB,SQL);

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"DELETE FROM pfile WHERE pfile_pk = '%s';",S);
    MyDBaccess(DB,SQL);
    }

  /***********************************************/
  /* Commit the change! */
  if (Test) MyDBaccess(DB,"ROLLBACK;");
  else
	{
	MyDBaccess(DB,"COMMIT;");
	MyDBaccess(DB,"VACUUM ANALYZE agent_lic_status;");
	MyDBaccess(DB,"VACUUM ANALYZE agent_lic_meta;");
	MyDBaccess(DB,"VACUUM ANALYZE attrib;");
	MyDBaccess(DB,"VACUUM ANALYZE ufile;");
	MyDBaccess(DB,"VACUUM ANALYZE pfile;");
	MyDBaccess(DB,"VACUUM ANALYZE foldercontents;");
	MyDBaccess(DB,"VACUUM ANALYZE upload;");
	MyDBaccess(DB,"VACUUM ANALYZE uploadtree;");
	MyDBaccess(DB,"VACUUM ANALYZE jobdepends;");
	MyDBaccess(DB,"VACUUM ANALYZE jobqueue;");
	MyDBaccess(DB,"VACUUM ANALYZE job;");
	}
  DBaccess(DB,"SET statement_timeout = 120000;");

  /***********************************************/
  /* Whew!  Now to delete the actual pfiles from the repository. */
  /** If someone presses ^C now, then at least the DB is accurate. **/
  for(Row=0; Row<MaxRow; Row++)
    {
    memset(SQL,'\0',sizeof(SQL));
    S = DBgetvalue(VDB,Row,1); /* sha1.md5.len */
    if (RepExist("license",S))
	{
	if (Test) printf("TEST: Delete %s %s\n","license",S);
	else RepRemove("license",S);
	}
    if (RepExist("files",S))
	{
	if (Test) printf("TEST: Delete %s %s\n","files",S);
	else RepRemove("files",S);
	}
    if (RepExist("gold",S))
	{
	if (Test) printf("TEST: Delete %s %s\n","gold",S);
	else RepRemove("gold",S);
	}
    ItemsProcessed++;
    }
  DBclose(VDB);
  if (ItemsProcessed > 0)
	{
	/* use heartbeat to say how many are completed */
	raise(SIGALRM);
	}
} /* DeleteUpload() */

/*********************************************
 ListFoldersRecurse(): Draw folder tree.
 if DelFlag is set, then all child uploads are
 deleted and the folders are deleted.
 *********************************************/
void	ListFoldersRecurse	(void *VDB, long Parent, int Depth,
				 int Row, int DelFlag)
{
  int r,MaxRow;
  long Fid;
  int i;
  char *Desc;

  /* Find all folders with this parent and recurse */
  MaxRow = DBdatasize(VDB);
  for(r=0; r < MaxRow; r++)
    {
    if (r == Row) continue; /* skip self-loops */
    /* NOTE: There can be an infinite loop if two rows point to each other.
       A->parent == B and B->parent == A  */
    if (atol(DBgetvalue(VDB,r,1)) == Parent)
	{
	if (!DelFlag)
		{
		for(i=0; i<Depth; i++) fputs("   ",stdout);
		}
	Fid = atol(DBgetvalue(VDB,r,0));
	if (Fid != 0)
		{
		if (!DelFlag)
			{
			printf("%4ld :: %s",Fid,DBgetvalue(VDB,r,2));
			Desc = DBgetvalue(VDB,r,3);
			if (Desc && Desc[0]) printf(" (%s)",Desc);
			printf("\n");
			}
		ListFoldersRecurse(VDB,Fid,Depth+1,r,DelFlag);
		}
	else
		{
		if (DelFlag) DeleteUpload(atol(DBgetvalue(VDB,r,4)));
		else printf("%4s :: Contains: %s\n","--",DBgetvalue(VDB,r,2));
		}
	}
    }

  /* if we're deleting folders, do it now */
  if (DelFlag)
	{
	switch(Parent)
	  {
	  case 1:	/* skip default parent */
		printf("INFO: Default folder not deleted.\n");
		break;
	  case 0:	/* it's an upload */
		break;
	  default:	/* it's a folder */
		memset(SQL,'\0',sizeof(SQL));
		snprintf(SQL,sizeof(SQL),"DELETE FROM foldercontents WHERE foldercontents_mode = 1 AND child_id = '%ld';",Parent);
		if (Test) printf("TEST: %s\n",SQL);
		else MyDBaccess(DB,SQL);

		memset(SQL,'\0',sizeof(SQL));
		snprintf(SQL,sizeof(SQL),"DELETE FROM folder WHERE folder_pk = '%ld';",Parent);
		if (Test) printf("TEST: %s\n",SQL);
		else MyDBaccess(DB,SQL);
		break;
	  } /* switch() */
	}
} /* ListFoldersRecurse() */

/*********************************************
 ListFolders(): List every folder.
 *********************************************/
void	ListFolders	()
{
  int i,j,MaxRow;
  long Fid;	/* folder ids */
  int DetachFlag=0;
  int Match;
  char *Desc;
  void *VDB;

  printf("# Folders\n");
  MyDBaccess(DB,"SELECT folder_pk,parent,name,description,upload_pk FROM leftnav ORDER BY name,parent,folder_pk;");
  VDB = DBmove(DB);
  ListFoldersRecurse(VDB,1,1,-1,0);

  /* Find detached folders */
  MaxRow = DBdatasize(VDB);
  DetachFlag=0;
  for(i=0; i < MaxRow; i++)
      {
      Fid = atol(DBgetvalue(VDB,i,1));
      if (Fid == 1) continue;	/* skip default parent */
      Match=0;
      for(j=0; (j<MaxRow) && !Match; j++)
	{
	if ((i!=j) && (atol(DBgetvalue(VDB,j,0)) == Fid)) Match=1;
	}
      if (!Match && !atol(DBgetvalue(VDB,i,4)))
	{
	if (!DetachFlag) { printf("# Unlinked folders\n"); DetachFlag=1; }
	printf("%4ld :: %s",Fid,DBgetvalue(VDB,i,2));
	Desc = DBgetvalue(VDB,i,3);
	if (Desc && Desc[0]) printf(" (%s)",Desc);
	printf("\n");
	ListFoldersRecurse(VDB,Fid,1,i,0);
	}
      }

  /* Find detached uploads */
  DetachFlag=0;
  for(i=0; i < MaxRow; i++)
      {
      Fid = atol(DBgetvalue(VDB,i,1));
      if (Fid == 1) continue;	/* skip default parent */
      Match=0;
      for(j=0; (j<MaxRow) && !Match; j++)
	{
	if ((i!=j) && (atol(DBgetvalue(VDB,j,0)) == Fid)) Match=1;
	}
      if (!Match && atol(DBgetvalue(VDB,i,4)))
	{
	if (!DetachFlag) { printf("# Unlinked uploads (uploads without folders)\n"); DetachFlag=1; }
	printf("%4s",DBgetvalue(VDB,i,4));
	printf(" :: %s",DBgetvalue(VDB,i,2));
	Desc = DBgetvalue(VDB,i,3);
	if (Desc && Desc[0]) printf(" (%s)",Desc);
	printf("\n");
	}
      }

  DBclose(VDB);
} /* ListFolders() */

/*********************************************
 ListUploads(): List every upload ID.
 *********************************************/
void	ListUploads	()
{
  int Row,MaxRow;
  long NewPid;

  printf("# Uploads\n");
  MyDBaccess(DB,"SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;");

  /* list each value */
  MaxRow = DBdatasize(DB);
  for(Row=0; Row < MaxRow; Row++)
      {
      NewPid = atol(DBgetvalue(DB,Row,0));
      if (NewPid >= 0)
	{
	char *S;
	printf("%ld :: %s",NewPid,DBgetvalue(DB,Row,2));
	S = DBgetvalue(DB,Row,1);
	if (S && S[0]) printf(" (%s)",S);
	printf("\n");
	}
      }
} /* ListUploads() */

/*********************************************
 DeleteFolder(): Given a folder ID, delete it
 AND recursively delete everything below it!
 This includes upload deletion!
 *********************************************/
void	DeleteFolder	(long FolderId)
{
  void *VDB;
  MyDBaccess(DB,"SELECT folder_pk,parent,name,description,upload_pk FROM leftnav ORDER BY name,parent,folder_pk;");
  VDB = DBmove(DB);
  ListFoldersRecurse(VDB,FolderId,0,-1,1);
  DBclose(VDB);
  MyDBaccess(DB,"VACUUM ANALYZE foldercontents;");
  MyDBaccess(DB,"VACUUM ANALYZE folder;");
} /* DeleteFolder() */

/**********************************************************************/
/**********************************************************************/
/**********************************************************************/

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
  char *L;
  int rc=0;     /* assume no data */
  int Type=0;	/* 0=undefined; 1=delete; 2=list */
  int Target=0;	/* 0=undefined; 1=upload; 2=license; 3=folder */
  long Id;

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
  L = FullLine;
  while(isspace(L[0])) L++;

  /** Get the type of command: delete or list **/
  if (!strncasecmp(L,"DELETE",6) && isspace(L[6]))
	{
	Type=1; /* delete */
	L+=6;
	}
  else if (!strncasecmp(L,"LIST",4) && isspace(L[4]))
	{
	Type=2; /* list */
	L+=4;
	}
  while(isspace(L[0])) L++;
  /** Get the target **/
  if (!strncasecmp(L,"UPLOAD",6) && (isspace(L[6]) || !L[6]))
	{
	Target=1; /* upload */
	L+=6;
	}
  else if (!strncasecmp(L,"LICENSE",7) && (isspace(L[7]) || !L[7]))
	{
	Target=2; /* license */
	L+=7;
	}
  else if (!strncasecmp(L,"FOLDER",6) && (isspace(L[6]) || !L[6]))
	{
	Target=3; /* folder */
	L+=6;
	}
  while(isspace(L[0])) L++;
  Id = atol(L);

  /* Handle the request */
  if ((Type==1) && (Target==1))	{ DeleteUpload(Id); rc=1; }
  else if ((Type==1) && (Target==2))	{ DeleteLicense(Id); rc=1; }
  else if ((Type==1) && (Target==3))	{ DeleteFolder(Id); rc=1; }
  else if ((Type==2) && (Target==1))	{ ListUploads(); rc=1; }
  else if ((Type==2) && (Target==2))	{ ListUploads(); rc=1; }
  else if ((Type==2) && (Target==3))	{ ListFolders(); rc=1; }
  else
    {
    printf("ERROR: Unknown command: '%s'\n",FullLine);
    }

  return(rc);
} /* ReadLine() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='delagent' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'delagent' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('delagent','unknown','Remove uploads and folders');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'delagent' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='delagent' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'delagent' from the database table 'agent'\n");
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
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  List or delete uploads.\n");
  fprintf(stderr,"  Options\n");
  fprintf(stderr,"  -i   :: Initialize the DB, then exit.\n");
  fprintf(stderr,"  -u   :: List uploads IDs.\n");
  fprintf(stderr,"  -U # :: Delete upload ID.\n");
  fprintf(stderr,"  -l   :: List uploads IDs. (same as -u, but goes with -L)\n");
  fprintf(stderr,"  -L # :: Delete ALL licenses associated with upload ID.\n");
  fprintf(stderr,"  -f   :: List folder IDs.\n");
  fprintf(stderr,"  -F # :: Delete folder ID and all uploads under this folder.\n");
  fprintf(stderr,"          Folder '1' is the default folder.  '-F 1' will delete\n");
  fprintf(stderr,"          every upload and folder in the navigation tree.\n");
  fprintf(stderr,"  -s   :: Run from the scheduler.\n");
  fprintf(stderr,"  -T   :: TEST -- do not update the DB or delete any files (just pretend)\n");
  fprintf(stderr,"  -v   :: Verbose (-vv for more verbose)\n");
} /* Usage() */

/**********************************************************************/
int	main	(int argc, char *argv[])
{
  int c;
  int ListProj=0, ListFolder=0;
  long DelUpload=0, DelFolder=0, DelLicense=0;
  int Scheduler=0; /* should it run from the scheduler? */
  int GotArg=0;

  while((c = getopt(argc,argv,"ifF:lL:sTuU:v")) != -1)
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
      case 'f': ListFolder=1; GotArg=1; break;
      case 'F': DelFolder=atol(optarg); GotArg=1; break;
      case 'L': DelLicense=atol(optarg); GotArg=1; break;
      case 'l': ListProj=1; GotArg=1; break;
      case 's': Scheduler=1; GotArg=1; break;
      case 'T': Test++; break;
      case 'u': ListProj=1; GotArg=1; break;
      case 'U': DelUpload=atol(optarg); GotArg=1; break;
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
  signal(SIGALRM,ShowHeartbeat);

  if (ListProj) ListUploads();
  if (ListFolder) ListFolders();

  alarm(60);  /* from this point on, handle the alarm */
  if (DelUpload) { DeleteUpload(DelUpload); }
  if (DelFolder) { DeleteFolder(DelFolder); }
  if (DelLicense) { DeleteLicense(DelLicense); }

  /* process from the scheduler */
  if (Scheduler)
    {
    while(ReadLine(stdin) >= 0) ;
    }

  DBclose(DB);
  return(0);
} /* main() */

