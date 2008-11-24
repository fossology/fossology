/*******************************************************************
 Ununpack: The universal unpacker.

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

 **************
 This time, it's rewritten in C for speed and multithreading.
 *******************************************************************/

#include <stdlib.h>

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>

#include "ununpack.h"
#include "ununpack-disk.h"
#include "ununpack-iso.h"
#include "ununpack-ar.h"
#include "metahandle.h"
#include "libfossrepo.h"
#include "libfossdb.h"

#include <sys/timeb.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#else
char Version[]="0.9.9";
#endif


int Verbose=0;
int Quiet=0;
int DebugHeartbeat=0; /* Enable heartbeat and print the time for each */
int UnlinkSource=0;
int UnlinkAll=0;
int ForceContinue=0;
int ForceDuplicate=0;	/* when using db, should it process duplicates? */
int PruneFiles=0;
int SetContainerArtifact=1;	/* should initial container be an artifact? */
FILE *ListOutFile=NULL;

/* for the repository */
int UseRepository=0;
char REP_GOLD[16]="gold";
char REP_FILES[16]="files";

/*** For DB queries ***/
char *Pfile = NULL;
char *Pfile_Pk = NULL; /* PK for *Pfile */
char *Upload_Pk = NULL; /* PK for upload table */
void *DB=NULL;	/* the DB repository */
void *DBTREE=NULL;	/* second DB repository for uploadtree */
int Agent_pk=-1;	/* agent ID */
#define MAXSQL	4096
char SQL[MAXSQL];

enum BITS {
  BITS_REPLICA = 26, /* obsolete! Due to 2006-06-13 schema change */
  BITS_PROJECT = 27,
  BITS_ARTIFACT = 28,
  BITS_CONTAINER = 29
  };

/*** Global Stats (for summaries) ***/
long TotalItems=0;	/* number of records inserted */
int TotalFiles=0;
int TotalCompressedFiles=0;
int TotalDirectories=0;
int TotalContainers=0;
int TotalArtifacts=0;

/*********************************************************
 MyDBaccess(): MyDBaccess with debugging wrapper.
 *********************************************************/
int	MyDBaccess	(void *VDB, char *SQL)
{
  if (Verbose) fprintf(stderr,"SQL[%s]: %s\n",VDB==DBTREE ? "DBTREE" : "DB",SQL);
  return(DBaccess(VDB,SQL));
} /* MyDBaccess() */

/*********************************************************
 AlarmDisplay(): While running, periodically display the
 number of items inserted.
 *********************************************************/
void	AlarmDisplay	(int Sig)
{
  time_t Now;
  if (TotalItems > 0) printf("ItemsProcessed %ld",TotalItems);
  else printf("Heartbeat");
  if (DebugHeartbeat)
    {
    Now = time(NULL);
    printf(" %s",ctime(&Now)); /* ctime() includes \n */
    }
  else
    {
    printf("\n");
    }
  fflush(stdout);

  /* Reset counters */
  TotalItems=0;
  /* re-schedule itself */
  alarm(10);
} /* AlarmDisplay() */

/*********************************************************
 SafeExit(): Close down sockets and exit.
 *********************************************************/
void	SafeExit	(int rc)
{
  fflush(stdout);
  if (DB) DBclose(DB);
  if (DBTREE) DBclose(DBTREE);
  exit(rc);
} /* SafeExit() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = MyDBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='unpack' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'unpack' from the database table 'agent'\n");
	SafeExit(1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = MyDBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('unpack','unknown','Recursively extract files');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'unpack' to the database table 'agent'\n");
	SafeExit(2);
	}
      rc = MyDBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='unpack' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'unpack' from the database table 'agent'\n");
	SafeExit(3);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/*************************************************
 InitCmd(): Initialize the metahandler CMD table.
 This ensures that (1) every mimetype is loaded
 and (2) every mimetype has an DBindex.
 *************************************************/
void	InitCmd	()
{
  int i;
  int rc;

  /* clear existing indexes */
  for(i=0; CMD[i].Magic != NULL; i++)
    {
    CMD[i].DBindex = -1; /* invalid value */
    }

  if (!DB) return; /* DB must be open */

  /* Load them up! */
  for(i=0; CMD[i].Magic != NULL; i++)
    {
    if (CMD[i].Magic[0] == '\0') continue;
ReGetCmd:
    memset(SQL,'\0',MAXSQL);
    snprintf(SQL,MAXSQL,"SELECT mimetype_pk FROM mimetype WHERE mimetype_name = '%s';",CMD[i].Magic);
    rc = MyDBaccess(DB,SQL); /* SELECT */
    if (rc < 0)
	{
	printf("ERROR: SQL '%s'\n",SQL);
	SafeExit(4);
	}
    else if (DBdatasize(DB) > 0) /* if there is a value */
	{
	CMD[i].DBindex = atol(DBgetvalue(DB,0,0));
	}
    else /* No value, so add it */
	{
	memset(SQL,'\0',MAXSQL);
	snprintf(SQL,MAXSQL,"INSERT INTO mimetype (mimetype_name) VALUES ('%s');",CMD[i].Magic);
	rc = MyDBaccess(DB,SQL); /* INSERT INTO mimetype */
	if (rc < 0)
	  {
	  printf("ERROR: SQL '%s'\n",SQL);
	  SafeExit(5);
	  }
	else goto ReGetCmd;
	}
    }
} /* InitCmd() */

/*************************************************
 DBTaintString(): Make a string safe for DB inserts.
 *************************************************/
char *	DBTaintString	(char *S)
{
  char *NewS;
  int i,j;
  int NewLen;

  /* Count number of bytes for the new string */
  NewLen=1;
  for(i=0; S[i] != '\0'; i++)
	{
	if (S[i]=='\'')	NewLen += 4;
	else if (!isprint(S[i])) NewLen += 4;
	else if (S[i]=='\n') NewLen += 2;
	else if (S[i]=='\t') NewLen += 2;
	else NewLen++;
	}

  NewS = (char *)calloc(NewLen,sizeof(char));
  if (!NewS)
	{
	printf("ERROR: Unable to allocate %d bytes for string.\n",NewLen);
	SafeExit(6);
	}
  j=0;
  for(i=0; S[i] != '\0'; i++)
    {
    if (S[i]=='\'')
      { NewS[j++]='\\'; NewS[j++]='x'; NewS[j++]='2'; NewS[j++]='7'; }
    else if (!isprint(S[i]))
      { sprintf(NewS+j,"\\x%02x",(unsigned char)(S[i])); j+=4; }
    else if (S[i]=='\n')
      { sprintf(NewS+j,"\\n"); j+=2; }
    else if (S[i]=='\t')
      { sprintf(NewS+j,"\\t"); j+=2; }
    else { NewS[j++] = S[i]; }
    }
  return(NewS);
} /* DBTaintString() */

/*************************************************
 TaintString(): Protect strings intelligently.
 Prevents filenames containing ' or % from screwing
 up system() and snprintf().  Even supports a "%s".
 NOTE: %s is assumed to be in single quotes!
 Returns: 0 on success, 1 on overflow.
 *************************************************/
int	TaintString	(char *Dest, int DestLen,
			 char *Src, int ProtectQuotes, char *Replace)
{
  int i,d;
  char Temp[FILENAME_MAX];

  memset(Dest,'\0',DestLen);
  i=0;
  d=0;
  while((Src[i] != '\0') && (d < DestLen))
    {
    /* save */
    if (ProtectQuotes && (Src[i]=='\''))
      {
      if (d+4 >= DestLen) return(1);
      strcpy(Dest+d,"'\\''"); /* unquote, raw quote, requote (for shells) */
      d+=4;
      i++;
      }
#if 0
    else if (!ProtectQuotes && strchr("\"'`\\",Src[i]))
      {
      if (d+2 >= DestLen) return(1);
      Dest[d] = '\\'; d++;
      Dest[d] = Src[i]; d++;
      i++;
      }
#endif
    else if (Replace && (Src[i]=='%') && (Src[i+1]=='s'))
      {
      TaintString(Temp,sizeof(Temp),Replace,1,NULL);
      if (d+strlen(Temp) >= DestLen) return(1);
      strcpy(Dest+d,Temp);
      d = strlen(Dest);
      i += 2;
      }
    else
      {
      Dest[d] = Src[i];
      d++;
      i++;
      }
    }
  return(0);
} /* TaintString() */

/***************************************************
 Prune(): Given a filename and its stat, prune it!
 - Remove anything that is not a regular file or directory
 - Remove files when hard-link count > 1 (duplicate search)
 - Remove zero-length files
 Returns 1=pruned, 0=no change.
 ***************************************************/
inline int	Prune	(char *Fname, stat_t Stat)
{
  if (!Fname || (Fname[0]=='\0')) return(1);  /* not a good name */
  /* check file type */
  if (S_ISLNK(Stat.st_mode) || S_ISCHR(Stat.st_mode) ||
      S_ISBLK(Stat.st_mode) || S_ISFIFO(Stat.st_mode) ||
      S_ISSOCK(Stat.st_mode))
	{
	unlink(Fname);
	return(1);
	}
  /* check hard-link count */
  if (S_ISREG(Stat.st_mode) && (Stat.st_nlink > 1))
	{
	unlink(Fname);
	return(1);
	}
  /* check zero-length files */
  if (S_ISREG(Stat.st_mode) && (Stat.st_size == 0))
	{
	unlink(Fname);
	return(1);
	}
  return(0);
} /* Prune() */

/***************************************************
 MkDirs(): Same as command-line "mkdir -p".
 Returns 0 on success, 1 on failure.
 ***************************************************/
inline int	MkDirs	(char *Fname)
{
  char Dir[FILENAME_MAX+1];
  int i;
  int rc=0;
  struct stat Status;

  memset(Dir,'\0',sizeof(Dir));
  strcpy(Dir,Fname);
  for(i=1; Dir[i] != '\0'; i++)
    {
    if (Dir[i] == '/')
	{
	Dir[i]='\0';
	/* Only mkdir if it does not exist */
	if (stat(Dir,&Status) == 0)
	  {
	  if (!S_ISDIR(Status.st_mode))
	    {
	    fprintf(stderr,"FATAL: '%s' is not a directory.\n",Dir);
	    return(1);
	    }
	  }
	else /* else, it does not exist */
	  {
	  rc=mkdir(Dir,0770); /* create this path segment */
	  if (rc && (errno == EEXIST)) rc=0;
	  if (rc)
	    {
	    perror("FATAL: ununpack");
	    fprintf(stderr,"FATAL: 'mkdir %s' failed with rc=%d\n",Dir,rc);
	    SafeExit(7);
	    }
	  } /* else */
	Dir[i]='/';
	}
    }
  rc = mkdir(Dir,0770);	/* create whatever is left */
  if (rc && (errno == EEXIST)) rc=0;
  if (rc)
	{
	perror("FATAL: ununpack");
	fprintf(stderr,"FATAL: 'mkdir %s' failed with rc=%d\n",Dir,rc);
	SafeExit(8);
	}
  return(rc);
} /* MkDirs() */

