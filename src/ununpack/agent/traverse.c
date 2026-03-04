/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief Traverse directories
 */

#include "ununpack.h"
#include "externs.h"

/**
 * \brief Find all files (assuming a directory)
 *        and process (unpack) all of them.
 * \param Filename Pathname of file to process
 * \param Label String displayed by debug messages
 * \param NewDir Optional, specifies an alternate directory to extract to.
 * \param Recurse >0 to recurse
 * \param ExcludePatterns Patterns to exclude directories.
 **/
void TraverseStart(char *Filename, char *Label, char *NewDir, int Recurse, char *ExcludePatterns)
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
    Traverse(Filename,Basename,Label,NewDir,Recurse,&PI,ExcludePatterns);
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
      TraverseStart(Name,Label,NewDir,Recurse, ExcludePatterns);
    }
    FreeDirList(DLhead);
  }

  /* remove anything that we needed to create */
  if (UnlinkAll)
  {
    struct stat Src,Dst;
    int i;
    /* build the destination name */
    SetDir(Name,sizeof(Name),NewDir,Basename);
    for(i=strlen(Filename)-1; (i>=0) && (Filename[i] != '/'); i--)
      ;
    if (strcmp(Filename+i+1,".")) strcat(Name,Filename+i+1);
    lstat(Filename,&Src);
    lstat(Name,&Dst);
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


/**
 * \brief Called by exec'd child to process.
 *
 * The child never leaves here! It calls EXIT!
 * Exit is 0 on success, non-zero on failure.
 * \param Index Child index into the queue table
 * \param CI The ContainerInfo
 * \param NewDir Optional, specifies an alternate directory to extract to.
 **/
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
      /**
       * If the file processed is one original uploaded file and it is an archive,
       * also using repository, need to reset the file name in the archive,
       * if do not reset, for the gz Z bz2 archives, the file name in the archive is
       * sha1.md5.size file name, that is:
       *
       * For example:
       * \code
       * argmatch.c.gz    ---> CI->Source  --->
       * 657db64230b9d647362bfe0ebb82f7bd1d879400.a0f2e4d071ba2e68910132a8da5784a6.292
       * CI->PartnameNew --->
       * 657db64230b9d647362bfe0ebb82f7bd1d879400.a0f2e4d071ba2e68910132a8da5784a6
       * \endcode
       * so in order to get the original file name(CI->PartnameNew): we need get the
       * upload archive name first, then get rid of the postfix.
       *
       * For example:
       * for test.gz, get rid of .gz, get the original file name 'test',
       * replace sha1.md5.size file name with 'test'.
       */
      if (CI->TopContainer && UseRepository)
      {
        RemovePostfix(UploadFileName);
        memcpy(CI->PartnameNew, UploadFileName, sizeof(CI->PartnameNew));
        CI->PartnameNew[sizeof(CI->PartnameNew)-1] = 0;
      }
      else
        /**
         * If the file processed is a sub-archive, in the other words, it is part of other archive,
         * or not using repository, need get rid of the postfix
         * two time, for example:
         * 1. for test.tar.gz, it is in test.rpm, when test.tar.gz is unpacked,
         * the name of unpacked file should be test.tar under test.tar.gz.dir, but it is
         * test.tar.gz.dir, so do as below:
         * test.tar.gz.dir-->test.tar.gz-->test.tar,
         * 2. for metahandle.tab.bz2, it is one top archive, when metahandle.tab.bz2 is unpacked,
         * the name of unpacked file should be metahandle.tab, so do as below:
         * metahandle.tab.bz2.dir-->metahandle.tab.bz2-->metahandle.tab,
         */
      {
        RemovePostfix(CI->PartnameNew);
        RemovePostfix(CI->PartnameNew);
      }

      /* unpack in a sub-directory */
      rc=RunCommand(CMD[CI->PI.Cmd].Cmd,CMD[CI->PI.Cmd].CmdPre,CI->Source,
          CMD[CI->PI.Cmd].CmdPost,CI->PartnameNew,Queue[Index].ChildRecurse);
      break;
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
        LOG_WARNING("pfile %s Minor zip error(%d)... ignoring error.",Pfile_Pk,rc)
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
    case CMD_DEB:
      /* unpack a DEBIAN source:*/
      rc=RunCommand(CMD[CI->PI.Cmd].Cmd,CMD[CI->PI.Cmd].CmdPre,CI->Source,
          CMD[CI->PI.Cmd].CmdPost,CI->PartnameNew,CI->Partdir);
      break;
    case CMD_ZSTD:
      /* unpack a ZSTD: source file, source name and destination directory */
      rc = ExtractZstd(CI->Source, CI->Partname, Queue[Index].ChildRecurse);
      break;
    case CMD_LZIP:
      /* unpack an LZIP: source file and destination directory */
      rc = ExtractLzip(CI->Source, CI->Partname, Queue[Index].ChildRecurse);
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
    /* Note: CMD_DEFAULT will never get here because rc==0 */
    if (strstr(CMD[CI->PI.Cmd].Cmd, "unzip") && (rc == 82))
    {
      LOG_ERROR("pfile %s Command %s failed on: %s, Password required.",
        Pfile_Pk, CMD[CI->PI.Cmd].Cmd, CI->Source);
    }
    else
    {
      LOG_ERROR("pfile %s Command %s failed on: %s",
        Pfile_Pk, CMD[CI->PI.Cmd].Cmd, CI->Source);
    }
    if (ForceContinue) rc=-1;
  }
  exit(rc);
} /* TraverseChild() */


