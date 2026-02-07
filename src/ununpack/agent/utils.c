/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileContributor: Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Contains all utility functions used by FOSSology
**/

#include "ununpack.h"
#include "externs.h"
#include "regex.h"

/**
 * \brief File mode BITS
 */
enum BITS {
  BITS_PROJECT = 27,
  BITS_ARTIFACT = 28,
  BITS_CONTAINER = 29
};

/**
 * regular expression to detect SCM data
 */
const char* SCM_REGEX = "/\\.git|\\.hg|\\.bzr|CVS/ROOT|\\.svn/";



/**
 * @brief Test if the file is a compression bomb.
 *
 * If the size of FileName is a factor of InflateSize more than the
 * size of the directory containing it, then it is a bomb.
 * @param FileName pathname to file
 * @param InflateSize Inflation factor.
 * @return 1 on is one inflated file, 0 on is not
 */
int IsInflatedFile(char *FileName, int InflateSize)
{
  int result = 0;
  char FileNameParent[PATH_MAX];
  struct stat st, stParent;
  memcpy(FileNameParent, FileName, sizeof(FileNameParent));
  FileNameParent[PATH_MAX-1] = 0;
  char  *lastSlashPos = strrchr(FileNameParent, '/');
  if (NULL != lastSlashPos)
  {
    /* get the parent container,
       e.g. for the file ./10g.tar.bz.dir/10g.tar, partent file is ./10g.tar.bz.dir
     */
    FileNameParent[lastSlashPos - FileNameParent] = '\0';
    if (!strcmp(FileNameParent + strlen(FileNameParent) - 4, ".dir"))
    {
      /* get the parent file, must be one file
         e.g. for the file ./10g.tar.bz.dir/10g.tar, partent file is ./10g.tar.bz
       */
      FileNameParent[strlen(FileNameParent) - 4] = '\0';
      stat(FileNameParent, &stParent);
      stat(FileName, &st);
      if(S_ISREG(stParent.st_mode) && (st.st_size/stParent.st_size > InflateSize))
      {
        result = 1;
      }
    }
  }
  return result;
}


/**
 * @brief Close scheduler and database connections, then exit.
 * @param rc exit code
 * @returns no return, calls exit()
 */
void	SafeExit	(int rc)
{
  if (pgConn) PQfinish(pgConn);
  fo_scheduler_disconnect(rc);
  exit(rc);
} /* SafeExit() */

/**
 * @brief get rid of the postfix
 *
 * For example: `test.gz --> test`
 * @param[in,out] Name input file name
 */
void RemovePostfix(char *Name)
{
  if (NULL == Name) return; // exception
  // keep the part before the last dot
  char *LastDot = strrchr(Name, '.');
  if (LastDot == NULL) return;
  // if the part after the last dot is number, do not get rid of the postfix
  if ((LastDot[1]>='0')&&(LastDot[1]<='9')) return;
  if (LastDot) *LastDot = 0;
}

/**
 * @brief Initialize the metahandler CMD table.
 *
 * This ensures that:
 *  - Every mimetype is loaded
 *  - Every mimetype has an DBindex.
 */
void	InitCmd	()
{
  int i;
  PGresult *result;

  /* clear existing indexes */
  for(i=0; CMD[i].Magic != NULL; i++)
  {
    CMD[i].DBindex = -1; /* invalid value */
  }

  if (!pgConn) return; /* DB must be open */

  /* Load them up! */
  for(i=0; CMD[i].Magic != NULL; i++)
  {
    if (CMD[i].Magic[0] == '\0') continue;
    ReGetCmd:
    memset(SQL,'\0',MAXSQL);
    snprintf(SQL,MAXSQL,"SELECT mimetype_pk FROM mimetype WHERE mimetype_name = '%s';",CMD[i].Magic);
    result =  PQexec(pgConn, SQL); /* SELECT */
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(1);
    else if (PQntuples(result) > 0) /* if there is a value */
    {
      CMD[i].DBindex = atol(PQgetvalue(result,0,0));
      PQclear(result);
    }
    else /* No value, so add it */
    {
      PQclear(result);
      memset(SQL,'\0',MAXSQL);
      snprintf(SQL,MAXSQL,"INSERT INTO mimetype (mimetype_name) VALUES ('%s');",CMD[i].Magic);
      result =  PQexec(pgConn, SQL); /* INSERT INTO mimetype */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(2);
      else
      {
        PQclear(result);
        goto ReGetCmd;
      }
    }
  }
} /* InitCmd() */


/**
 * @brief Protect strings intelligently.
 *
 * Prevents filenames containing ' or % or \ from screwing
 * up system() and snprintf(). Even supports a "%s".
 * @note %s is assumed to be in single quotes!
 * @param[in,out] Dest Destination to store tainted string
 * @param DestLen Length of Dest
 * @param Src     Source string
 * @param ProtectQuotes Set to protect quotes for shell
 * @param Replace String to replace with
 * @returns 0 on success, 1 on overflow.
 **/
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
    else if (!ProtectQuotes && strchr("\\",Src[i]))
    {
      if (d+2 >= DestLen) return(1);
      Dest[d] = '\\'; d++;
      Dest[d] = Src[i]; d++;
      i++;
    }
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

