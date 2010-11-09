/***************************************************************
 wget_agent: Retrieve a file and put it in the database.

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

 ***************************************************************/
#include <stdlib.h>

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <grp.h>
#include <libgen.h>

#define lstat64(x,y) lstat(x,y)
#define stat64(x,y) stat(x,y)
typedef struct stat stat_t;

#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"

#include "../ununpack/checksum.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXCMD	2048
char SQL[MAXCMD];

/* for the DB */
void *DB;

/* input for this system */
long GlobalUploadKey=-1;
char GlobalTempFile[MAXCMD];
char GlobalURL[MAXCMD];
int GlobalImportGold=1;	/* set to 0 to not store file in gold repository */
gid_t ForceGroup=-1;

/* for debugging */
int Debug=0;


/*********************************************************
 DBLoadGold(): Insert a file into the database and repository.
 (This mimicks the old webgoldimport.)
 *********************************************************/
void	DBLoadGold	()
{
  Cksum *Sum;
  char *Unique=NULL;
  char *SHA1, *MD5, *Len;
  char SQL[MAXCMD];
  long PfileKey;
  char *Path;
  FILE *Fin;
  int rc;

  if (Debug) printf("Processing %s\n",GlobalTempFile);
  Fin = fopen(GlobalTempFile,"rb");
  if (!Fin)
	{
	printf("ERROR upload %ld Unable to open temp file.\n",GlobalUploadKey);
	printf("LOG upload %ld Unable to open temp file %s from %s\n",
		GlobalUploadKey,GlobalTempFile,GlobalURL);
	fflush(stdout);
	DBclose(DB);
	exit(1);
	}
  Sum = SumComputeFile(Fin);
  fclose(Fin);
  if (ForceGroup > 0) { chown(GlobalTempFile,-1,ForceGroup); }

  if (!Sum)
	{
	printf("ERROR upload %ld Unable to compute checksum.\n",GlobalUploadKey);
	printf("LOG upload %ld Unable to compute checksum for %s from %s\n",
		GlobalUploadKey,GlobalTempFile,GlobalURL);
	fflush(stdout);
	DBclose(DB);
	exit(2);
	}
  if (Sum->DataLen <= 0)
	{
	printf("ERROR upload %ld No bytes downloaded from %s.\n",GlobalUploadKey,GlobalURL);
	printf("LOG upload %ld No bytes downloaded from %s to %s.\n",
		GlobalUploadKey,GlobalURL,GlobalTempFile);
	fflush(stdout);
	DBclose(DB);
	exit(3);
	}
  Unique = SumToString(Sum);
  if (Debug) printf("Unique %s\n",Unique);

  if (GlobalImportGold)
    {
    if (Debug) printf("Import Gold %s\n",Unique);
    rc = RepImport(GlobalTempFile,"gold",Unique,1);
    if (rc != 0)
	{
	printf("ERROR upload %ld Failed to import file into the repository (RepImport=%d).\n",GlobalUploadKey,rc);
	printf("LOG upload %ld Failed to import %s from %s into gold %s\n",
		GlobalUploadKey,GlobalTempFile,GlobalURL,Unique);
	fflush(stdout);
	DBclose(DB);
	exit(4);
	}
    /* Put the file in the "files" repository too */
    Path = RepMkPath("gold",Unique);
    if (ForceGroup >= 0) { chown(Path,-1,ForceGroup); }
    } /* if GlobalImportGold */
  else /* if !GlobalImportGold */
    {
    Path = GlobalTempFile;
    } /* else if !GlobalImportGold */
  if (Debug) printf("Path is %s\n",Path);

  if (!Path)
	{
	printf("ERROR upload %ld Failed to determine repository location.\n",GlobalUploadKey);
	printf("LOG upload %ld Failed to determine repository location for %s in gold\n",
		GlobalUploadKey,Unique);
	fflush(stdout);
	DBclose(DB);
	exit(5);
	}
  if (Debug) printf("Import files %s\n",Path);
  if (RepImport(Path,"files",Unique,1) != 0)
	{
	printf("ERROR upload %ld Failed to import file into the repository.\n",GlobalUploadKey);
	printf("LOG upload %ld Failed to import %s from %s into files\n",
		GlobalUploadKey,Unique,Path);
	fflush(stdout);
	DBclose(DB);
	exit(6);
	}
  if (ForceGroup >= 0) { chown(Path,-1,ForceGroup); }
  if (Path != GlobalTempFile) free(Path);

  /* Now update the DB */
  /** Break out the sha1, md5, len components **/
  SHA1 = Unique;
  MD5 = Unique+41; /* 40 for sha1 + 1 for '.' */
  Len = Unique+41+33; /* 32 for md5 + 1 for '.' */
  /** Set the pfile **/
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD-1,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = %s;",
	SHA1,MD5,Len);
  if (DBaccess(DB,SQL) < 0)
	{
	printf("ERROR upload %ld Unable to select from the database\n",GlobalUploadKey);
	printf("LOG upload %ld Unable to select from the database: %s\n",GlobalUploadKey,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(7);
	}

  /* See if pfile needs to be added */
  if (DBdatasize(DB) <= 0)
	{
	/* Insert it */
	memset(SQL,'\0',MAXCMD);
	snprintf(SQL,MAXCMD-1,"INSERT INTO pfile (pfile_sha1, pfile_md5, pfile_size) VALUES ('%.40s','%.32s',%s);",
		SHA1,MD5,Len);
	if (DBaccess(DB,SQL) < 0)
		{
		printf("ERROR upload %ld Unable to select from the database\n",GlobalUploadKey);
		printf("LOG upload %ld Unable to select from the database: %s\n",GlobalUploadKey,SQL);
		fflush(stdout);
		DBclose(DB);
		exit(8);
		}
	DBaccess(DB,"SELECT currval('pfile_pfile_pk_seq');");
	}
  PfileKey = atol(DBgetvalue(DB,0,0));
  if (Debug) printf("pfile_pk = %ld\n",PfileKey);

  /* Upload the DB so the pfile is linked to the upload record */
  DBaccess(DB,"BEGIN;");
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD-1,"SELECT * FROM upload WHERE upload_pk=%ld FOR UPDATE;",GlobalUploadKey);
  DBaccess(DB,SQL);
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD-1,"UPDATE upload SET pfile_fk=%ld WHERE upload_pk=%ld;",
	PfileKey,GlobalUploadKey);
  if (Debug) printf("SQL=%s\n",SQL);
  if (DBaccess(DB,SQL) < 0)
	{
	printf("ERROR upload %ld Unable to update the database\n",GlobalUploadKey);
	printf("LOG upload %ld Unable to update the database: %s\n",GlobalUploadKey,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(9);
	}
  DBaccess(DB,"COMMIT;");

  /* Clean up */
  free(Sum);
} /* DBLoadGold() */