/***************************************************
 MkDir(): Smart mkdir.
 If mkdir fails, then try running MkDirs.
 Returns 0 on success, 1 on failure.
 ***************************************************/
inline int	MkDir	(char *Fname)
{
  if (mkdir(Fname,0770))
    {
    if (errno == EEXIST) return(0); /* failed because it exists is ok */
    return(MkDirs(Fname));
    }
  return(0);
} /* MkDir() */

/***************************************************
 IsDir(): Given a filename, is it a directory?
 Returns 1=yes, 0=no.
 (This is used by ISO and Disk extraction...)
 ***************************************************/
inline int	IsDir	(char *Fname)
{
  stat_t Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  rc = lstat64(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISDIR(Stat.st_mode));
} /* IsDir() */

/***************************************************
 IsFile(): Given a filename, is it a file?
 Link: should it follow symbolic links?
 Returns 1=yes, 0=no.
 (This is used by Disk extraction for getting a unique name...)
 ***************************************************/
inline int	IsFile	(char *Fname, int Link)
{
  stat_t Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  if (Link) rc = stat64(Fname,&Stat);
  else rc = lstat64(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISREG(Stat.st_mode));
} /* IsFile() */

/***************************************************
 IsExe(): Check if the executable exists.
 (Like the command-line "which" but without returning
 the path.)
 This should only be used on relative path executables.
 Returns: 1 if exists, 0 if does not exist.
 ***************************************************/
int	IsExe	(char *Exe, int Quiet)
{
  char *Path;
  int i,j;
  char TestCmd[FILENAME_MAX];

  Path = getenv("PATH");
  if (!Path) return(0);	/* nope! */

  memset(TestCmd,'\0',sizeof(TestCmd));
  j=0;
  for(i=0; (j<FILENAME_MAX-1) && (Path[i] != '\0'); i++)
    {
    if (Path[i]==':')
	{
	if ((j>0) && (TestCmd[j-1] != '/')) strcat(TestCmd,"/");
	strcat(TestCmd,Exe);
	if (IsFile(TestCmd,1))	return(1); /* found it! */
	/* missed */
	memset(TestCmd,'\0',sizeof(TestCmd));
	j=0;
	}
    else
	{
	TestCmd[j]=Path[i];
	j++;
	}
    }

  /* check last path element */
  if (j>0)
    {
    if (TestCmd[j-1] != '/') strcat(TestCmd,"/");
    strcat(TestCmd,Exe);
    if (IsFile(TestCmd,1))	return(1); /* found it! */
    }
  if (!Quiet) fprintf(stderr,"  %s :: not found in $PATH\n",Exe);
  return(0); /* not in path */
} /* IsExe() */

/***************************************************
 CopyFile(): Copy a file.
 For speed: mmap and save.
 Returns: 0 if copy worked, 1 if failed.
 ***************************************************/
int	CopyFile	(char *Src, char *Dst)
{
  int Fin, Fout;
  unsigned char * Mmap;
  int LenIn, LenOut, Wrote;
  stat_t Stat;
  int rc=0;
  char *Slash;

  if (lstat64(Src,&Stat) == -1) return(1);
  LenIn = Stat.st_size;
  if (!S_ISREG(Stat.st_mode))	return(1);

  Fin = open(Src,O_RDONLY);
  if (Fin == -1)
	{
	fprintf(stderr,"FATAL: Unable to open source '%s'\n",Src);
	return(1);
	}

  /* Make sure the directory exists for copying */
  Slash = strrchr(Dst,'/');
  if (Slash && (Slash != Dst))
    {
    Slash[0]='\0';
    MkDir(Dst);
    Slash[0]='/';
    }

  Fout = open(Dst,O_WRONLY|O_CREAT|O_TRUNC,Stat.st_mode);
  if (Fout == -1)
	{
	fprintf(stderr,"FATAL: Unable to open target '%s'\n",Dst);
	close(Fin);
	return(1);
	}

  /* load the source file */
  Mmap = mmap(0,LenIn,PROT_READ,MAP_PRIVATE,Fin,0);
  if (Mmap == NULL)
	{
	printf("FATAL pfile %s Unable to process file.\n",Pfile_Pk);
	printf("LOG pfile %s Mmap failed during copy.\n",Pfile_Pk);
	rc=1;
	goto CopyFileEnd;
	}

  /* write file at maximum speed */
  LenOut=0;
  Wrote=0;
  while((LenOut < LenIn) && (Wrote >= 0))
    {
    Wrote = write(Fout,Mmap+LenOut,LenIn-LenOut);
    LenOut += Wrote;
    }

  /* clean up */
  munmap(Mmap,LenIn);
CopyFileEnd:
  close(Fout);
  close(Fin);
  return(rc);
} /* CopyFile() */

/***************************************************
 ReadLine(): Given a stream, read in one line.
 Return number of bytes read, or -1 on EOF.
 ***************************************************/
int	ReadLine	(FILE *Fin, char *Line, int MaxLine)
{
  int C='@';
  int i=0;      /* index */
  memset(Line,0,MaxLine);
  if (feof(Fin))        return(-1);
  while(!feof(Fin) && (i < MaxLine-1) && (C != '\n') && (C>0))
    {
    C=fgetc(Fin);
    if ((C>0) && (C!='\n'))
      {
      Line[i]=C;
      i++;
      }
    }
  return(i);
} /* ReadLine() */


/***************************************************************************/
/***************************************************************************/
/*** Spawning ***/
/***************************************************************************/
/***************************************************************************/

/* ParentInfo relates to the command being executed.
   It is common information needed by Traverse() and stored in CommandInfo
   and Queue structures. */
struct ParentInfo
  {
  int Cmd;      /* index into command table used to run this */
  time_t StartTime;     /* time when command started */
  time_t EndTime;       /* time when command ended */
  int ChildRecurseArtifact; /* child is an artifact -- don't log to XML */
  long uploadtree_pk;	/* if DB is enabled, this is the parent */
  };
typedef struct ParentInfo ParentInfo;

struct unpackqueue
  {
  int ChildPid; /* set to 0 if this record is not in use */
  char ChildRecurse[FILENAME_MAX+1]; /* file (or directory) to recurse on */
  int ChildStatus;	/* return code from child */
  int ChildCorrupt;	/* return status from child */
  int ChildEnd;	/* flag: 0=recurse, 1=don't recurse */
  int ChildHasChild;	/* is the child likely to have children? */
  stat_t ChildStat;
  ParentInfo PI;
  };
typedef struct unpackqueue unpackqueue;
#define MAXCHILD        4096
unpackqueue Queue[MAXCHILD+1];    /* manage children */
int MaxThread=1; /* value between 1 and MAXCHILD */
int Thread=0;

/**********************************************
 ParentWait(): Wait for a child.  Sets child status.
 Returns the queue record, or -1 if no more children.
 **********************************************/
int     ParentWait      ()
{
  int i;
  int Pid;
  int Status;

  Pid = wait(&Status);
  if (Pid <= 0) return(-1);  /* no pending children, or call failed */

  /* find the child! */
  for(i=0; (i<MAXCHILD) && (Queue[i].ChildPid != Pid); i++)        ;
  if (Queue[i].ChildPid != Pid)
	{
	/* child not found */
	return(-1);
	}

  /* check if the child had an error */
  if (!WIFEXITED(Status))
	{
	if (!ForceContinue)
	  {
	  printf("FATAL: Child had unnatural death\n");
	  SafeExit(9);
	  }
	Queue[i].ChildCorrupt=1;
	Status = -1;
	}
  else Status = WEXITSTATUS(Status);
  if (Status != 0)
	{
	if (!ForceContinue)
	  {
	  printf("FATAL: Child had non-zero status: %d\n",Status);
	  printf("FATAL: Child was to recurse on %s\n",Queue[i].ChildRecurse);
	  SafeExit(10);
	  }
	Queue[i].ChildCorrupt=1;
	}

  /* Finish record */
  Queue[i].ChildStatus = Status;
  Queue[i].ChildPid = 0;
  Queue[i].PI.EndTime = time(NULL);
  return(i);
} /* ParentWait() */

/***************************************************************************/
/***************************************************************************/
/*** Command Processing ***/
/***************************************************************************/
/***************************************************************************/

/*************************************************
 CheckCommands(): Make sure all commands are usable.
 *************************************************/
void	CheckCommands	(int Show)
{
  int i;
  int rc;

  /* Check for CMD_PACK and CMD_ARC tools */
  for(i=0; CMD[i].Cmd != NULL; i++)
	{
	if (CMD[i].Cmd[0] == '\0')	continue; /* no command to check */
	switch(CMD[i].Type)
	  {
	  case CMD_PACK:
	  case CMD_RPM:
	  case CMD_ARC:
	  case CMD_AR:
	  case CMD_PARTITION:
		CMD[i].Status = IsExe(CMD[i].Cmd,Quiet);
		break;
	  default:
	  	; /* do nothing */
	  }
	}

  /* Check for CMD_ISO */
  rc = ( IsExe("isoinfo",Quiet) && IsExe("grep",Quiet) );
  for(i=0; CMD[i].Cmd != NULL; i++)
    {
    if (CMD[i].Type == CMD_ISO) CMD[i].Status = rc;
    }

  /* Check for CMD_DISK */
  rc = ( IsExe("icat",Quiet) && IsExe("fls",Quiet) );
  for(i=0; CMD[i].Cmd != NULL; i++)
    {
    if (CMD[i].Type == CMD_DISK) CMD[i].Status = rc;
    }
} /* CheckCommands() */

/*************************************************
 RunCommand(): try a command and return command code.
 Command becomes:
    Cmd CmdPre 'File' CmdPost Out
    If there is a %s, then that becomes Where.
 Returns -1 if command could not run.
 *************************************************/