/**
 * @brief Given a filename and its stat, prune it
 *
 * - Remove anything that is not a regular file or directory
 * - Remove files when hard-link count > 1 (duplicate search)
 * - Remove zero-length files
 * @returns 1=pruned, 0=no change.
 **/
int	Prune	(char *Fname, struct stat Stat)
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

/**
 * @brief Same as command-line "mkdir -p".
 * @param Fname file name
 * @returns 0 on success, 1 on failure.
 **/
int MkDirs (char *Fname)
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
          LOG_FATAL("'%s' is not a directory.",Dir);
          SafeExit(3);
        }
      }
      else /* else, it does not exist */
      {
        rc=mkdir(Dir,0770); /* create this path segment + Setgid */
        if (rc && (errno == EEXIST)) rc=0;
        if (rc)
        {
          LOG_FATAL("mkdir %s' failed, error: %s",Dir,strerror(errno));
          SafeExit(4);
        }
        chmod(Dir,02770);
      } /* else */
      Dir[i]='/';
    }
  }
  rc = mkdir(Dir,0770);	/* create whatever is left */
  if (rc && (errno == EEXIST)) rc=0;
  if (rc)
  {
    LOG_FATAL("mkdir %s' failed, error: %s",Dir,strerror(errno));
    SafeExit(5);
  }
  chmod(Dir,02770);
  return(rc);
} /* MkDirs() */

/**
 * @brief Smart mkdir.
 *
 * If mkdir fails, then try running MkDirs.
 * @param Fname file name
 * @returns 0 on success, 1 on failure.
 **/
int	MkDir	(char *Fname)
{
  if (mkdir(Fname,0770))
  {
    if (errno == EEXIST) return(0); /* failed because it exists is ok */
    return(MkDirs(Fname));
  }
  chmod(Fname,02770);
  return(0);
} /* MkDir() */

/**
 * @brief Given a filename, is it a directory?
 * @param Fname file name
 * @returns 1=yes, 0=no.
 **/
int	IsDir	(char *Fname)
{
  struct stat Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  rc = lstat(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISDIR(Stat.st_mode));
} /* IsDir() */

/**
 * @brief Given a filename, is it a file?
 * @param Fname Path of file to check
 * @param Link True if should it follow symbolic links
 * @returns 1=yes, 0=no.
 **/
int      IsFile  (char *Fname, int Link)
{
  struct stat Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  if (Link) rc = stat(Fname,&Stat);
  else rc = lstat(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISREG(Stat.st_mode));
} /* IsFile() */


/**
 * @brief Read a command from a stream.
 *
 * If the line is empty, then try again.
 * @param Fin  Input file pointer
 * @param[out] Line Output line buffer
 * @param MaxLine Max line length
 * @returns line length, or -1 of EOF.
 **/
int     ReadLine (FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  if (!Fin) return(-1);
  if (feof(Fin)) return(-1);
  memset(Line,'\0',MaxLine);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
  {
    if (C=='\n')
    {
      if (i > 0) return(i);
      /* if it is a blank line, then ignore it. */
    }
    else
    {
      Line[i]=C;
      i++;
    }
    C=fgetc(Fin);
  }
  return(i);
} /* ReadLine() */

/**
 * @brief Check if the executable exists.
 *
 * (Like the command-line "which" but without returning the path.)
 * \note This should only be used on relative path executables.
 * @param Exe Executable file name
 * @param Quiet If true, do not write warning on file not found
 * @returns 1 if exists, 0 if does not exist.
 **/
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
  if (!Quiet) LOG_WARNING("%s not found in $PATH",Exe);
  return(0); /* not in path */
} /* IsExe() */

/**
 * @brief Copy a file.
 * For speed: mmap and save.
 * @param Src Source file path
 * @param[out] Dst Destination file path
 * @returns 0 if copy worked, 1 if failed.
 **/