/*********************************************************
 TaintURL(): Given a URL string, taint-protect it.
 Returns: 1=tainted, 0=failed to taint
 *********************************************************/
int	TaintURL	(char *Sin, char *Sout, int SoutSize)
{
  int i;
  int si;
  memset(Sout,'\0',SoutSize);
  SoutSize--; /* always keep the EOL */
  for(i=0,si=0; (si<SoutSize) && (Sin[i] != '\0'); i++)
    {
    if (Sin[i] == '#') return(0);  /* end at the start of comment */
    if (!strchr("'`",Sin[i]) && !isspace(Sin[i])) Sout[si++] = Sin[i];
    else
	{
	if (si+3 >= SoutSize) return(0); /* no room */
	snprintf(Sout+si,4,"%%%02X",Sin[i]);
	si+=3;
	}
    }
  return(Sin[i]=='\0');
} /* TaintURL() */

/*********************************************************
 GetURL(): Do the wget.
 *********************************************************/
int	GetURL	(char *TempFile, char *URL)
{
  char CMD[MAXCMD];
  char TaintedURL[MAXCMD];
  char TmpLine[256];
  int rc;
  FILE *Fin;
#if 1
  char WgetArgs[]="--no-check-certificate --progress=dot";
#else
  /* wget < 1.10 does not support "--no-check-certificate" */
  char WgetArgs[]="--progress=dot";
#endif

  if (!TaintURL(URL,TaintedURL,MAXCMD))
	{
	printf("FATAL: Failed to parse the URL\n");
	printf("LOG: Failed to taint the URL '%s'\n",URL);
	fflush(stdout);
	DBclose(DB);
	exit(10);
	}

  memset(CMD,'\0',MAXCMD);
  /***
   Wget options:
   --progress=dot :: display a new line as it progresses.
   --no-check-certificate :: download HTTPS files even if the cert cannot
     be validated.  (Neal has many issues with SSL and does not view it
     as very secure.)  Without this, some caching proxies and web sites
     with old certs won't download.  Granted, in theory a bad cert should
     prevent downloads.  In reality, 99.9% of bad certs are because the
     admin did not notice that they expired and not because of a hijacking
     attempt.
   ***/

  if (TempFile && TempFile[0])
    {
    /* Delete the temp file if it exists */
    unlink(TempFile);
    snprintf(CMD,MAXCMD-1,". %s ; /usr/bin/wget %s -O '%s' '%s' 2>&1",
	PROXYFILE,WgetArgs,TempFile,TaintedURL);
    }
  else
    {
    snprintf(CMD,MAXCMD-1,". %s ; /usr/bin/wget %s '%s' 2>&1",
	PROXYFILE,WgetArgs,TaintedURL);
    }

  Fin = popen(CMD,"r");
  if (!Fin)
    {
    printf("FATAL upload %ld Failed to retrieve file.\n",GlobalUploadKey);
    printf("LOG upload %ld Failed to run command: %s\n",GlobalUploadKey,CMD);
    fflush(stdout);
    DBclose(DB);
    exit(11);
    }

  while(ReadLine(Fin,TmpLine,256) != -1)
	{
	/* Track if a line is read.
	   If this does not change after a minute, then heartbeat will
	   not display. This catches cases where wget hangs. */
	InitHeartbeat();
	}
  InitHeartbeat();

  rc = pclose(Fin);  /* rc is the exit status */

  if (WIFEXITED(rc) && (WEXITSTATUS(rc) != 0))
	{
	printf("ERROR upload %ld Download failed\n",GlobalUploadKey);
	printf("LOG upload %ld Download failed; Return code %d from: %s\n",GlobalUploadKey,WEXITSTATUS(rc),CMD);
	fflush(stdout);
	unlink(GlobalTempFile);
	DBclose(DB);
	exit(12);
	}

  if (WIFEXITED(rc) && WIFSIGNALED(rc))
	{
	printf("ERROR upload %ld Download killed by a signal\n",GlobalUploadKey);
	printf("LOG upload %ld Download killed by signal %d\n",GlobalUploadKey,WTERMSIG(rc));
	fflush(stdout);
	unlink(GlobalTempFile);
	DBclose(DB);
	exit(13);
	}

  if (WIFEXITED(rc) && WIFSIGNALED(rc))
	{
	printf("ERROR upload %ld Download killed by a signal\n",GlobalUploadKey);
	printf("LOG upload %ld Download killed by signal %d\n",GlobalUploadKey,WTERMSIG(rc));
	fflush(stdout);
	unlink(GlobalTempFile);
	DBclose(DB);
	exit(14);
	}

  if (TempFile && TempFile[0] && !IsFile(TempFile,1))
	{
	printf("ERROR upload %ld File %s not created from %s\n",GlobalUploadKey,TempFile,URL);
	printf("LOG upload %ld File not created from command: %s\n",GlobalUploadKey,CMD);
	fflush(stdout);
	DBclose(DB);
	exit(15);
	}

  printf("LOG upload %ld Downloaded %s to %s\n",GlobalUploadKey,URL,TempFile);
  return(0);
} /* GetURL() */