int	RunCommand	(char *Cmd, char *CmdPre, char *File, char *CmdPost,
			 char *Out, char *Where)
{
  char Cmd1[FILENAME_MAX * 3];
  char CWD[FILENAME_MAX];
  int rc;
  char TempPre[FILENAME_MAX];
  char TempFile[FILENAME_MAX];
  char TempCwd[FILENAME_MAX];
  char TempPost[FILENAME_MAX];

  if (!Cmd) return(0); /* nothing to do */

  if (!Quiet)
    {
    if (Where && Verbose && Out)
	fprintf(stderr,"Extracting %s: %s > %s\n",Cmd,File,Out);
    else if (Where) fprintf(stderr,"Extracting %s in %s: %s\n",Cmd,Where,File);
    else fprintf(stderr,"Testing %s: %s\n",Cmd,File);
    }

  if (getcwd(CWD,sizeof(CWD)) == NULL)
	{
	fprintf(stderr,"FATAL: directory name longer than %d characters\n",(int)sizeof(CWD));
	return(-1);
	}
  if (Verbose > 1) printf("CWD: %s\n",CWD);
  if ((Where != NULL) && (Where[0] != '\0'))
	{
	if (chdir(Where) != 0)
		{
		MkDir(Where);
		if (chdir(Where) != 0)
			{
			fprintf(stderr,"FATAL: Unable to access directory '%s'\n",Where);
			return(-1);
			}
		}
	if (Verbose > 1) printf("CWD: %s\n",Where);
	}

  /* CMD: Cmd CmdPre 'CWD/File' CmdPost */
  /* CmdPre and CmdPost may contain a "%s" */
  memset(Cmd1,'\0',sizeof(Cmd1));
  if (TaintString(TempPre,FILENAME_MAX,CmdPre,0,Out) ||
      TaintString(TempFile,FILENAME_MAX,File,1,Out) ||
      TaintString(TempPost,FILENAME_MAX,CmdPost,0,Out))
	{
	return(-1);
	}
  if (File[0] != '/')
	{
	TaintString(TempCwd,FILENAME_MAX,CWD,1,Out);
	snprintf(Cmd1,sizeof(Cmd1),"%s %s '%s/%s' %s",
		Cmd,TempPre,TempCwd,TempFile,TempPost);
	}
  else
	{
	snprintf(Cmd1,sizeof(Cmd1),"%s %s '%s' %s",
		Cmd,TempPre,TempFile,TempPost);
	}
  rc = system(Cmd1);
  if (WIFSIGNALED(rc))
	{
	printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd1);
	SafeExit(11);
	}
  if (WIFEXITED(rc)) rc = WEXITSTATUS(rc);
  else rc=-1;
  if (Verbose) printf("CMD: in %s -- %s ; rc=%d\n",Where,Cmd1,rc);

  chdir(CWD);
  if (Verbose > 1) printf("CWD: %s\n",CWD);
  return(rc);
} /* RunCommand() */

/***************************************************************************/
/***************************************************************************/
/*** Magic Processing ***/
/***************************************************************************/
/***************************************************************************/

magic_t MagicCookie;

/***************************************************
 MagicMatch(): Does a magic string match a real string?
 There is a problem with magic_load: It sets default values
 AND it loads my own magic file.
 There does not appear to be ANY WAY to remove the default values.
 As a result:
   - "tar" uses the default, rather than the one I load!!!
   - magic_file returns "," and ";" attributes!!!
 This function checks to see if it returns the string I want, rather
 than all of the bogus crap from the default settings.
 Returns: 1 = match, 0 = not match
 ***************************************************/
int	MagicMatch	(char *Want, char *Have)
{
  int i;

  i=0;
  while((Want[i] != '\0') && (Have[i] != '\0') && (Want[i]==Have[i]))
	{
	i++;
	}

  if (Want[i]=='\0')
    {
    if (Have[i] == '\0') return(1);
    if (Have[i] == ',') return(1);
    if (Have[i] == ';') return(1);
    if (Have[i] == ' ') return(1);
    }
  return(0);
} /* MagicMatch() */

/***************************************************
 FindCmd(): Given a file name, determine the type of
 extraction command.  This uses Magic.
 Returns index to command-type, or -1 on error.
 ***************************************************/
int	FindCmd	(char *Filename)
{
  char *Type;
  char Static[256];
  int Match;
  int i;

  Type = (char *)magic_file(MagicCookie,Filename);
  if (Type == NULL) return(-1);

  /* sometimes Magic is wrong... */
  if (MagicMatch("application/x-exe",Type) ||
      MagicMatch("application/x-shellscript",Type))
	{
	int rc;
	rc = RunCommand("unzip","-q -l",Filename,">/dev/null 2>&1",NULL,NULL);
	if ((rc==0) || (rc==1) || (rc==2) || (rc==51))
	  {
	  memset(Static,0,sizeof(Static));
	  strcpy(Static,"application/x-zip");
	  Type=Static;
	  }
	else
	  {
	  rc = RunCommand("cabextract","-l",Filename,">/dev/null 2>&1",NULL,NULL);
	  if (rc==0)
	    {
	    memset(Static,0,sizeof(Static));
	    strcpy(Static,"application/x-cab");
	    Type=Static;
	    }
	  }
	} /* if was x-exe */
  else if (MagicMatch("application/x-tar",Type))
	{
	if (RunCommand("tar","-tf",Filename,">/dev/null 2>&1",NULL,NULL) != 0)
		return(-1); /* bad tar! (Yes, they do happen) */
	} /* if was x-tar */

  /* determine command for file */
  Match=-1;
  for(i=0; (CMD[i].Cmd != NULL) && (Match == -1); i++)
      {
      if (CMD[i].Status == 0) continue; /* cannot check */
      if (CMD[i].Type == CMD_DEFAULT)
      	{ 
	Match=i; /* done! */
	}
      if (!MagicMatch(CMD[i].Magic,Type)) continue; /* not a match */
      Match=i;
      }

  if (Verbose > 0)
      {
      /* no match */
      if (Match == -1) printf("MISS: Type=%s  %s\n",Type,Filename);
      else printf("MATCH: Type=%d  %s %s %s %s\n",CMD[Match].Type,CMD[Match].Cmd,CMD[Match].CmdPre,Filename,CMD[Match].CmdPost);
      }

  return(Match);
} /* FindCmd() */

/***************************************************************************/
/***************************************************************************/
/*** File Processing ***/
/***************************************************************************/
/***************************************************************************/

/* readdir() can be overwritten by subsequent entries.
   To resolve this, read in all files first, and THEN process them. */
struct dirlist
  {
  char *Name;
  struct dirlist *Next;
  };
typedef struct dirlist dirlist;

/***************************************************
 FreeDirList(): Free a list of files in a directory.
 ***************************************************/
void	FreeDirList	(dirlist *DL)
{
  dirlist *d;
  /* free records */
  while(DL)
    {
    d=DL;  /* grab the head */
    DL=DL->Next; /* increment new head */
    /* free old head */
    if (d->Name) free(d->Name);
    free(d);
    }
} /* FreeDirList() */

/***************************************************
 MakeDirList(): Allocate a list of files in a directory.
 ***************************************************/
dirlist *	MakeDirList	(char *Fullname)
{
  dirlist *dlist=NULL, *dhead=NULL;
  DIR *Dir;
  struct dirent *Entry;

  /* no effort is made to sort since all records need to be processed anyway */
  /* Current order is "reverse inode order" */
  Dir = opendir(Fullname);
  if (Dir == NULL)	return(NULL);

  Entry = readdir(Dir);
  while(Entry != NULL)
	{
	if (!strcmp(Entry->d_name,".")) goto skip;
	if (!strcmp(Entry->d_name,"..")) goto skip;
	dhead = (dirlist *)malloc(sizeof(dirlist));
	if (!dhead)
	  {
	  printf("FATAL: Failed to allocate dirlist memory\n");
	  SafeExit(12);
	  }
	dhead->Name = (char *)malloc(strlen(Entry->d_name)+1);
	if (!dhead->Name)
	  {
	  printf("FATAL: Failed to allocate dirlist.Name memory\n");
	  SafeExit(13);
	  }
	memset(dhead->Name,'\0',strlen(Entry->d_name)+1);
	strcpy(dhead->Name,Entry->d_name);
	/* add record to the list */
	dhead->Next = dlist;
	dlist = dhead;
#if 0
	{
	/* bubble-sort name -- head is out of sequence */
	/** This is SLOW! Only use for debugging! **/
	char *Name;
	dhead = dlist;
	while(dhead->Next && (strcmp(dhead->Name,dhead->Next->Name) > 0))
	  {
	  /* swap names */
	  Name = dhead->Name;
	  dhead->Name = dhead->Next->Name;
	  dhead->Next->Name = Name;
	  dhead = dhead->Next;
	  }
	}
#endif

skip:
	Entry = readdir(Dir);
	}
  closedir(Dir);

#if 0
  /* debug: List the directory */
  printf("Directory: %s\n",Fullname);
  for(dhead=dlist; dhead; dhead=dhead->Next)
    {
    printf("  %s\n",dhead->Name);
    }
#endif

  return(dlist);
} /* MakeDirList() */

/***************************************************
 SetDir(): Set a destination directory name.
 Smain = main extraction directory (may be null)
 Sfile = filename
 This will concatenate Smain and Sfile, but remove
 and terminating filename.
 ***************************************************/
void	SetDir	(char *Dest, int DestLen, char *Smain, char *Sfile)
{
  int i;

  memset(Dest,'\0',DestLen);
  if (Smain)
	{
	strcpy(Dest,Smain);
	/* remove absolute path (stay in destination) */
	if (Sfile && (Sfile[0]=='/')) Sfile++;
	/* skip "../" */
	/** NOTE: Someone that embeds "../" within the path can still
	    climb out! **/
	i=1;
	while(i && Sfile)
	  {
	  i=0;
	  if (!memcmp(Sfile,"../",3)) { Sfile+=3; i=1; }
	  else if (!memcmp(Sfile,"./",2)) { Sfile+=2; i=1; }
	  }
	while(Sfile && !memcmp(Sfile,"../",3)) Sfile+=3;
	}

  if ((strlen(Dest) > 0) && (Last(Smain) != '/') && (Sfile[0] != '/'))
	strcat(Dest,"/");
  if (Sfile) strcat(Dest,Sfile);
  /* remove terminating file */
  for(i=strlen(Dest)-1; (i>=0) && (Dest[i] != '/'); i--)
	{
	Dest[i]='\0';
	}
} /* SetDir() */

/************************************
 ContainerInfo: stucture for storing
 information about a particular file.
 ************************************/