int	CopyFile	(char *Src, char *Dst)
{
  int Fin, Fout;
  unsigned char * Mmap;
  int LenIn, LenOut, Wrote;
  struct stat Stat;
  int rc=0;
  char *Slash;

  if (lstat(Src,&Stat) == -1) return(1);
  LenIn = Stat.st_size;
  if (!S_ISREG(Stat.st_mode))	return(1);

  Fin = open(Src,O_RDONLY);
  if (Fin == -1)
  {
    LOG_FATAL("Unable to open source '%s'",Src);
    SafeExit(22);
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
    LOG_FATAL("Unable to open target '%s'",Dst);
    close(Fin);
    SafeExit(23);
  }

  /* load the source file */
  Mmap = mmap(0,LenIn,PROT_READ,MAP_PRIVATE,Fin,0);
  if (Mmap == NULL)
  {
    LOG_FATAL("pfile %s Unable to process file.",Pfile_Pk);
    LOG_WARNING("pfile %s Mmap failed during copy.",Pfile_Pk);
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


/**
 * @brief Wait for a child. Sets child status.
 * @returns the queue record, or -1 if no more children.
 **/
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
      LOG_FATAL("Child had unnatural death");
      SafeExit(6);
    }
    Queue[i].ChildCorrupt=1;
    Status = -1;
  }
  else Status = WEXITSTATUS(Status);
  if (Status != 0)
  {
    if (!ForceContinue)
    {
      LOG_FATAL("Child had non-zero status: %d",Status);
      LOG_FATAL("Child was to recurse on %s",Queue[i].ChildRecurse);
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

/**
 * @brief Make sure all commands are usable.
 * @param Show Unused
 * @return void but updates global CMD Status
 **/
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
      case CMD_DEB:
      case CMD_ARC:
      case CMD_AR:
      case CMD_PARTITION:
      case CMD_ZSTD:
      case CMD_LZIP:
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

/**
 * @brief Try a command and return command code.
 *
 * Command becomes:
 * - `Cmd CmdPre 'File' CmdPost Out`
 * - If there is a %s, then that becomes Where.
 * @param Cmd
 * @param CmdPre
 * @param File
 * @param CmdPost
 * @param Out
 * @param Where
 * @returns -1 if command could not run.
 *************************************************/
int	RunCommand	(char *Cmd, char *CmdPre, char *File, char *CmdPost,
    char *Out, char *Where)
{
  char Cmd1[FILENAME_MAX * 5];
  char CWD[FILENAME_MAX];
  int rc;
  char TempPre[FILENAME_MAX];
  char TempFile[FILENAME_MAX];
  char TempCwd[FILENAME_MAX];
  char TempPost[FILENAME_MAX];

  if (!Cmd) return(0); /* nothing to do */

  if (Verbose)
  {
    if (Where && Out)
    {
      LOG_DEBUG("Extracting %s: %s > %s",Cmd,File,Out);
    }
    else
    {
      if (Where)
      {
        LOG_DEBUG("Extracting %s in %s: %s\n",Cmd,Where,File);
      }
      else
      {
        LOG_DEBUG("Testing %s: %s\n",Cmd,File);
      }
    }
  }

  if (getcwd(CWD,sizeof(CWD)) == NULL)
  {
    LOG_FATAL("directory name longer than %d characters",(int)sizeof(CWD));
    SafeExit(24);
  }
  if (Verbose > 1){ LOG_DEBUG("CWD: %s\n",CWD);}
  if ((Where != NULL) && (Where[0] != '\0'))
  {
    if (chdir(Where) != 0)
    {
      MkDir(Where);
      if (chdir(Where) != 0)
      {
        LOG_FATAL("Unable to access directory '%s'",Where);
        SafeExit(25);
      }
    }
    if (Verbose > 1) LOG_DEBUG("CWD: %s",Where);
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
    LOG_ERROR("Process killed by signal (%d): %s",WTERMSIG(rc),Cmd1);
    SafeExit(8);
  }
  if (WIFEXITED(rc)) rc = WEXITSTATUS(rc);
  else rc=-1;
  if (Verbose) LOG_DEBUG("in %s -- %s ; rc=%d",Where,Cmd1,rc);

  if(chdir(CWD) != 0)
    LOG_ERROR("Unable to change directory to %s", CWD);
  if (Verbose > 1) LOG_DEBUG("CWD: %s",CWD);
  return(rc);
} /* RunCommand() */


/**
 * @brief Open and load Magic file
 * Initializes global MagicCookie
 * @returns 0 on success
 **/
int InitMagic()
{
  MagicCookie = magic_open(MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    LOG_FATAL("Failed to initialize magic cookie");
    SafeExit(9);
  }
  return magic_load(MagicCookie,NULL);
}

/**
 * @brief Read file to see if it is a Debian source file
 *
 * Assumes that all Debian source files have a .dsc filename extension.
 * @param Filename File to open
 * @returns 1 if Filename is a Debian source file, else 0
 **/
int IsDebianSourceFile(char *Filename)
{
  /* Set .dsc file magic as application/x-debian-source */
  char *pExt;
  FILE *fp;
  char line[500];
  int j;
  char c;

  pExt = strrchr(Filename, '.');
  if ( pExt != NULL)
  {
    if (strcmp(pExt, ".dsc")==0)
    {
      /* read the first 500 characters of the file to verify that
      * it really is a debian source file
      */
      if ((fp = fopen(Filename, "r")) == NULL) return 0;
      j=0;
      while ((c = fgetc(fp)) != EOF && j < 500 ){
        line[j]=c;
        j++;
      }
      fclose(fp);
      if ((strstr(line, "-----BEGIN PGP SIGNED MESSAGE-----") && strstr(line,"Source:")) ||
          (strstr(line, "Format:") && strstr(line, "Source:") && strstr(line, "Version:")))
      {
        return 1;
      }
    }
  }
  return 0;
}

/**
 * @brief Figure out the real type of "octet" files in case we can unarchive them.
 * @param Filename
 * @param Static buffer to return with new Type
 **/
void OctetType(char *Filename, char *TypeBuf)
{
  int rc1, rc2, rc3;
  char *Type;

  /* Get more information from magic */
  magic_setflags(MagicCookie, MAGIC_NONE);
  Type = (char *)magic_file(MagicCookie, Filename);
  /* reset magic flags */
  magic_setflags(MagicCookie, MAGIC_MIME);

  /* .deb and .udeb as application/x-debian-package*/
  if (strstr(Type, "Debian binary package"))
  {
    strcpy(TypeBuf,"application/x-debian-package");
    return;
  }

  if (strstr(Type, "ISO 9660"))
  {
    strcpy(TypeBuf,"application/x-iso9660-image");
    return;
  }

  /* 7zr can handle many formats (including isos), so try this first */
  rc1 = RunCommand("7z","l -y ",Filename,">/dev/null 2>&1",NULL,NULL);
  rc2 = RunCommand("7z","t -y -pjunk",Filename,">/dev/null 2>&1",NULL,NULL);
  if(rc2!=0)
  {
    rc3 = RunCommand("7z","t -y -pjunk",Filename,"|grep 'Wrong password' >/dev/null 2>&1",NULL,NULL);
    if(rc3==0)
    {
      LOG_ERROR("'%s' cannot be unpacked, password required.",Filename);
      return;
    }
  }
  if ((rc1 || rc2)==0)
  {
    strcpy(TypeBuf,"application/x-7z-w-compressed");
    return;
  }

  if (strstr(Type, " ext2 "))
  {
    strcpy(TypeBuf,"application/x-ext2");
    return;
  }

  if (strstr(Type, " ext3 "))
  {
    strcpy(TypeBuf,"application/x-ext3");
    return;
  }

  if (strstr(Type, "x86 boot sector, mkdosfs")) /* the file type is FAT */
  {
    strcpy(TypeBuf,"application/x-fat");
    return;
  }

  if (strstr(Type, "NTFS")) /* the file type is NTFS */
  {
    strcpy(TypeBuf,"application/x-ntfs");
    return;
  }

  if (strstr(Type, "x86 boot")) /* the file type is boot partition */
  {
    strcpy(TypeBuf,"application/x-x86_boot");
    return;
  }
}

/**
 * @brief Given a file name, determine the type of
 *        extraction command.  This uses Magic.
 * @returns index to command-type, or -1 on error.
 **/
int	FindCmd	(char *Filename)
{
  char *Type;
  char TypeBuf[256];
  int Match;
  int i;
  int rc;

  if (!MagicCookie) InitMagic();
  TypeBuf[0] = 0;

  Type = (char *)magic_file(MagicCookie,Filename);
  if (Type == NULL) return(-1);

  /* Windows executables look like archives and 7z will attempt to unpack them.
   * If that happens there will be a .bss file representing the .bss segment.
   * 7z will try to unpack this further, potentially getting into an infinite
   * unpack loop.  So if you see an octet type .bss file, consider it text
   * to avoid this problem.  This problem was first noticed on papi-4.1.3-3.el6.src.rpm
   */
  if ((strcmp(basename(Filename), ".bss") == 0) && (strstr(Type, "octet")))
  {
    Type = strdup("text/plain");
  }

  /* The Type returned by magic_file needs to be verified and possibly rewritten.
   * So save it in a static buffer.
   */
  strncpy(TypeBuf, Type, sizeof(TypeBuf));
  TypeBuf[255] = 0;  /* make sure TypeBuf is null terminated */

  if (strstr(Type, "octet" ))
  {
    OctetType(Filename, TypeBuf);
  }
  else
  if (IsDebianSourceFile(Filename)) strcpy(TypeBuf,"application/x-debian-source");
  else
  if (strstr(Type, "msword") || strstr(Type, "vnd.ms"))
     strcpy(TypeBuf, "application/x-7z-w-compressed");
  else
  /* some files you just have to try to verify their type */
  if (strstr(Type, "application/x-exe") ||
      strstr(Type, "application/x-shellscript"))
  {
    rc = RunCommand("unzip","-q -l",Filename,">/dev/null 2>&1",NULL,NULL);
    if ((rc==0) || (rc==1) || (rc==2) || (rc==51))
    {
      strcpy(TypeBuf,"application/x-zip");
    }
  } /* if was x-exe */
  else if (strstr(Type, "application/x-tar"))
  {
    if (RunCommand("tar","-tf",Filename,">/dev/null 2>&1",NULL,NULL) != 0)
      return(-1); /* bad tar! (Yes, they do happen) */
  } /* if was x-tar */

  /* Match Type (mimetype from magic or from special processing above to determine
   * the command for Filename
   */
  Match=-1;
  for(i=0; (CMD[i].Cmd != NULL) && (Match == -1); i++)
  {
    if (CMD[i].Status == 0) continue; /* cannot check */
    if (CMD[i].Type == CMD_DEFAULT)
    {
      Match=i; /* done! */
    }
    else
      if (!strstr(TypeBuf, CMD[i].Magic))
      {
        continue; /* not a match */
      }
    Match=i;
  }

  if (Verbose > 0)
  {
    /* no match */
    if (Match == -1)
    {
      LOG_DEBUG("MISS: Type=%s  %s",TypeBuf,Filename);
    }
    else
    {
      LOG_DEBUG("MATCH: Type=%d  %s %s %s %s",CMD[Match].Type,CMD[Match].Cmd,CMD[Match].CmdPre,Filename,CMD[Match].CmdPost);
    }
  }
  return(Match);
} /* FindCmd() */

/***************************************************************************/
/***************************************************************************/
/*** File Processing ***/
/***************************************************************************/
/***************************************************************************/

/**
 * @brief Free a list of files in a directory list.
 * @param DL directory list
 **/
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

/**
 * @brief Create a list of files in a directory.
 * @param Fullname Path to top level directory.
 * @returns the directory list
 **/
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
      LOG_FATAL("Failed to allocate dirlist memory");
      SafeExit(10);
    }
    dhead->Name = (char *)malloc(strlen(Entry->d_name)+1);
    if (!dhead->Name)
    {
      LOG_FATAL("Failed to allocate dirlist.Name memory");
      SafeExit(11);
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

/**
 * @brief  Set a destination directory name.
 *
 * This will concatenate Smain and Sfile, but remove
 * and terminating filename.
 * @param[in,out] Dest returned directory name
 * @param DestLen size of Dest
 * @param Smain main extraction directory (may be null)
 * @param Sfile filename
 **/
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


/**
 * @brief Print a ContainerInfo structure.
 * @param CI ContainerInfo struct to print
 **/
void	DebugContainerInfo	(ContainerInfo *CI)
{
  LOG_DEBUG("Container:");
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

/**
 * @brief Insert a Pfile record.
 *        Sets the pfile_pk in CI.
 * @param CI
 * @param Fuid string of sha1.md5.size
 * @returns 1 if record exists, 0 if record does not exist.
 **/
int	DBInsertPfile	(ContainerInfo *CI, char *Fuid)
{
  PGresult *result;
  char *Val; /* string result from SQL query */
  long tempMimeType; ///< Temporary storage for mimetype fk from DB
  char *tempSha256; ///< Temporary storage for pfile_sha256 from DB

  /* idiot checking */
  if (!Fuid || (Fuid[0] == '\0')) return(1);

  /* Check if the pfile exists */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pfile_pk,pfile_mimetypefk,pfile_sha256 FROM pfile "
      "WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
      Fuid,Fuid+41,Fuid+140);
  result =  PQexec(pgConn, SQL); /* SELECT */
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(12);

  /* add it if it was not found */
  if (PQntuples(result) == 0)
  {
    /* blindly insert to pfile table in database (don't care about dups) */
    /* If TWO ununpacks are running at the same time, they could both
        create the same pfile at the same time. Ignore the dup constraint. */
    PQclear(result);
    memset(SQL,'\0',MAXSQL);
    if (CMD[CI->PI.Cmd].DBindex > 0)
    {
      snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_sha256,pfile_size,pfile_mimetypefk) "
               "VALUES ('%.40s','%.32s','%.64s','%s','%ld');",
          Fuid,Fuid+41,Fuid+74,Fuid+140,CMD[CI->PI.Cmd].DBindex);
    }
    else
    {
      snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_sha256,pfile_size) VALUES ('%.40s','%.32s','%.64s','%s');",
          Fuid,Fuid+41,Fuid+74,Fuid+140);
    }
    result =  PQexec(pgConn, SQL); /* INSERT INTO pfile */
    // ignore duplicate constraint failure (23505), report others
    if ((result==0) || ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
        (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5))))
    {
      LOG_ERROR("Error inserting pfile, %s.", SQL);
      SafeExit(13);
    }
    PQclear(result);

    /* Now find the pfile_pk.  Since it might be a dup, we cannot rely
       on currval(). */
    memset(SQL,'\0',MAXSQL);
    snprintf(SQL,MAXSQL,"SELECT pfile_pk,pfile_mimetypefk,pfile_sha256 FROM pfile "
        "WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_sha256 = '%.64s' AND pfile_size = '%s';",
        Fuid,Fuid+41,Fuid+74,Fuid+140);
    result =  PQexec(pgConn, SQL);  /* SELECT */
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(14);
  }

  /* Now *DB contains the pfile_pk information */
  Val = PQgetvalue(result,0,0);
  if (Val)
  {
    CI->pfile_pk = atol(Val);
    if (Verbose) LOG_DEBUG("pfile_pk = %ld",CI->pfile_pk);
    tempMimeType = atol(PQgetvalue(result,0,1));
    tempSha256 = PQgetvalue(result,0,2);
    /* For backwards compatibility... Do we need to update the mimetype? */
    if ((CMD[CI->PI.Cmd].DBindex > 0) &&
        ((tempMimeType != CMD[CI->PI.Cmd].DBindex)))
    {
      PQclear(result);
      memset(SQL,'\0',MAXSQL);
      snprintf(SQL,MAXSQL,"UPDATE pfile SET pfile_mimetypefk = '%ld' WHERE pfile_pk = '%ld';",
          CMD[CI->PI.Cmd].DBindex, CI->pfile_pk);
      result =  PQexec(pgConn, SQL); /* UPDATE pfile */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(16);
    }
    /* Update the SHA256 for the pfile if it does not exists */
    if (strncasecmp(tempSha256, Fuid+74, 64) != 0)
    {
      PQclear(result);
      memset(SQL,'\0',MAXSQL);
      snprintf(SQL,MAXSQL,"UPDATE pfile SET pfile_sha256 = '%.64s' WHERE pfile_pk = '%ld';",
          Fuid+74, CI->pfile_pk);
      result =  PQexec(pgConn, SQL); /* UPDATE pfile */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(16);
    }
    PQclear(result);
  }
  else
  {
    PQclear(result);
    CI->pfile_pk = -1;
    return(0);
  }

  return(1);
} /* DBInsertPfile() */