/**********************************************
 SetEnv(): Convert input pairs into globals.
 This functions taints the parameters as needed.
 **********************************************/
void    SetEnv  (char *S, char *TempFileDir)
{
  int SLen,GLen; /* lengths for S and global string */

  GlobalUploadKey = -1;
  memset(GlobalTempFile,'\0',MAXCMD);
  memset(GlobalURL,'\0',MAXCMD);
  if (!S) return;

  /* first value is the upload_pk */
  GlobalUploadKey = atol(S);
  while(S[0] && isdigit(S[0])) S++;
  while(S[0] && isspace(S[0])) S++; /* skip spaces */

#if 1
  /* second value is the temp file location */
  /** This will be removed when the jobqueue is changed. **/
  SLen=0;
  GLen=0;
  while((GLen < MAXCMD-4) && S[SLen] && !isspace(S[SLen]))
    {
    if ((S[SLen] == '\'') || isspace(S[SLen]) || !isprint(S[SLen]))
	{
	sprintf(GlobalTempFile+GLen,"%%%02x",(unsigned char)(S[SLen]));
	GLen += 3;
	}
    else GlobalTempFile[GLen++] = S[SLen];
    SLen++;
    }
  S+=SLen;
  while(S[0] && isspace(S[0])) S++; /* skip spaces */
#endif
  if (TempFileDir)
	{
	memset(GlobalTempFile,'\0',MAXCMD);
	snprintf(GlobalTempFile,MAXCMD-1,"%s/wget.%d",TempFileDir,getpid());
	}

  /* third value is the URL location -- taint any single-quotes */
  SLen=0;
  GLen=0;
  while((GLen < MAXCMD-4) && S[SLen])
    {
    if ((S[SLen] == '\'') || isspace(S[SLen]) || !isprint(S[SLen]))
	{
	sprintf(GlobalURL+GLen,"%%%02x",(unsigned char)(S[SLen]));
	GLen += 3;
	}
    else GlobalURL[GLen++] = S[SLen];
    SLen++;
    }

#if 1
  printf("  LOG upload %ld wget_agent globals loaded:\n  upload_pk = %ld\n  tmpfile=%s\n  URL=%s\n",GlobalUploadKey,
  	GlobalUploadKey,GlobalTempFile,GlobalURL);
#endif
} /* SetEnv() */