struct ContainerInfo
  {
  char Source[FILENAME_MAX];  /* Full source filename */
  char Partdir[FILENAME_MAX];  /* directory name */
  char Partname[FILENAME_MAX];  /* filename without directory */
  char PartnameNew[FILENAME_MAX];  /* new filename without directory */
  int TopContainer;	/* flag: 1=yes (so Stat is meaningless), 0=no */
  int HasChild;	/* Can this a container have children? (include directories) */
  int Pruned;	/* no longer exists due to pruning */
  int Corrupt;	/* is this container/file known to be corrupted? */
  stat_t Stat;
  ParentInfo PI;
  int Artifact; /* this container is an artifact -- don't log to XML */
  int IsDir; /* this container is a directory */
  int IsCompressed; /* this container is compressed */
  long uploadtree_pk;	/* uploadtree of this item */
  long pfile_pk;	/* pfile of this item */
  long ufile_mode;	/* ufile_mode of this item */
  };
typedef struct ContainerInfo ContainerInfo;

/***************************************************
 DebugContainerInfo(): Check the structure.
 ***************************************************/
void	DebugContainerInfo	(ContainerInfo *CI)
{
  printf("Container:\n");
  printf("  Source: %s\n",CI->Source); 
  printf("  Partdir: %s\n",CI->Partdir); 
  printf("  Partname: %s\n",CI->Partname); 
  printf("  PartnameNew: %s\n",CI->PartnameNew); 
  printf("  TopContainer: %d\n",CI->TopContainer);
  printf("  HasChild: %d\n",CI->HasChild);
  printf("  Pruned: %d\n",CI->Pruned);
  printf("  Corrupt: %d\n",CI->Corrupt);
  printf("  Artifact: %d\n",CI->Artifact);
  printf("  IsDir: %d\n",CI->IsDir);
  printf("  IsCompressed: %d\n",CI->IsCompressed);
  printf("  uploadtree_pk: %ld\n",CI->uploadtree_pk);
  printf("  pfile_pk: %ld\n",CI->pfile_pk);
  printf("  ufile_mode: %ld\n",CI->ufile_mode);
  printf("  Parent Cmd: %d\n",CI->PI.Cmd);
  printf("  Parent ChildRecurseArtifact: %d\n",CI->PI.ChildRecurseArtifact);
  printf("  Parent uploadtree_pk: %ld\n",CI->PI.uploadtree_pk);
} /* DebugContainerInfo() */

/***************************************************
 DBInsertPfile(): Insert a Pfile record.
 Sets the pfile_pk in CI.
 Returns: 1 if record exists, 0 if record does not exist.
 ***************************************************/
int	DBInsertPfile	(ContainerInfo *CI, char *Fuid)
{
  int rc;
  char *Val; /* string result from SQL query */

  /* idiot checking */
  if (!Fuid || (Fuid[0] == '\0')) return(1);

  /* Check if the pfile exists */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pfile_pk,pfile_mimetypefk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
	Fuid,Fuid+41,Fuid+74);
  rc=MyDBaccess(DB,SQL); /* SELECT */

  if (rc < 0)
	{
	printf("FATAL: Database access error.\n");
	printf("LOG: Database access error in ununpack: %s\n",SQL);
	SafeExit(14);
	}

  /* add it if it was not found */
  if (DBdatasize(DB) <= 0)
    {
    /* blindly insert to pfile table in database (don't care about dups) */
    /** If TWO ununpacks are running at the same time, they could both
        create the same pfile at the same time.  Ignore the dup constraint. */
    memset(SQL,'\0',MAXSQL);
    if (CMD[CI->PI.Cmd].DBindex > 0)
	{
	snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size,pfile_mimetypefk) VALUES ('%.40s','%.32s','%s','%ld');",
	Fuid,Fuid+41,Fuid+74,CMD[CI->PI.Cmd].DBindex);
	}
    else
	{
	snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ('%.40s','%.32s','%s');",
	Fuid,Fuid+41,Fuid+74);
	}
    rc=MyDBaccess(DB,SQL); /* INSERT INTO pfile */
#if 0
    if (rc >= 0) TotalItems++;
#endif

    /* Now find the pfile_pk.  Since it might be a dup, we cannot rely
       on currval(). */
    memset(SQL,'\0',MAXSQL);
    snprintf(SQL,MAXSQL,"SELECT pfile_pk,pfile_mimetypefk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
	Fuid,Fuid+41,Fuid+74);
    rc=MyDBaccess(DB,SQL); /* SELECT */
    if (rc < 0)
	{
	printf("FATAL: Database access error.\n");
	printf("LOG: Database access error in ununpack: %s\n",SQL);
	SafeExit(15);
	}
    }

  /* Now *DB contains the pfile_pk information */
  Val = DBgetvalue(DB,0,0);
  if (Val)
	{
	CI->pfile_pk = atol(Val);
	if (Verbose) fprintf(stderr,"pfile_pk = %ld\n",CI->pfile_pk);
	/* For backwards compatibility... Do we need to update the mimetype? */
	if ((CMD[CI->PI.Cmd].DBindex > 0) &&
	    (atol(DBgetvalue(DB,0,1)) != CMD[CI->PI.Cmd].DBindex))
	    {
	    MyDBaccess(DB,"BEGIN;");
	    memset(SQL,'\0',MAXSQL);
	    snprintf(SQL,MAXSQL,"SELECT * FROM pfile WHERE pfile_pk = '%ld' FOR UPDATE;", CI->pfile_pk);
	    MyDBaccess(DB,SQL); /* lock pfile */
	    memset(SQL,'\0',MAXSQL);
	    snprintf(SQL,MAXSQL,"UPDATE pfile SET pfile_mimetypefk = '%ld' WHERE pfile_pk = '%ld';",
		CMD[CI->PI.Cmd].DBindex, CI->pfile_pk);
	    rc=MyDBaccess(DB,SQL); /* UPDATE pfile */
	    if (rc < 0) fprintf(stderr,"ERROR: SQL '%s'\n",SQL);
	    MyDBaccess(DB,"COMMIT;");
	    }
	}
    else
	{
	CI->pfile_pk = -1;
	return(0);
	}

  return(1);
} /* DBInsertPfile() */

/***************************************************
 DBInsertUploadTree(): Insert an UploadTree record.
 If the tree is a duplicate, then we need to replicate
 all of the uploadtree records for the tree.
 This uses Upload_Pk.
 Returns: 1 if tree exists for some other project (duplicate)
 and 0 if tree does not exist.
 ***************************************************/
int	DBInsertUploadTree	(ContainerInfo *CI, int Mask)
{
  char UfileName[1024];
  int rc;

  if (!Upload_Pk) return(-1); /* should never happen */
  // printf("=========== BEFORE ==========\n"); DebugContainerInfo(CI);

  /* Find record's mode */
  CI->ufile_mode = CI->Stat.st_mode & Mask;
  if (!CI->TopContainer && CI->Artifact) CI->ufile_mode |= (1 << BITS_ARTIFACT);
  if (CI->HasChild) CI->ufile_mode |= (1 << BITS_CONTAINER);

  /* Find record's name */
  memset(UfileName,'\0',sizeof(UfileName));
  if (CI->TopContainer)
	{
	char *ufile_name;
	snprintf(UfileName,sizeof(UfileName),"SELECT upload_filename FROM upload WHERE upload_pk = %s;",Upload_Pk);
	MyDBaccess(DB,UfileName);
	memset(UfileName,'\0',sizeof(UfileName));
	ufile_name = DBgetvalue(DB,0,0);
	if (strchr(ufile_name,'/')) ufile_name = strrchr(ufile_name,'/')+1;
	strncpy(UfileName,ufile_name,sizeof(UfileName)-1);
	}
  else if (CI->Artifact)
	{
	int Len;
	Len = strlen(CI->Partname);
	/* determine type of artifact */
	if ((Len > 4) && !strcmp(CI->Partname+Len-4,".dir"))
		strcpy(UfileName,"artifact.dir");
	else if ((Len > 9) && !strcmp(CI->Partname+Len-9,".unpacked"))
		strcpy(UfileName,"artifact.unpacked");
	else if ((Len > 5) && !strcmp(CI->Partname+Len-5,".meta"))
		strcpy(UfileName,"artifact.meta");
	else /* Don't know what it is */
		strcpy(UfileName,"artifact");
	}
  else /* not an artifact -- use the name */
	{
	char *S;
	S = DBTaintString(CI->Partname);
	strncpy(UfileName,S,sizeof(UfileName));
	free(S);
	}

  /* Get the parent ID */
  /* Two cases -- depending on if the parent exists */
  memset(SQL,'\0',MAXSQL);
  if (CI->PI.uploadtree_pk > 0) /* This is a child */
    {
    /* Prepare to insert child */
    snprintf(SQL,MAXSQL,"INSERT INTO uploadtree (parent,pfile_fk,ufile_mode,ufile_name,upload_fk) VALUES (%ld,%ld,%ld,'%s',%s);",
	CI->PI.uploadtree_pk, CI->pfile_pk, CI->ufile_mode,
	UfileName, Upload_Pk);
    rc=MyDBaccess(DBTREE,SQL); /* INSERT INTO uploadtree */
    }
  else /* No parent!  This is the first upload! */
    {
    snprintf(SQL,MAXSQL,"INSERT INTO uploadtree (upload_fk,pfile_fk,ufile_mode,ufile_name) VALUES (%s,%ld,%ld,'%s');",
	Upload_Pk, CI->pfile_pk, CI->ufile_mode, UfileName);
    rc=MyDBaccess(DBTREE,SQL); /* INSERT INTO uploadtree */
    }
  /* Find the inserted child */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT uploadtree_pk FROM uploadtree WHERE upload_fk=%s AND pfile_fk=%ld AND ufile_mode=%ld AND ufile_name='%s';",
    Upload_Pk, CI->pfile_pk, CI->ufile_mode, UfileName);
  rc=MyDBaccess(DBTREE,"SELECT currval('uploadtree_uploadtree_pk_seq');");
  CI->uploadtree_pk = atol(DBgetvalue(DBTREE,0,0));
  TotalItems++;
  // printf("=========== AFTER ==========\n"); DebugContainerInfo(CI);

  return(0);
} /* DBInsertUploadTree() */

/***************************************************
 AddToRepository(): Add a ContainerInfo record to the
 repository AND to the database.
 This modifies the CI record's pfile and ufile indexes!
 Returns: 1 if added, 0 if already exists!
 ***************************************************/