/**
 * @brief Search for SCM data in the filename
 *
 * SCM data is one of these:
 *   Git (.git)Data(char *FileName)
 *   Mercurial (.hg)
 *   Bazaar (.bzr)
 *   CVS (CVS/Root)
 *   Subversion (.svn)
 * @param sourcefilename
 * @returns 1 if SCM data is found
 **/
int TestSCMData(char *sourcefilename)
{
  regex_t preg;
  int err;
  int found=0;

  err = regcomp (&preg, SCM_REGEX, REG_NOSUB | REG_EXTENDED);
  if (err == 0)
  {
    int match;

    match = regexec (&preg, sourcefilename, 0, NULL, 0);
    regfree (&preg);
    if(match == 0)
    {
      found = 1;
      if (Verbose) LOG_DEBUG("match found %s",sourcefilename);
    }
    else if(match == REG_NOMATCH)
    {
      found = 0;
      if (Verbose) LOG_DEBUG("match not found %s",sourcefilename);
    }
    else
    {
      char *text;
      size_t size;
      size = regerror (err, &preg, NULL, 0);
      text = malloc (sizeof (*text) * size);
      if(text)
      {
        regerror (err, &preg, text, size);
        LOG_ERROR("Error regexc '%s' '%s' return %d, error %s",SCM_REGEX,sourcefilename,match,text);
      }
      else
      {
        LOG_ERROR("Not enough memory (%lu)",sizeof (*text) * size);
        SafeExit(127);
      }
      found = 0;
    }
  }
  else
  {
     LOG_ERROR("Error regcomp(%d)",err);
     SafeExit(127);
  }


  return(found);
} /* TestSCMData() */