/**
 * \brief Count the number of times Dirname appears in Pathname
 *        This is used to limit recursion in test archives that infinitely recurse.
 * \param Pathname Pathname of file to process
 * \param Dirname  Directory name to search for
 * \return number of occurances of Dirname in Pathname
 **/
int CountFilename(char *Pathname, char *Dirname)
{
  char *FullDirname;  /** Full Dirname (includes forward and trailing slashes and null terminator) */
  char *sp;
  int   FoundCount = 0;
  int   NameLen;

  FullDirname = calloc(strlen(Dirname) + 3, sizeof(char));
  sprintf(FullDirname, "/%s/", Dirname);
  NameLen = strlen(FullDirname);

  sp = Pathname;
  while (((sp = strstr(sp, FullDirname)) != NULL))
  {
    FoundCount++;
    sp += NameLen;
  }

  free(FullDirname);
  return(FoundCount);
}


/**
 * \brief Find all files, traverse all directories.
 *        This is a depth-first search, in inode order!
 * \param Filename Pathname of file to process
 * \param Basename Optional basename() of Filename
 * \param Label is used for debugging.
 * \param NewDir  Optional, specifies an alternate directory to extract to.
 *                Default (NewDir==NULL) is to extract to the same directory
 *                as Filename.
 * \param Recurse >0 to recurse
 * \param PI ParentInfo
 * \return 1 if Filename was a container, 0 if not a container.
 *        (The return value is really only used by TraverseStart().)
 **/