int	AddToRepository	(ContainerInfo *CI, char *Fuid, int Mask)
{
  int IsUnique = 1;  /* is it a DB replica? */

  /*****************************************/
  /* populate repository (include artifacts) */
  /* If we ever want to skip artifacts, use && !CI->Artifact */
  if ((Fuid[0]!='\0') && UseRepository)
    {
    /* put file in repository */
    if (!RepExist(REP_FILES,Fuid))
      {
      if (RepImport(CI->Source,REP_FILES,Fuid,1) != 0)
	  {
	  fprintf(stderr,"ERROR: Failed to import '%s' as '%s' into the repository\n",CI->Source,Fuid);
	  SafeExit(16);
	  }
      }
    if (Verbose) fprintf(stderr,"Repository[%s]: insert '%s' as '%s'\n",
	REP_FILES,CI->Source,Fuid);
    }

  /*****************************************/
  /* populate DB (skip artifacts) */
  if (!DB) return(1); /* No DB? Quit! (and say it is unique) */

  /* PERFORMANCE NOTE:
     I used to use and INSERT and an UPDATE.
     Turns out, INSERT is fast, UPDATE is *very* slow (10x).
     Now I just use an INSERT.
   */

  /* Insert pfile record */
  if (!DBInsertPfile(CI,Fuid)) return(0);
  /* Update uploadtree table */
  IsUnique = !DBInsertUploadTree(CI,Mask);

  if (ForceDuplicate) IsUnique=1;
  return(IsUnique);
} /* AddToRepository() */

/***************************************************
 DisplayContainerInfo(): Print what can be printed in XML.
 Cmd = command used to create this file (parent)
 CI->Cmd = command to be used ON this file (child)
 Returns: 1 if item is unique, 0 if duplicate.
 ***************************************************/
int	DisplayContainerInfo	(ContainerInfo *CI, int Cmd)
{
  int i;
  int Mask=0177000; /* used for XML modemask */
  char Fuid[1024];

  if (CI->Source[0] == '\0') return(0);
  memset(Fuid,0,sizeof(Fuid));
  /* TotalItems++; */

  /* list source */
  if (ListOutFile)
    {
    fputs("<item source=\"",ListOutFile);
    for(i=0; CI->Source[i] != '\0'; i++)
      {
      if (isalnum(CI->Source[i]) ||
	  strchr(" `~!@#$%^*()-=_+[]{}\\|;:',./?",CI->Source[i]))
	fputc(CI->Source[i],ListOutFile);
      else fprintf(ListOutFile,"&#x%02x;",(int)(CI->Source[i])&0xff);
      }
    fputs("\" ",ListOutFile);

    /* list file names */
    if (CI->Partname[0] != '\0')
      {
      fputs("name=\"",ListOutFile);
      /* XML taint-protect name */
      for(i=0; CI->Partname[i] != '\0'; i++)
	{
	if (isalnum(CI->Partname[i]) ||
	    strchr(" `~!@#$%^*()-=_+[]{}\\|;:',./?",CI->Partname[i]))
		fputc(CI->Partname[i],ListOutFile);
	else fprintf(ListOutFile,"&#x%02x;",(int)(CI->Partname[i])&0xff);
	}
      fputs("\" ",ListOutFile);
      }

    /* list mime info */
    if ((CI->PI.Cmd >= 0) && (CMD[CI->PI.Cmd].Type != CMD_DEFAULT))
      {
      fprintf(ListOutFile,"mime=\"%s\" ",CMD[CI->PI.Cmd].Magic);
      TotalFiles++;
      }
    else if (S_ISDIR(CI->Stat.st_mode))
      {
      fprintf(ListOutFile,"mime=\"directory\" ");
      TotalDirectories++;
      }
    else TotalFiles++;
  
    /* identify compressed files */
    if (CMD[CI->PI.Cmd].Type == CMD_PACK)
      {
      fprintf(ListOutFile,"compressed=\"1\" ");
      TotalCompressedFiles++;
      }
    /* identify known artifacts */
    if (CI->Artifact)
      {
      fprintf(ListOutFile,"artifact=\"1\" ");
      TotalArtifacts++;
      }

    if (CI->HasChild) fprintf(ListOutFile,"haschild=\"1\" ");
    } /* if ListOutFile */

  if (!CI->TopContainer)
    {
    /* list mode */
    Mask=0177000;
    if (Cmd >= 0)
      {
      if (S_ISDIR(CI->Stat.st_mode))
	{
	Mask = CMD[Cmd].ModeMaskDir;
	}
      else if (S_ISREG(CI->Stat.st_mode))
	{
	Mask = CMD[Cmd].ModeMaskReg;
	}
      }

    if (ListOutFile)
      {
      if (!CI->Artifact) /* no masks for an artifact */
	{
	fprintf(ListOutFile,"mode=\"%07o\" ",CI->Stat.st_mode & Mask);
	fprintf(ListOutFile,"modemask=\"%07o\" ",Mask);
	}

      /* identify known corrupted files */
      if (CI->Corrupt) fprintf(ListOutFile,"error=\"%d\" ",CI->Corrupt);

      /* list timestamps */
      if (CI->Stat.st_mtime)
	{
	if ((CI->Stat.st_mtime < CI->PI.StartTime) || (CI->Stat.st_mtime > CI->PI.EndTime))
	  fprintf(ListOutFile,"mtime=\"%d\" ",(int)(CI->Stat.st_mtime));
	}
#if 0
      /** commented out since almost anything can screw this up. **/
      if (CI->Stat.st_ctime)
	{
	if ((CI->Stat.st_ctime < CI->PI.StartTime) || (CI->Stat.st_ctime > CI->PI.EndTime))
	  fprintf(ListOutFile,"ctime=\"%d\" ",(int)(CI->Stat.st_ctime));
	}
#endif
      } /* if ListOutFile */
    } /* if not top container */

  /* list checksum info for files only! */
  if (S_ISREG(CI->Stat.st_mode) && !CI->Pruned)
    {
    CksumFile *CF;
    Cksum *Sum;

    CF = SumOpenFile(CI->Source);
    if (CF)
      {
      Sum = SumComputeBuff(CF);
      SumCloseFile(CF);
      if (Sum)
	{
	for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",Sum->SHA1digest[i]); }
	Fuid[40]='.';
	for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",Sum->MD5digest[i]); }
	Fuid[73]='.';
	snprintf(Fuid+74,sizeof(Fuid)-74,"%Lu",(long long unsigned int)Sum->DataLen);
	if (ListOutFile) fprintf(ListOutFile,"fuid=\"%s\" ",Fuid);
	free(Sum);
	} /* if Sum */
      } /* if CF */
    else /* file too large to mmap (probably) */
      {
      FILE *Fin;
      Fin = fopen64(CI->Source,"rb");
      if (Fin)
	{
	Sum = SumComputeFile(Fin);
	if (Sum)
	  {
	  for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",Sum->SHA1digest[i]); }
	  Fuid[40]='.';
	  for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",Sum->MD5digest[i]); }
	  Fuid[73]='.';
	  snprintf(Fuid+74,sizeof(Fuid)-74,"%Lu",(long long unsigned int)Sum->DataLen);
	  if (ListOutFile) fprintf(ListOutFile,"fuid=\"%s\" ",Fuid);
	  free(Sum);
	  }
	fclose(Fin);
	}
      }
    } /* if is file */

  /* end XML */
  if (ListOutFile)
    {
    if (CI->HasChild) fputs(">\n",ListOutFile);
    else fputs("/>\n",ListOutFile);
    } /* if ListOutFile */

  return(AddToRepository(CI,Fuid,Mask));
} /* DisplayContainerInfo() */

/***************************************************
 TraverseChild(): This is the child spawn for recursion.
 The child never leaves here!  It calls EXIT!
 Exit is 0 on success, non-zero on failure.
 ***************************************************/
void	TraverseChild	(int Index, ContainerInfo *CI, char *NewDir)
{
  int rc;
  int PlainCopy=0;
  cmdtype Type;
  Type = CMD[CI->PI.Cmd].Type;
  if (CMD[CI->PI.Cmd].Status == 0) Type=CMD_DEFAULT;
  switch(Type)
	{
	case CMD_PACK:
	case CMD_RPM:
	  /* unpack in the current directory */
	  rc=RunCommand(CMD[CI->PI.Cmd].Cmd,CMD[CI->PI.Cmd].CmdPre,CI->Source,
	     CMD[CI->PI.Cmd].CmdPost,CI->PartnameNew,CI->Partdir);
	  break;
	case CMD_ARC:
	case CMD_PARTITION:
	  /* unpack in a sub-directory */
	  rc=RunCommand(CMD[CI->PI.Cmd].Cmd,CMD[CI->PI.Cmd].CmdPre,CI->Source,
	     CMD[CI->PI.Cmd].CmdPost,CI->PartnameNew,Queue[Index].ChildRecurse);
	  if (!strcmp(CMD[CI->PI.Cmd].Magic,"application/x-zip") &&
	      ((rc==1) || (rc==2) || (rc==51)) )
		{
		fprintf(stderr,"WARNING pfile %s Minor zip error... ignoring error.\n",Pfile_Pk);
		fprintf(stderr,"LOG pfile %s Minor zip error(%d)... ignoring error.\n",Pfile_Pk,rc);
		rc=0;	/* lots of zip return codes */
		}
	  break;
	case CMD_AR:
	  /* unpack an AR: source file and destination directory */
	  rc=ExtractAR(CI->Source,Queue[Index].ChildRecurse);
	  break;
	case CMD_ISO:
	  /* unpack an ISO: source file and destination directory */
	  rc=ExtractISO(CI->Source,Queue[Index].ChildRecurse);
	  break;
	case CMD_DISK:
	  /* unpack a DISK: source file, FS type, and destination directory */
	  rc=ExtractDisk(CI->Source,CMD[CI->PI.Cmd].Cmd,Queue[Index].ChildRecurse);
	  break;
	case CMD_DEFAULT:
	default:
	  /* use the original name */
	  PlainCopy=1;
	  if (!IsFile(Queue[Index].ChildRecurse,0))
	  	{
	  	CopyFile(CI->Source,Queue[Index].ChildRecurse);
		}
	  rc=0;
	  break;
	} /* switch type of processing */

      /* Child: Unpacks */
      /* remove source */
      if (UnlinkSource && (rc==0) && !NewDir && !PlainCopy)
	{
	/* if we're unlinking AND command worked AND it's not original... */
	unlink(CI->Source);
	}
      if (rc)
	{
	/* if command failed but we want to continue anyway */
	/** Note: CMD_DEFAULT will never get here because rc==0 **/
	fprintf(stderr,"%s pfile %s Command %s failed\n",
		ForceContinue?"WARNING":"ERROR",Pfile_Pk,CMD[CI->PI.Cmd].Cmd);
	fprintf(stderr,"LOG pfile %s %s Command %s failed: %s\n",
		Pfile_Pk,ForceContinue?"WARNING":"ERROR",CMD[CI->PI.Cmd].Cmd,CI->Source);
	if (ForceContinue) rc=-1;
	}
  exit(rc);
} /* TraverseChild() */