/**
 * @brief Insert an UploadTree record.
 *
 * If the tree is a duplicate, then we need to replicate
 * all of the uploadtree records for the tree.
 * This uses Upload_Pk.
 * @param CI
 * @param Mask mask file mode for ufile_mode
 * @returns 1 if tree exists for some other project (duplicate) and 0 if tree does not exist.
 **/
int	DBInsertUploadTree	(ContainerInfo *CI, int Mask)
{
  char UfileName[1024];
  char *cp;
  PGresult *result;
  char EscBuf[1024];
  int  error;

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
    result =  PQexec(pgConn, UfileName);
    if (fo_checkPQresult(pgConn, result, UfileName, __FILE__, __LINE__)) SafeExit(17);
    memset(UfileName,'\0',sizeof(UfileName));
    ufile_name = PQgetvalue(result,0,0);
    PQclear(result);
    if (strchr(ufile_name,'/')) ufile_name = strrchr(ufile_name,'/')+1;
    strncpy(CI->Partname,ufile_name,sizeof(CI->Partname)-1);
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
    strncpy(CI->Partname,UfileName,sizeof(CI->Partname)-1);
  }

  PQescapeStringConn(pgConn, EscBuf, CI->Partname, strlen(CI->Partname), &error);
  if (error)
  {
      LOG_WARNING("Error escaping filename with multibyte character set (%s).", CI->Partname);
  }
  else
  {
    strncpy(UfileName, EscBuf, sizeof(UfileName));
  }

  /*
   * Tests for SCM Data: IgnoreSCMData is global and defined in ununpack_globals.h with false value
   * and pass to true if ununpack is called with -I option to ignore SCM data.
   * So if IgnoreSCMData is false the right test is true.
   * Otherwise if IgnoreSCMData is true and CI->Source is not a SCM data then add it in database.
  */
  if(ReunpackSwitch && ((IgnoreSCMData && !TestSCMData(CI->Source)) || !IgnoreSCMData))
  {
    /* postgres 8.3 seems to have a problem escaping binary characters
     * (it works in 8.4).  So manually substitute '~' for any unprintable and slash chars.
     * Updated to handle UTF-8 characters properly by checking for valid UTF-8 sequences
     * instead of using isprint() which only works with ASCII characters.
     */
    for (cp=UfileName; *cp; cp++) {
      unsigned char c = (unsigned char)*cp;
      /* Replace control characters (ASCII 0-31) and path separators */
      if ((c < 32 && c != '\t' && c != '\n' && c != '\r') || (*cp=='/') || (*cp=='\\')) {
        *cp = '~';
      }
      /* All other characters (including UTF-8 sequences) are kept as-is */
    }

    /* Get the parent ID */
    /* Two cases -- depending on if the parent exists */
    memset(SQL,'\0',MAXSQL);
    if (CI->PI.uploadtree_pk > 0) /* This is a child */
    {
      /* Prepare to insert child */
      snprintf(SQL,MAXSQL,"INSERT INTO %s (parent,pfile_fk,ufile_mode,ufile_name,upload_fk) VALUES (%ld,%ld,%ld,E'%s',%s);",
          uploadtree_tablename, CI->PI.uploadtree_pk, CI->pfile_pk, CI->ufile_mode,
          UfileName, Upload_Pk);
      result =  PQexec(pgConn, SQL); /* INSERT INTO uploadtree */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
      {
        SafeExit(18);
      }
      PQclear(result);
    }
    else /* No parent!  This is the first upload! */
    {
      snprintf(SQL,MAXSQL,"INSERT INTO %s (upload_fk,pfile_fk,ufile_mode,ufile_name) VALUES (%s,%ld,%ld,E'%s');",
          uploadtree_tablename, Upload_Pk, CI->pfile_pk, CI->ufile_mode, UfileName);
      result =  PQexec(pgConn, SQL); /* INSERT INTO uploadtree */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(19);
      PQclear(result);
    }
    /* Find the inserted child */
    memset(SQL,'\0',MAXSQL);
    snprintf(SQL,MAXSQL,"SELECT currval('uploadtree_uploadtree_pk_seq');");
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(20);
    CI->uploadtree_pk = atol(PQgetvalue(result,0,0));
    PQclear(result);
  }
  TotalItems++;
  fo_scheduler_heart(1);
  return(0);
} /* DBInsertUploadTree() */