int	Traverse	(char *Filename, char *Basename,
    char *Label, char *NewDir, int Recurse, ParentInfo *PI, char *ExcludePatterns)
{
  if (ShouldExclude(Filename, ExcludePatterns)) {
    if (Verbose) LOG_DEBUG("Skipping excluded file or folder: %s", Filename);
    return 0;
  }
  int rc;
  PGresult *result;
  int i;
  ContainerInfo CI,CImeta;
  int IsContainer=0;
  int RecurseOk=1;	/* should it recurse? (only on unique inserts) */
  int MaxRepeatingName = 3;

  if (!Filename || (Filename[0]=='\0')) return(IsContainer);
  if (Verbose > 0) LOG_DEBUG("Traverse(%s) -- %s",Filename,Label)

  /* to prevent infinitely recursive test archives, count how many times the basename
     occurs in Filename.  If over MaxRepeatingName, then return 0
   */
  if (CountFilename(Filename, basename(Filename)) >= MaxRepeatingName)
  {
    LOG_NOTICE("Traverse() recursion terminating due to max directory repetition: %s", Filename);
    return 0;
  }

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

  rc = lstat(Filename,&CI.Stat);

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
    LOG_ERROR("pfile %s \"%s\" DOES NOT EXIST!!!",Pfile_Pk,Filename)
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
        Traverse(CI.Partdir,NULL,"Called by dir",NULL,Recurse,&(CI.PI), ExcludePatterns);
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
    if(IsInflatedFile(CI.Source, 1000)) return 0; // if the file is one compression bombs, do not unpack this file

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

    /* if it made it this far, then it's spawning time! */
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
      case CMD_PACK:
      case CMD_ZSTD:
      case CMD_LZIP:
        CI.HasChild=1;
        IsContainer=1;
        strcat(Queue[Index].ChildRecurse,".dir");
        strcat(CI.PartnameNew,".dir");
        Queue[Index].PI.ChildRecurseArtifact=1;
        /* make the directory */
        if (MkDir(Queue[Index].ChildRecurse))
        {
          LOG_FATAL("Unable to mkdir(%s) in Traverse", Queue[Index].ChildRecurse)
          if (!ForceContinue)
          {
            SafeExit(30);
          }
        }
        if (CMD[CI.PI.Cmd].Type == CMD_PARTITION)
          Queue[Index].PI.ChildRecurseArtifact=2;

        /* get the upload file name */
        /* if the type of the upload file is CMD_PACK, and is top container,
         * and using repository, then get the upload file name from DB
         */
        if (CMD_PACK == CMD[CI.PI.Cmd].Type && CI.TopContainer && UseRepository)
        {
          char *UFileName;
          char SQL[MAXSQL];
          snprintf(SQL, MAXSQL,"SELECT upload_filename FROM upload WHERE upload_pk = %s;",Upload_Pk);
          result =  PQexec(pgConn, SQL);  // get name of the upload file
          if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
          {
            SafeExit(31);
          }
          UFileName = PQgetvalue(result,0,0);
          PQclear(result);
          if (strchr(UFileName, '/')) UFileName = strrchr(UFileName, '/') + 1;
          memset(UploadFileName, '\0', FILENAME_MAX);
          strncpy(UploadFileName, UFileName, FILENAME_MAX - 1);
        }

        break;
      case CMD_DEB:
      case CMD_RPM:
        CI.HasChild=1;
        IsContainer=1;
        strcat(Queue[Index].ChildRecurse,".unpacked");
        strcat(CI.PartnameNew,".unpacked");
        Queue[Index].PI.ChildRecurseArtifact=1;
        if (CMD[CI.PI.Cmd].Type == CMD_PACK)
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
      /* This needs to call AddToRepository() or DisplayContainerInfo() */
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
      /* this gets past any permission problems with read-only files */
      unlink(CImeta.Source);

      /* build the command and run it */
      sprintf(Cmd,CMD[CI.PI.Cmd].MetaCmd,Fname,CImeta.Source);
      rc = system(Cmd);
      if (WIFSIGNALED(rc))
      {
        LOG_ERROR("Process killed by signal (%d): %s",WTERMSIG(rc),Cmd)
        SafeExit(32);
      }
      if (WIFEXITED(rc)) rc = WEXITSTATUS(rc);
      else rc=-1;
      if (rc != 0) LOG_ERROR("Unable to run command '%s'",Cmd)
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
          LOG_FATAL("Unable to fork child.")
          SafeExit(33);
        }
        Queue[Index].ChildPid = Pid;

        // add by larry, start
        Queue[Index].PI.uploadtree_pk = CI.uploadtree_pk;
        // add by larry, end

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
#if 0
            Queue[Index].PI.uploadtree_pk = CI.uploadtree_pk;
#endif
            if (Recurse > 0)
              Traverse(Queue[Index].ChildRecurse,NULL,"Called by dir/wait",NULL,Recurse-1,&Queue[Index].PI, ExcludePatterns);
            else if (Recurse < 0)
              Traverse(Queue[Index].ChildRecurse,NULL,"Called by dir/wait",NULL,Recurse,&Queue[Index].PI, ExcludePatterns);
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
    LOG_DEBUG("Skipping (not a file or directory): %s",CI.Source)
  }

  TraverseEnd:
  if (UnlinkAll && MaxThread <=1)
  {
#if 0
    printf("===\n");
    printf("Source: '%s'\n",CI.Source);
    printf("NewDir: '%s'\n",NewDir ? NewDir : "");
    printf("Name: '%s'  '%s'\n",CI.Partdir,CI.Partname);
#endif
    if (!NewDir)
    {
      if (IsDir(CI.Source)) RemoveDir(CI.Source);
      //    else unlink(CI.Source);
    }
    else RemoveDir(NewDir);
  }
  return(IsContainer);
} /* Traverse() */