/***************************************************
 Traverse(): Find all files, traverse all directories.
 This is a depth-first search, in inode order!
 Label is used for debugging.
 NewDir specifies an alternate directory to extract to.
 Default (NewDir==NULL) is to extract to the same directory
 as Filename.
 Returns: 1 if Filename was a container, 0 if not a container.
 (The return value is really only used by TraverseStart().)
 ***************************************************/
int	Traverse	(char *Filename, char *Basename,
			 char *Label, char *NewDir,
			 int Recurse, ParentInfo *PI)
{
  int rc;
  int i;
  ContainerInfo CI,CImeta;
  int IsContainer=0;
  int RecurseOk=1;	/* should it recurse? (only on unique inserts) */

  if (!Filename || (Filename[0]=='\0')) return(IsContainer);
  if (Verbose > 0) printf("Traverse(%s) -- %s\n",Filename,Label);

  /* clear the container */
  memset(&CI,0,sizeof(ContainerInfo));

  /* check for top containers */
  CI.TopContainer = (NewDir!=NULL);

  /***********************************************/
  /* Populate CI and CI.PI structure */
  /***********************************************/
  CI.PI.Cmd=PI->Cmd;	/* inherit */
  CI.PI.StartTime = PI->StartTime;
  CI.PI.EndTime = PI->EndTime;
  CI.PI.uploadtree_pk = PI->uploadtree_pk;
  CI.Artifact = PI->ChildRecurseArtifact;
  /* the item is processed; log all children */
  if (CI.Artifact > 0) PI->ChildRecurseArtifact=CI.Artifact-1;
  else PI->ChildRecurseArtifact=0;

  rc = lstat64(Filename,&CI.Stat);

  /* Source filename may be from another Queue element.
     Copy the name over so it does not accidentally change. */
  strcpy(CI.Source,Filename);

  /* split directory and filename */
  if (Basename) SetDir(CI.Partdir,sizeof(CI.Partdir),NewDir,Basename);
  else SetDir(CI.Partdir,sizeof(CI.Partdir),NewDir,CI.Source);

  /* count length of filename */
  for(i=strlen(CI.Source)-1; (i>=0) && (CI.Source[i] != '/'); i--)
	;
  strcpy(CI.Partname,CI.Source+i+1);
  strcpy(CI.PartnameNew,CI.Partname);

  /***********************************************/
  /* ignore anything that is not a directory or a file */
  /***********************************************/
  if (CI.Stat.st_mode & S_IFMT & ~(S_IFREG | S_IFDIR))
	{
	if (PI->Cmd) DisplayContainerInfo(&CI,PI->Cmd);
	goto TraverseEnd;
	}

  if (rc != 0)
	{
	/* this should never happen... */
	fprintf(stderr,"LOG pfile %s \"%s\" does not exist!\n",Pfile_Pk,Filename);
	/* goto TraverseEnd; */
	return(0);
	}

  /***********************************************/
  /* handle pruning (on recursion only -- never delete originals) */
  /***********************************************/
  if (PruneFiles && !NewDir && Prune(Filename,CI.Stat))
	{
	/* pruned! */
	if (PI->Cmd)
		{
		CI.Pruned=1;
		DisplayContainerInfo(&CI,PI->Cmd);
		}
	goto TraverseEnd;
	}

  /***********************************************/
  /* check the type of file in filename: file or directory */
  /***********************************************/
  if (S_ISDIR(CI.Stat.st_mode))
    {
    /***********************************************/
    /* if it's a directory, then recurse! */
    /***********************************************/
    dirlist *DLhead, *DLentry;
    long TmpPk;

    /* record stats */
    CI.IsDir=1;
    CI.HasChild=1;
    IsContainer=1;

    /* make sure it is accessible */
    if (!NewDir && ((CI.Stat.st_mode & 0700) != 0700))
      {
      chmod(Filename,(CI.Stat.st_mode | 0700));
      }

    if (CI.Source[strlen(CI.Source)-1] != '/') strcat(CI.Source,"/");
    DLhead = MakeDirList(CI.Source);
    /* process inode in the directory (only if unique) */
    if (DisplayContainerInfo(&CI,PI->Cmd))
	{
	for(DLentry=DLhead; DLentry; DLentry=DLentry->Next)
	  {
	  SetDir(CI.Partdir,sizeof(CI.Partdir),NewDir,CI.Source);
	  strcat(CI.Partdir,DLentry->Name);
	  TmpPk = CI.PI.uploadtree_pk;
	  CI.PI.uploadtree_pk = CI.uploadtree_pk;
	  /* don't decrement just because it is a directory */
	  Traverse(CI.Partdir,NULL,"Called by dir",NULL,Recurse,&(CI.PI));
	  CI.PI.uploadtree_pk = TmpPk;
	  }
	}
    if (PI->Cmd && ListOutFile)
	{
	fputs("</item>\n",ListOutFile);
	}
    FreeDirList(DLhead);
    } /* if S_ISDIR() */

#if 0
  else if (S_ISLNK(CI.Stat.st_mode) || S_ISCHR(CI.Stat.st_mode) ||
	   S_ISBLK(CI.Stat.st_mode) || S_ISFIFO(CI.Stat.st_mode) ||
	   S_ISSOCK(CI.Stat.st_mode))
    {
    /* skip symbolic links, blocks, and special devices */
    /** This condition should never happen since we already ignore anything
	that is not a file or a directory. **/
    }
#endif

  /***********************************************/
  else if (S_ISREG(CI.Stat.st_mode))
    {
    /***********************************************/
    /* if it's a regular file, then process it! */
    /***********************************************/
    int Pid;
    int Index;  /* child index into queue table */

    CI.PI.Cmd = FindCmd(CI.Source);
    if (CI.PI.Cmd < 0) goto TraverseEnd;

    /* make sure it is accessible */
    if (!NewDir && ((CI.Stat.st_mode & 0600) != 0600))
      {
      chmod(Filename,(CI.Stat.st_mode | 0600));
      }

    /** if it made it this far, then it's spawning time! **/
    /* Determine where to put the output */
    Index=0;
    while((Index < MAXCHILD) && (Queue[Index].ChildPid != 0))
	Index++;

    /* determine output location */
    memset(Queue+Index,0,sizeof(unpackqueue)); /* clear memory */
    strcpy(Queue[Index].ChildRecurse,CI.Partdir);
    strcat(Queue[Index].ChildRecurse,CI.Partname);
    Queue[Index].PI.StartTime = CI.PI.StartTime;
    Queue[Index].ChildEnd=0;
    Queue[Index].PI.Cmd = CI.PI.Cmd;
    Queue[Index].PI.uploadtree_pk = CI.PI.uploadtree_pk;
    Queue[Index].ChildStat = CI.Stat;
    switch(CMD[CI.PI.Cmd].Type)
	{
	case CMD_ARC:
	case CMD_AR:
	case CMD_ISO:
	case CMD_DISK:
	case CMD_PARTITION:
	  CI.HasChild=1;
	  IsContainer=1;
	  strcat(Queue[Index].ChildRecurse,".dir");
	  strcat(CI.PartnameNew,".dir");
	  Queue[Index].PI.ChildRecurseArtifact=1;
	  /* make the directory */
	  if (MkDir(Queue[Index].ChildRecurse))
	    {
	    printf("FATAL: Unable to mkdir(%s) in Traverse\n",
		Queue[Index].ChildRecurse);
	    if (!ForceContinue)
	      {
	      SafeExit(17);
	      }
	    }
	  if (CMD[CI.PI.Cmd].Type == CMD_PARTITION)
		Queue[Index].PI.ChildRecurseArtifact=2;
	  break;
	case CMD_PACK:
	case CMD_RPM:
	  CI.HasChild=1;
	  IsContainer=1;
	  strcat(Queue[Index].ChildRecurse,".unpacked");
	  strcat(CI.PartnameNew,".unpacked");
	  Queue[Index].PI.ChildRecurseArtifact=1;
	  if ((CMD[CI.PI.Cmd].Type == CMD_PACK))
	  	{
		CI.IsCompressed = 1;
		}
	  break;
	case CMD_DEFAULT:
	default:
	  /* use the original name */
	  CI.HasChild=0;
	  Queue[Index].ChildEnd=1;
	  break;
	}
    Queue[Index].ChildHasChild = CI.HasChild;

    /* save the file's data */
    RecurseOk = DisplayContainerInfo(&CI,PI->Cmd);

    /* extract meta info if we added it */
    if (RecurseOk && CMD[CI.PI.Cmd].MetaCmd && CMD[CI.PI.Cmd].MetaCmd[0])
      {
      /* extract meta info */
      /** This needs to call AddToRepository() or DisplayContainerInfo() **/
      char Cmd[2*FILENAME_MAX];
      char Fname[FILENAME_MAX];
      memcpy(&CImeta,&CI,sizeof(CI));
      CImeta.Artifact=1;
      CImeta.HasChild=0;
      CImeta.TopContainer = 0;
      CImeta.PI.uploadtree_pk = CI.uploadtree_pk;
      CImeta.PI.Cmd = 0; /* no meta type */
      memset(Cmd,0,sizeof(Cmd));
      memset(Fname,0,sizeof(Fname));
      strcpy(Fname,CImeta.Source);
      strcat(CImeta.Source,".meta");
      strcat(CImeta.Partname,".meta");

      /* remove the destination file if it exists */
      /** this gets past any permission problems with read-only files **/
      unlink(CImeta.Source);

      /* build the command and run it */
      sprintf(Cmd,CMD[CI.PI.Cmd].MetaCmd,Fname,CImeta.Source);
      rc = system(Cmd);
      if (WIFSIGNALED(rc))
        {
        printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
	SafeExit(18);
        }
      if (WIFEXITED(rc)) rc = WEXITSTATUS(rc);
      else rc=-1;
      if (rc != 0) fprintf(stderr,"Unable to run command '%s'\n",Cmd);
      /* add it to the list of files */
      RecurseOk = DisplayContainerInfo(&CImeta,PI->Cmd);
      if (UnlinkAll) unlink(CImeta.Source);
      }

    /* see if I need to spawn (if not, then save time by not!) */
    if ((Queue[Index].ChildEnd == 1) && IsFile(Queue[Index].ChildRecurse,0))
	{
	goto TraverseEnd;
	}

    /* spawn unpacker */
    fflush(stdout); /* if no flush, then child may duplicate output! */
    if (ListOutFile) fflush(ListOutFile);
    if (RecurseOk)
      {
      Pid = fork();
      if (Pid == 0) TraverseChild(Index,&CI,NewDir);
      else
	{
	/* Parent: Save child info */
	if (Pid == -1)
	  {
	  perror("FATAL: Unable to fork child.\n");
	  SafeExit(19);
	  }
	Queue[Index].ChildPid = Pid;
	Thread++;
	/* Parent: Continue testing files */
	if (Thread >= MaxThread)
	  {
	  /* Too many children.  Wait for one to end */
	  Index=ParentWait();
	  if (Index < 0) goto TraverseEnd; /* no more children (shouldn't happen here!) */
	  Thread--;
	  /* the value for ChildRecurse can/will be overwitten quickly, but
	     it will be overwritten AFTER it is used */
	  /* Only recurse if the name is different */
	  if (strcmp(Queue[Index].ChildRecurse,CI.Source) && !Queue[Index].ChildEnd)
	    {
	    /* copy over data */
	    CI.Corrupt = Queue[Index].ChildCorrupt;
	    CI.PI.StartTime = Queue[Index].PI.StartTime;
	    CI.PI.EndTime = Queue[Index].PI.EndTime;
	    CI.PI.uploadtree_pk = Queue[Index].PI.uploadtree_pk;
	    CI.HasChild = Queue[Index].ChildHasChild;
	    CI.Stat = Queue[Index].ChildStat;
	    Queue[Index].PI.uploadtree_pk = CI.uploadtree_pk;
	    if (Recurse > 0)
	      Traverse(Queue[Index].ChildRecurse,NULL,"Called by dir/wait",NULL,Recurse-1,&Queue[Index].PI);
	    else if (Recurse < 0)
	      Traverse(Queue[Index].ChildRecurse,NULL,"Called by dir/wait",NULL,Recurse,&Queue[Index].PI);
	    if (ListOutFile)
		{
		fputs("</item>\n",ListOutFile);
		TotalContainers++;
		}
	    }
	  } /* if waiting for a child */
	} /* if parent */
      } /* if RecurseOk */
    } /* if S_ISREG() */

  /***********************************************/
  else
    {
    /* Not a file and not a directory */
    if (PI->Cmd)
	{
	CI.HasChild = 0;
	DisplayContainerInfo(&CI,PI->Cmd);
	}
    printf("Skipping (not a file or directory): %s\n",CI.Source);
    }

TraverseEnd:
  if (UnlinkAll)
    {
#if 0
    printf("===\n");
    printf("Source: '%s'\n",CI.Source);
    printf("NewDir: '%s'\n",NewDir ? NewDir : "");
    printf("Name: '%s'  '%s'\n",CI.Partdir,CI.Partname);
#endif
    if (!NewDir)
      {
      if (IsDir(CI.Source)) rmdir(CI.Source);
      else unlink(CI.Source);
      }
    }
  return(IsContainer);
} /* Traverse() */