/***********************************************
 Usage():
 ***********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [options] [OBJ]\n",Name);
  printf("  -i  :: Initialize the DB connection then exit (nothing downloaded)\n");
  printf("  -g group :: Set the group on processed files (e.g., -g fossy).\n");
  printf("  -G  :: Do NOT copy the file to the gold repository.\n");
  printf("  -d dir :: directory to use file for temporary file storage\n");
  printf("  -k key :: upload key identifier (number)\n");
  printf("  OBJ :: if a URL is listed, then it is retrieved.\n");
  printf("         if a file is listed, then it used.\n");
  printf("         if OBJ and Key are provided, then it is inserted into\n");
  printf("         the DB and repository.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  int arg;
  char Parm[MAXCMD];
  char *TempFileDir=NULL;
  int c;
  int InitFlag=0;
  char *agent_desc = "Network downloader.  Uses wget(1).";

  memset(GlobalTempFile,'\0',MAXCMD);
  memset(GlobalURL,'\0',MAXCMD);
  GlobalUploadKey = -1;

  /* Process command-line */
  while((c = getopt(argc,argv,"d:Gg:ik:")) != -1)
    {
    switch(c)
	{
	case 'd':
		TempFileDir = optarg;
		break;
	case 'g':
		{
		struct group *SG;
		SG = getgrnam(optarg);
		if (SG) ForceGroup = SG->gr_gid;
		}
		break;
	case 'G':
		GlobalImportGold=0;
		break;
	case 'i':
		InitFlag=1;
		break;
	case 'k':
		GlobalUploadKey = atol(optarg);
		if (!GlobalTempFile[0])
			strcpy(GlobalTempFile,"wget.default_download");
		break;
	default:
		Usage(argv[0]);
		exit(-1);
	}
    }
  if (argc - optind > 1)
	{
	Usage(argv[0]);
	exit(-1);
	}

  /* Init */
  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(20);
	}

  /* When initializing the DB, don't do anything else */
  if (InitFlag)
	{
	DBclose(DB);
	return(0);
	}

  /* Get the Agent Key from the DB */
  GetAgentKey(DB, basename(argv[0]), GlobalUploadKey, SVN_REV, agent_desc);

  /* Run from the command-line (for testing) */
  InitHeartbeat();
  signal(SIGALRM,ShowHeartbeat);
  for(arg=optind; arg < argc; arg++)
    {
    memset(GlobalURL,'\0',sizeof(GlobalURL));
    strncpy(GlobalURL,argv[arg],sizeof(GlobalURL));
    /* If the file contains "://" then assume it is a URL.
       Else, assume it is a file. */
    if (Debug) printf("Command-line: %s\n",GlobalURL);
    if (strstr(GlobalURL,"://"))
      {
      alarm(60);
      Heartbeat(0);
      if (Debug) printf("It's a URL\n");
      if (GetURL(GlobalTempFile,GlobalURL) != 0)
	{
	printf("ERROR: Download of %s failed.\n",GlobalURL);
	fflush(stdout);
	DBclose(DB);
	exit(21);
	}
      InitHeartbeat();
      if (GlobalUploadKey != -1) { DBLoadGold(); }
      unlink(GlobalTempFile);
      alarm(0);
      }
    else /* must be a file */
      {
      if (Debug) printf("It's a file -- GlobalUploadKey = %ld\n",GlobalUploadKey);
      if (GlobalUploadKey != -1)
	{
	memcpy(GlobalTempFile,GlobalURL,MAXCMD);
	DBLoadGold();
	}
      }
    }

  /* Run from scheduler! */
  if (optind == argc)
    {
    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    InitHeartbeat();
    alarm(60);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      if (Parm[0] != '\0')
	{
    Heartbeat(0);
	/* 3 parameters: uploadpk downloadfile url */
	SetEnv(Parm,TempFileDir); /* set globals */
	if (GetURL(GlobalTempFile,GlobalURL) == 0)
		{
		DBLoadGold();
		unlink(GlobalTempFile);
		}
	else
		{
		printf("FATAL upload %ld File retrieval failed.\n",GlobalUploadKey);
		printf("LOG upload %ld File retrieval failed: uploadpk=%ld tempfile=%s URL=%s\n",GlobalUploadKey,GlobalUploadKey,GlobalTempFile,GlobalURL);
		fflush(stdout);
		DBclose(DB);
		exit(22);
		}
	printf("OK\n"); /* inform scheduler that we are ready */
	fflush(stdout);
	alarm(60);
	}
      }
    } /* if run from scheduler */

  /* Clean up */
  DBclose(DB);
  return(0);
} /* main() */