/**
 * @brief Add a ContainerInfo record to the
 *        repository AND to the database.
 *
 * This modifies the CI record's pfile and ufile indexes!
 * @param CI
 * @param Fuid sha1.md5.sha256.size
 * @param Mask file mode mask
 * @returns 1 if added, 0 if already exists!
 **/
int	AddToRepository	(ContainerInfo *CI, char *Fuid, int Mask)
{
  int IsUnique = 1;  /* is it a DB replica? */

  /*****************************************/
  /* populate repository (include artifacts) */
  /* If we ever want to skip artifacts, use && !CI->Artifact */
  if ((Fuid[0]!='\0') && UseRepository)
  {
    /* Translate the new Fuid into old Fuid */
    char FuidNew[1024];
    memset(FuidNew, '\0', sizeof(FuidNew));
    // Copy the value till md5
    strncpy(FuidNew, Fuid, 74);
    // Copy the size of the file
    strcat(FuidNew,Fuid+140);

    /* put file in repository */
    if (!fo_RepExist(REP_FILES,Fuid))
    {
      if (fo_RepImport(CI->Source,REP_FILES,FuidNew,1) != 0)
      {
        LOG_ERROR("Failed to import '%s' as '%s' into the repository",CI->Source,FuidNew);
        SafeExit(21);
      }
    }
    if (Verbose) LOG_DEBUG("Repository[%s]: insert '%s' as '%s'",
        REP_FILES,CI->Source,FuidNew);
  }

  /* PERFORMANCE NOTE:
     I used to use and INSERT and an UPDATE.
     Turns out, INSERT is fast, UPDATE is *very* slow (10x).
     Now I just use an INSERT.
   */

  /* Insert pfile record */
  if (pgConn && Upload_Pk)
  {
    if (!DBInsertPfile(CI,Fuid)) return(0);
    /* Update uploadtree table */
    IsUnique = !DBInsertUploadTree(CI,Mask);
  }

  if (ForceDuplicate) IsUnique=1;
  return(IsUnique);
} /* AddToRepository() */