/***************************************************
 TraverseStart(): Find all files (assuming a directory)
 and process all of them.
 ***************************************************/
void	TraverseStart	(char *Filename, char *Label, char *NewDir,
			 int Recurse)
{
  dirlist *DLhead, *DLentry;
  char Name[FILENAME_MAX];
  char *Basename; /* the filename without the path */
  ParentInfo PI;

  PI.Cmd = 0;
  PI.StartTime = time(NULL);
  PI.EndTime = PI.StartTime;
  PI.uploadtree_pk = 0;
  Basename = strrchr(Filename,'/');
  if (Basename) Basename++;
  else Basename = Filename;
  memset(SQL,'\0',MAXSQL);
  if (!IsDir(Filename))
	{
	memset(Name,'\0',sizeof(Name));
	strcpy(Name,Filename);
	Traverse(Filename,Basename,Label,NewDir,Recurse,&PI);
	}
  else /* process directory */
	{
	DLhead = MakeDirList(Filename);
	for(DLentry=DLhead; DLentry; DLentry=DLentry->Next)
	  {
	  /* Now process the filename */
	  memset(Name,'\0',sizeof(Name));
	  strcpy(Name,Filename);
	  if (Last(Name) != '/') strcat(Name,"/");
	  strcat(Name,DLentry->Name);
	  TraverseStart(Name,Label,NewDir,Recurse);
	  }
	FreeDirList(DLhead);
	}

  /* remove anything that we needed to create */
  if (UnlinkAll)
    {
    stat_t Src,Dst;
    int i;
    /* build the destination name */
    SetDir(Name,sizeof(Name),NewDir,Basename);
    for(i=strlen(Filename)-1; (i>=0) && (Filename[i] != '/'); i--)
	;
    if (strcmp(Filename+i+1,".")) strcat(Name,Filename+i+1);
    lstat64(Filename,&Src);
    lstat64(Name,&Dst);
#if 0
    printf("End:\n");
    printf("  Src: %ld %s\n",(long)Src.st_ino,Filename);
    printf("  Dst: %ld %s\n",(long)Dst.st_ino,Name);
#endif
    /* only delete them if they are different!  (Different inodes) */
    if (Src.st_ino != Dst.st_ino)
      {
      if (IsDir(Name)) rmdir(Name);
      else unlink(Name);
      }
    } /* if UnlinkAll */
} /* TraverseStart() */

/**********************************************
 Usage(): Display program usage.
 **********************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Universal Unpacker, version %s, compiled %s %s\n",
	Version,__DATE__,__TIME__);
  fprintf(stderr,"Usage: %s [options] file [file [file...]]\n",Name);
  fprintf(stderr,"  Extracts each file.\n");
  fprintf(stderr,"  If filename specifies a directory, then extracts everything in it.\n");
  fprintf(stderr," Unpack Options:\n");
  fprintf(stderr,"  -C     :: force continue when unpack tool fails.\n");
  fprintf(stderr,"  -d dir :: specify alternate extraction directory.\n");
  fprintf(stderr,"            Default is the same directory as file (usually not a good idea).\n");
  fprintf(stderr,"  -m #   :: number of CPUs to use (default: 1).\n");
  fprintf(stderr,"  -P     :: prune files: remove links, >1 hard links, zero files, etc.\n");
  fprintf(stderr,"  -R     :: recursively unpack (same as '-r -1')\n");
  fprintf(stderr,"  -r #   :: recurse to a specified depth (0=none/default, -1=infinite)\n");
  fprintf(stderr,"  -X     :: remove recursive sources after unpacking.\n");
  fprintf(stderr,"  -x     :: remove ALL unpacked files when done (clean up).\n");
  fprintf(stderr," I/O Options:\n");
  fprintf(stderr,"  -L out :: Generate a log of files extracted (in XML) to out.\n");
  fprintf(stderr,"  -F     :: Using files from the repository.\n");
  fprintf(stderr,"  -i     :: Initialize the database queue system, then exit.\n");
  fprintf(stderr,"  -Q     :: Using database queue system. (Includes -F)\n");
  fprintf(stderr,"            Each source name should come from the repository.\n");
  fprintf(stderr,"            First 'gold' is checked, then 'files'.\n");
  fprintf(stderr,"            If -L is used, unpacked files are placed in 'files'.\n");
  fprintf(stderr,"      -T rep :: Set gold repository name to 'rep' (for testing)\n");
  fprintf(stderr,"      -t rep :: Set files repository name to 'rep' (for testing)\n");
  fprintf(stderr,"      -A     :: do not set the initial DB container as an artifact.\n");
  fprintf(stderr,"      -f     :: force processing files that already exist in the DB.\n");
  fprintf(stderr,"  -q     :: quiet (generate no output).\n");
  fprintf(stderr,"  -H     :: Debug heartbeat (turns it on and prints timestamps)\n");
  fprintf(stderr,"  -v     :: verbose (-vv = more verbose).\n");
  fprintf(stderr,"Currently identifies and processes:\n");
  fprintf(stderr,"  Compressed files: .Z .gz .bz .bz2 upx\n");
  fprintf(stderr,"  Archives files: tar cpio zip jar ar rar cab\n");
  fprintf(stderr,"  Data files: pdf\n");
  fprintf(stderr,"  Installer files: rpm deb\n");
  fprintf(stderr,"  File images: iso9660(plain/Joliet/Rock Ridge) FAT(12/16/32) ext2/ext3 NTFS\n");
  fprintf(stderr,"  Boot partitions: x86, vmlinuz\n");
  CheckCommands(Quiet);
} /* Usage() */