/**
 * @brief Print what can be printed in XML.
 * @param CI
 * @param Cmd Command used to create this file (parent)
 *            CI->Cmd = command to be used ON this file (child)
 * @returns 1 if item is unique, 0 if duplicate.
 **/
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
      /* commented out since almost anything can screw this up. */
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
    char SHA256[65];

    memset(SHA256, '\0', sizeof(SHA256));

    CF = SumOpenFile(CI->Source);
    if(calc_sha256sum(CI->Source, SHA256))
    {
        LOG_FATAL("Unable to calculate SHA256 of %s\n", CI->Source);
        SafeExit(56);
    }

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
        for(i=0; i<64; i++) { sprintf(Fuid+74+i,"%c",SHA256[i]); }
        Fuid[139]='.';
        snprintf(Fuid+140,sizeof(Fuid)-140,"%Lu",(long long unsigned int)Sum->DataLen);
        if (ListOutFile) fprintf(ListOutFile,"fuid=\"%s\" ",Fuid);
        free(Sum);
      } /* if Sum */
    } /* if CF */
    else /* file too large to mmap (probably) */
    {
      FILE *Fin;
      Fin = fopen(CI->Source,"rb");
      if (Fin)
      {
        Sum = SumComputeFile(Fin);
        if (Sum)
        {
          for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",Sum->SHA1digest[i]); }
          Fuid[40]='.';
          for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",Sum->MD5digest[i]); }
          Fuid[73]='.';
          for(i=0; i<64; i++) { sprintf(Fuid+74+i,"%c",SHA256[i]); }
          Fuid[139]='.';
          snprintf(Fuid+140,sizeof(Fuid)-140,"%Lu",(long long unsigned int)Sum->DataLen);
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

/**
 * @brief Remove all files under dirpath (rm -rf)
 * @param dirpath
 * @returns shell exit code of rm -rf
 **/
int RemoveDir(char *dirpath)
{
  char RMcmd[FILENAME_MAX];
  int rc;
  memset(RMcmd, '\0', sizeof(RMcmd));
  snprintf(RMcmd, FILENAME_MAX -1, "rm -rf '%s' ", dirpath);
// nokill = fo_scheduler_get_special(SPECIAL_NOKILL);
  rc = system(RMcmd);
//  fo_scheduler_set_special(SPECIAL_NOKILL, nokill);
  return rc;
} /* RemoveDir() */


/**
 * @brief Check if path contains a "%U" or "%H". If so, substitute a unique ID for %U.
 *
 * This substitution parameter must be at the end of the DirPath.
 * Substitute hostname for %H.
 * @parm DirPath Directory path.
 * @returns new directory path
 **/
char *PathCheck(char *DirPath)
{
  char *NewPath;
  char *subs;
  char  TmpPath[2048];
  char  HostName[2048];
  struct timeval time_st;

  NewPath = strdup(DirPath);

  if ((subs = strstr(NewPath,"%U")) )
  {
    /* dir substitution */
    if (gettimeofday(&time_st, 0))
    {
      /* gettimeofday failure */
      LOG_WARNING("gettimeofday() failure.");
      time_st.tv_usec = 999999;
    }

    *subs = 0;
    snprintf(TmpPath, sizeof(TmpPath), "%s%ul", NewPath, (unsigned)time_st.tv_usec);
    free(NewPath);
    NewPath = strdup(TmpPath);
  }

  if ((subs = strstr(NewPath,"%H")) )
  {
    /* hostname substitution */
    gethostname(HostName, sizeof(HostName));

    *subs = 0;
    snprintf(TmpPath, sizeof(TmpPath), "%s%s%s", NewPath, HostName, subs+2);
    free(NewPath);
    NewPath = strdup(TmpPath);
  }
#ifndef STANDALONE
  if ((subs = strstr(NewPath, "%R")) )
  {
    /* repo location substitution */
    *subs = 0;

    snprintf(TmpPath, sizeof(TmpPath), "%s%s%s", NewPath, fo_config_get(sysconfig, "FOSSOLOGY", "path", NULL), subs+2);
    free(NewPath);
    NewPath = strdup(TmpPath);
  }
#endif
  return(NewPath);
}

void deleteTmpFiles(char *dir)
{
  if (strcmp(dir, ".")) RemoveDir(dir);
}


/**
 * @brief Display program usage.
 * @param Name program name
 * @param Version program version
 **/
void	Usage	(char *Name, char *Version)
{
  fprintf(stderr,"Universal Unpacker, %s, compiled %s %s\n",
      Version,__DATE__,__TIME__);
  fprintf(stderr,"Usage: %s [options] file [file [file...]]\n",Name);
  fprintf(stderr,"  Extracts each file.\n");
  fprintf(stderr,"  If filename specifies a directory, then extracts everything in it.\n");
  fprintf(stderr," Unpack Options:\n");
  fprintf(stderr,"  -h     :: help (print this message), then exit.\n");
  fprintf(stderr,"  -C     :: force continue when unpack tool fails.\n");
  fprintf(stderr,"  -d dir :: specify alternate extraction directory. %%U substitutes a unique ID.\n");
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
  fprintf(stderr,"  -I     :: Ignore SCM Data.\n");
  fprintf(stderr,"  -Q     :: Using scheduler queue system. (Includes -F)\n");
  fprintf(stderr,"            If -L is used, unpacked files are placed in 'files'.\n");
  fprintf(stderr,"      -T rep :: Set gold repository name to 'rep' (for testing)\n");
  fprintf(stderr,"      -t rep :: Set files repository name to 'rep' (for testing)\n");
  fprintf(stderr,"      -A     :: do not set the initial DB container as an artifact.\n");
  fprintf(stderr,"      -f     :: force processing files that already exist in the DB.\n");
  fprintf(stderr,"  -q     :: quiet (generate no output).\n");
  fprintf(stderr,"  -U upload_pk :: upload to unpack (implies -RQ). Writes to db.\n");
  fprintf(stderr,"  -v     :: verbose (-vv = more verbose).\n");
  fprintf(stderr,"  -V     :: print the version info, then exit.\n");
  fprintf(stderr,"Currently identifies and processes:\n");
  fprintf(stderr,"  Compressed files: .Z .gz .bz .bz2 upx\n");
  fprintf(stderr,"  Archives files: tar cpio zip jar ar rar cab\n");
  fprintf(stderr,"  Data files: pdf\n");
  fprintf(stderr,"  Installer files: rpm deb\n");
  fprintf(stderr,"  File images: iso9660(plain/Joliet/Rock Ridge) FAT(12/16/32) ext2/ext3 NTFS\n");
  fprintf(stderr,"  Boot partitions: x86, vmlinuz\n");
  CheckCommands(Quiet);
} /* Usage() */

/**
 * @brief Dummy postgresql notice processor.
 *        This prevents Notices from being written to stderr.
 * @param arg unused
 * @param message unused
 **/
 void SQLNoticeProcessor(void *arg, const char *message)
 {
 }

/**
 * \brief Determines if a file or folder should be excluded.
 *
 * This function checks whether the supplied file name, `Filename`, contains any of the
 * substrings listed in the comma-separated string `ExcludePatterns`. Each pattern is matched
 * directly as a substring; no wildcard or directory-specific matching is performed.
 *
 * \param Filename The name of the file or folder to be examined.
 * \param ExcludePatterns A comma-separated list of substrings used for determining exclusion.
 * \returns 1 if a substring match is found (folder is to be excluded), or 0 otherwise.
 */
 int ShouldExclude(char *Filename, const char *ExcludePatterns)
 {
   if (!ExcludePatterns || !Filename) return 0;

   char *patternsCopy = strdup(ExcludePatterns);
   if (!patternsCopy) return 0;

   char *pattern = strtok(patternsCopy, ",");
   while (pattern != NULL) {
     if (strstr(Filename, pattern)) {
       if (Verbose) LOG_DEBUG("Excluding: %s (matched substring: %s)", Filename, pattern);
       free(patternsCopy);
       return 1;
     }
     pattern = strtok(NULL, ",");
   }
   free(patternsCopy);
   return 0;
 }