/***********************************************************************/
int	main	(int argc, char *argv[])
{
  int Pid;
  int c;
  char *NewDir=".";
  int Recurse=0;
  char *ListOutName=NULL;
  char *Fname = NULL;

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
    {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    exit(-1);
    }

  /* Debian is trying to depricate the added ".mime" suffix.
     Check if it needs to be added. */
  if ((magic_load(MagicCookie,UNMAGIC ".mime") != 0) &&
      (magic_load(MagicCookie,UNMAGIC) != 0))
    {
    fprintf(stderr,"FATAL: Failed to load magic file: UnMagic\n");
    exit(-1);
    }

  while((c = getopt(argc,argv,"ACd:FfHL:m:PQiqRr:T:t:vXx")) != -1)
    {
    switch(c)
	{
	case 'A':	SetContainerArtifact=0; break;
	case 'C':	ForceContinue=1; break;
	case 'd':	NewDir=optarg; break;
	case 'F':	UseRepository=1; break;
	case 'f':	ForceDuplicate=1; break;
	case 'H':
		DebugHeartbeat=1;
		signal(SIGALRM,AlarmDisplay);
		alarm(10);
		break;
	case 'L':	ListOutName=optarg; break;
	case 'm':
		MaxThread = atoi(optarg);
		if (MaxThread < 1) MaxThread=1;
		break;
	case 'P':	PruneFiles=1; break;
	case 'R':	Recurse=-1; break;
	case 'r':	Recurse=atoi(optarg); break;
	case 'i':
		DB=DBopen();
		if (!DB)
			{
			fprintf(stderr,"FATAL: Unable to access database\n");
			SafeExit(20);
			}
		GetAgentKey();
		DBclose(DB);
		return(0);
		break; /* never reached */
	case 'Q':
		UseRepository=1;
		DB=DBopen();
		DBTREE=DBopen();
		if (!DB || !DBTREE)
		  {
		  fprintf(stderr,"FATAL: Unable to access database\n");
		  SafeExit(21);
		  }
		if (MyDBaccess(DBTREE,"BEGIN;") < 0)
		  {
		  printf("ERROR pfile %s Unable to 'BEGIN' database updates.\n",Pfile_Pk);
		  SafeExit(22);
		  }
		signal(SIGALRM,AlarmDisplay);
		alarm(10);
		GetAgentKey();
		Pfile = getenv("ARG_pfile");
		if (!Pfile) Pfile = getenv("pfile");
		Pfile_Pk = getenv("ARG_pfile_fk");
		if (!Pfile_Pk) Pfile_Pk = getenv("pfile_fk");
		Upload_Pk = getenv("ARG_upload_pk");
		if (!Upload_Pk) Upload_Pk = getenv("upload_pk");

		/* Check for all necessary parameters */
		if (Verbose)
		  {
		  printf("ENV Pfile=%s\n",Pfile);
		  printf("ENV Pfile_Pk=%s\n",Pfile_Pk);
		  printf("ENV Upload_Pk=%s\n",Upload_Pk);
		  }
		if (DB && !Pfile)
		  {
		  printf("FATAL: Pfile not specified in environment.\n");
		  SafeExit(23);
		  }
		if (DB && !Pfile_Pk)
		  {
		  printf("FATAL: Pfile_Pk not specified in environment.\n");
		  SafeExit(24);
		  }
		InitCmd();
		break;
	case 'q':	Quiet=1; break;
	case 'T':
		memset(REP_GOLD,0,sizeof(REP_GOLD));
		strncpy(REP_GOLD,optarg,sizeof(REP_GOLD)-1);
		break;
	case 't':
		memset(REP_FILES,0,sizeof(REP_FILES));
		strncpy(REP_FILES,optarg,sizeof(REP_FILES)-1);
		break;
	case 'v':	Verbose++; break;
	case 'X':	UnlinkSource=1; break;
	case 'x':	UnlinkAll=1; break;
	default:
		Usage(argv[0]);
		SafeExit(25);
	}
    }

  if ((optind >= argc) && !UseRepository)
	{
	Usage(argv[0]);
	SafeExit(26);
	}

  /*** post-process args ***/
#if 0
  umask(0077); /* default to user-only access */
#endif

  CheckCommands(Quiet);
  if (NewDir) MkDir(NewDir);
  if (Verbose) { fclose(stderr) ; stderr=stdout; } /* don't interlace! */
  if (ListOutName != NULL)
	{
	if ((ListOutName[0]=='-') && (ListOutName[1]=='\0'))
		ListOutFile=stdout;
	else ListOutFile = fopen(ListOutName,"w");
	if (!ListOutFile)
		{
		printf("WARNING pfile %s There was a processing error during a file-write\n",Pfile_Pk);
		printf("LOG pfile %s Unable to write to %s\n",Pfile_Pk,ListOutName);
		SafeExit(27);
		}
	else
		{
		/* Start the file */
		fputs("<xml tool=\"ununpack\" ",ListOutFile);
		fputs("version=\"",ListOutFile);
		fputs(Version,ListOutFile);
		fputs("\" ",ListOutFile);
		fputs("compiled_date=\"",ListOutFile);
		fputs(__DATE__,ListOutFile);
		fputs(" ",ListOutFile);
		fputs(__TIME__,ListOutFile);
		fputs("\"",ListOutFile);
		fputs(">\n",ListOutFile);
		}
	/* Problem: When parallel processing, the XML may be generated out
	   of order.  Solution?  When using XML, only use 1 thread. */
	MaxThread=1;
	}

  /*** process files ***/
  for( ; optind<argc; optind++)
    {
    CksumFile *CF=NULL;
    Cksum *Sum;
    int i;
    if (Fname) { free(Fname); Fname=NULL; }
    if (ListOutName != NULL)
      {
      fprintf(ListOutFile,"<source source=\"%s\" ",argv[optind]);
      if (UseRepository && !RepExist(REP_FILES,argv[optind]))
	{
	/* make sure the source exists in the src repository */
	if (RepImport(argv[optind],REP_FILES,argv[optind],1) != 0)
	  {
	  fprintf(stderr,"ERROR: Failed to import '%s' as '%s' into the repository\n",argv[optind],argv[optind]);
	  SafeExit(28);
	  }
	}
      }
    if (UseRepository)
	{
	if (RepExist(REP_FILES,argv[optind]))
		{
		Fname=RepMkPath(REP_FILES,argv[optind]);
		}
	else if (RepExist(REP_GOLD,argv[optind]))
		{
		Fname=RepMkPath(REP_GOLD,argv[optind]);
		if (RepImport(Fname,REP_FILES,argv[optind],1) != 0)
		  {
		  fprintf(stderr,"ERROR: Failed to import '%s' as '%s' into the repository\n",Fname,argv[optind]);
		  SafeExit(29);
		  }
		}
	if (Fname)
	  {
	  CF = SumOpenFile(Fname);
	  }
	/* else: Fname is NULL and CF is NULL */
	}
    else CF = SumOpenFile(argv[optind]);
    if (ListOutFile)
      {
      if (CF)
	{
	Sum = SumComputeBuff(CF);
	SumCloseFile(CF);
	if (Sum)
	  {
	  fputs("fuid=\"",ListOutFile);
	  for(i=0; i<20; i++)
	    { fprintf(ListOutFile,"%02X",Sum->SHA1digest[i]); }
	  fputs(".",ListOutFile);
	  for(i=0; i<16; i++)
	    { fprintf(ListOutFile,"%02X",Sum->MD5digest[i]); }
	  fputs(".",ListOutFile);
	  fprintf(ListOutFile,"%Lu",(long long unsigned int)Sum->DataLen);
	  fputs("\" ",ListOutFile);
	  free(Sum);
	  } /* if Sum */
	} /* if CF */
      else /* file too large to mmap (probably) */
	{
	FILE *Fin;
	Fin = fopen64(argv[optind],"rb");
	if (Fin)
	  {
	  Sum = SumComputeFile(Fin);
	  if (Sum)
	    {
	    fputs("fuid=\"",ListOutFile);
	    for(i=0; i<20; i++)
	      { fprintf(ListOutFile,"%02X",Sum->SHA1digest[i]); }
	    fputs(".",ListOutFile);
	    for(i=0; i<16; i++)
	      { fprintf(ListOutFile,"%02X",Sum->MD5digest[i]); }
	    fputs(".",ListOutFile);
	    fprintf(ListOutFile,"%Lu",(long long unsigned int)Sum->DataLen);
	    fputs("\" ",ListOutFile);
	    free(Sum);
	    }
	  fclose(Fin);
	  }
	} /* else no CF */
    fprintf(ListOutFile,">\n"); /* end source XML */
    }
  if (Fname)	TraverseStart(Fname,"called by main via args",NewDir,Recurse);
  else		TraverseStart(argv[optind],"called by main",NewDir,Recurse);
  if (ListOutName != NULL) fprintf(ListOutFile,"</source>\n");
  } /* end for */

  /* free memory */
  if (Fname) { free(Fname); Fname=NULL; }

  /* process pfile from environment */
  if (Pfile)
    {
    if (RepExist(REP_FILES,Pfile))
	{
	Fname=RepMkPath(REP_FILES,Pfile);
	}
    else if (RepExist(REP_GOLD,Pfile))
	{
	Fname=RepMkPath(REP_GOLD,Pfile);
	if (RepImport(Fname,REP_FILES,Pfile,1) != 0)
	  {
	  fprintf(stderr,"ERROR: Failed to import '%s' as '%s' into the repository\n",Fname,Pfile);
	  SafeExit(30);
	  }
	}
    if (Fname)
	{
	TraverseStart(Fname,"called by main via env",NewDir,Recurse);
	free(Fname);
	Fname=NULL;
	}
    }

  /* recurse on all the children */
  if (Thread > 0) do
    {
    Pid = ParentWait();
    Thread--;
    if (Pid >= 0)
      {
      if (!Queue[Pid].ChildEnd)
	{
	/* copy over data */
	if (Recurse > 0)
      	  Traverse(Queue[Pid].ChildRecurse,NULL,"called by wait",NULL,Recurse-1,&Queue[Pid].PI);
	else if (Recurse < 0)
      	  Traverse(Queue[Pid].ChildRecurse,NULL,"called by wait",NULL,Recurse,&Queue[Pid].PI);
	}
      }
    } while(Pid >= 0);

  magic_close(MagicCookie);
  if (ListOutFile)
	{
	fprintf(ListOutFile,"<summary files_regular=\"%d\" files_compressed=\"%d\" artifacts=\"%d\" directories=\"%d\" containers=\"%d\" />\n",
		TotalFiles,TotalCompressedFiles,TotalArtifacts,
		TotalDirectories,TotalContainers);
	fputs("</xml>\n",ListOutFile);
	}
  if (DB)
	{
	/* If it completes, mark it! */
	if (Upload_Pk)
	  {
	  memset(SQL,'\0',MAXSQL);
	  snprintf(SQL,MAXSQL,"UPDATE upload SET upload_mode = upload_mode | (1<<5) WHERE upload_pk = '%s';",Upload_Pk);
	  MyDBaccess(DBTREE,SQL); /* UPDATE upload */
	  }

#if 0
	/* Debugging code */
	if (DBTREE && (MyDBaccess(DBTREE,"ROLLBACK;") < 0))
#else
	if (DBTREE && (MyDBaccess(DBTREE,"COMMIT;") < 0))
#endif
	  {
	  printf("ERROR pfile %s Unable to 'COMMIT' database updates.\n",Pfile_Pk);
	  SafeExit(31);
	  }

	if (DB)
	  {
#if 0
  /** Disabled -- DB will handle this **/
	  /* Tell DB that lots of updates are done */
	  /* This has no visible benefit for small files, but after unpacking
	     a full ISO, analyze has a huge performance benefit. */
	  MyDBaccess(DB,"ANALYZE mimetype;");
	  MyDBaccess(DB,"ANALYZE pfile;");
	  MyDBaccess(DB,"ANALYZE uploadtree;");
#endif
	  /* Tell the world how many items we proudly processed */
	  /** Humans will ignore this, but the scheduler will use it. **/
	  alarm(0);
	  printf("ItemsProcessed %ld\n",TotalItems);
	  TotalItems=0;
	  fflush(stdout);
	  }

	DBclose(DB); DB=NULL;
	DBclose(DBTREE); DBTREE=NULL;
	}
  if (ListOutFile && (ListOutFile != stdout))
	{
	fclose(ListOutFile);
	}
  return(0);
} /* main() */

