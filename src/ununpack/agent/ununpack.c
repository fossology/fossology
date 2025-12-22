/*
 Ununpack: The universal unpacker.

 SPDX-FileCopyrightText: © 2007-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015,2023 Siemens AG
 SPDX-FileContributor: Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>


 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Source code for ununpack agent
 * \file
 * \brief Main file for ununpack agent
 * \page ununpack Ununpack agent
 * \tableofcontents
 * \section ununpackabout About
 * Ununpack agent helps in extracting uploaded files. The agent uses depth-first
 * search for traversing and unpacks every file it can.
 *
 * Ununpack agent uses mimetypes to identify the file type. The list of
 * supported mimetypes and related tools are below and for programmatic use:
 * \link ununpack_globals.h \endlink
 *
 * | Mimetype | Tool |
 * | ---: | :--- |
 * | application/gzip | zcat |
 * | application/x-gzip | zcat |
 * | application/x-compress | zcat |
 * | application/x-bzip | bzcat |
 * | application/x-bzip2 | bzcat |
 * | application/x-upx | upx |
 * | application/pdf | pdftotext |
 * | application/x-pdf | pdftotext |
 * | application/x-zip | unzip |
 * | application/zip | unzip |
 * | application/x-tar | tar |
 * | application/x-gtar | tar |
 * | application/x-cpio | cpio |
 * | application/x-rar | unrar |
 * | application/x-cab | cabextract |
 * | application/x-7z-compressed | 7z |
 * | application/x-7z-w-compressed | 7z |
 * | application/x-rpm | rpm2cpio |
 * | application/x-archive | ar |
 * | application/x-debian-package | ar |
 * | application/vnd.debian.binary-package | ar |
 * | application/x-iso | isoinfo |
 * | application/x-iso9660-image | isoinfo |
 * | application/x-fat | fat |
 * | application/x-ntfs | ntfs |
 * | application/x-ext2 | linux-ext |
 * | application/x-ext3 | linux-ext |
 * | application/x-x86_boot | departition |
 * | application/x-debian-source | dpkg-source |
 * | application/x-xz | tar |
 * | application/jar | unzip |
 * | application/java-archive | unzip |
 * | application/x-dosexec | 7z |
 * | application/zstd | zstd |
 * | application/x-lz4 | zstd |
 * | application/x-lzma | zstd |
 *
 * \section ununpackactions Supported actions
 * Usage: \code ununpack [options] file [file [file...]] \endcode
 * Extracts each file. If filename specifies a directory, then extracts
 * everything in it.
 * | Command line flag | Description |
 * | -----: | :--- |
 * | -h     | Help, then exit |
 * | -C     | Force continue when unpack tool fails |
 * | -d dir | Specify alternate extraction directory. %%U substitutes a unique ID |
 * | ^ | Default is the same directory as file (usually not a good idea) |
 * | -m #   | Number of CPUs to use (default: 1) |
 * | -P     | Prune files: remove links, >1 hard links, zero files, etc |
 * | -R     | Recursively unpack (same as '-r -1') |
 * | -r #   | Recurse to a specified depth (0=none/default, -1=infinite) |
 * | -X     | Remove recursive sources after unpacking |
 * | -x     | Remove ALL unpacked files when done (clean up) |
 * | -L out | Generate a log of files extracted (in XML) to out |
 * | -F     | Using files from the repository |
 * | -i     | Initialize the database queue system, then exit |
 * | -I     | Ignore SCM data |
 * | -Q     | Using scheduler queue system. (Includes -F) |
 * | ^      | If -L is used, unpacked files are placed in 'files' |
 * | -T rep | Set gold repository name to 'rep' (for testing) |
 * | -t rep | Set files repository name to 'rep' (for testing) |
 * | -A     | Do not set the initial DB container as an artifact |
 * | -f     | Force processing files that already exist in the DB |
 * | -q     | Quiet (generate no output) |
 * | -U upload_pk | Upload to unpack (implies -RQ). Writes to db |
 * | -v     | Verbose (-vv = more verbose) |
 * | -V     | Print the version info, then exit |
 * | -E patterns | Exclude files matching patterns |
 * \section ununpacksource Agent source
 *   - \link src/ununpack/agent \endlink
 *   - \link src/ununpack/ui \endlink
 *   - Functional test cases \link src/ununpack/agent_tests/Functional \endlink
 *   - Unit test cases \link src/ununpack/agent_tests/Unit \endlink
 */
#define _GNU_SOURCE
#include "ununpack.h"
#include "ununpack_globals.h"
#include <gcrypt.h>

#ifdef COMMIT_HASH_S
char BuildVersion[]="ununpack build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="ununpack build version: NULL.\n";
#endif

/***********************************************************************/
int	main(int argc, char *argv[])
{
  int Pid;
  int c;
  int rvExist1=0, rvExist2=0;
  PGresult *result;
  char *NewDir=".";
  char *AgentName = "ununpack";
  char *AgentARSName = "ununpack_ars";
  char *agent_desc = "Unpacks archives (iso, tar, etc)";
  int   Recurse=0;
  int   ars_pk = 0;
  int   user_pk = 0;
  long  Pfile_size = 0;
  char *ListOutName=NULL;
  char *Fname = NULL;
  char *FnameCheck = NULL;
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[PATH_MAX];
  struct stat Stat;
  char *ExcludePatterns = NULL;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);

  while((c = getopt(argc,argv,"ACc:d:FfHhL:m:PQiIqRr:T:t:U:VvXxE:")) != -1)
  {
    switch(c)
    {
      case 'A':	SetContainerArtifact=0; break;
      case 'C':	ForceContinue=1; break;
      case 'c':	break;  /* handled by fo_scheduler_connect() */
      case 'd':
        /* if there is a %U in the path, substitute a unique ID */
        NewDir=PathCheck(optarg);
        break;
      case 'F':	UseRepository=1; break;
      case 'f':	ForceDuplicate=1; break;
      case 'L':	ListOutName=optarg; break;
      case 'm':
        MaxThread = atoi(optarg);
        if (MaxThread < 1) MaxThread=1;
        break;
      case 'P':	PruneFiles=1; break;
      case 'R':	Recurse=-1; break;
      case 'r':	Recurse=atoi(optarg); break;
      case 'i':
        if (!IsExe("dpkg-source",Quiet))
          LOG_WARNING("dpkg-source is not available on this system.  This means that debian source packages will NOT be unpacked.");
        SafeExit(0);
        break; /* never reached */
      case 'I': IgnoreSCMData=1; break;
      case 'Q':
        UseRepository=1;

        user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */

        /* Get the upload_pk from the scheduler */
        if((Upload_Pk = fo_scheduler_next()) == NULL) SafeExit(0);
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
      case 'U':
        UseRepository = 1;
        Recurse = -1;
        Upload_Pk = optarg;
        break;
      case 'V': printf("%s", BuildVersion);SafeExit(0);
      case 'v':	Verbose++; break;
      case 'X':	UnlinkSource=1; break;
      case 'x':	UnlinkAll=1; break;
      case 'E': ExcludePatterns = optarg; break;
      default:
        Usage(argv[0], BuildVersion);
        SafeExit(25);
    }
  }

  // Only print exclude patterns if actually set and not empty
  if (ExcludePatterns && strlen(ExcludePatterns) > 0) {
    if (Verbose) LOG_DEBUG("Excluding patterns: %s", ExcludePatterns);
  } else {
    ExcludePatterns = NULL;
  }

  /* Initialize gcrypt and disable security memory */
  gcry_check_version(NULL);
  gcry_control (GCRYCTL_DISABLE_SECMEM, 0);
  gcry_control (GCRYCTL_INITIALIZATION_FINISHED, 0);

  /* Open DB and Initialize CMD table */
  if (UseRepository)
  {
    /* Check Permissions */
    if (GetUploadPerm(pgConn, atoi(Upload_Pk), user_pk) < PERM_WRITE)
    {
      LOG_ERROR("You have no update permissions on upload %s", Upload_Pk);
      SafeExit(100);
    }

    COMMIT_HASH = fo_sysconfig(AgentName, "COMMIT_HASH");
    VERSION = fo_sysconfig(AgentName, "VERSION");
    sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);
    /* Get the unpack agent key */
    agent_pk = fo_GetAgentKey(pgConn, AgentName, atoi(Upload_Pk), agent_rev,agent_desc);

    InitCmd();

    /* Make sure ars table exists */
    if (!fo_CreateARSTable(pgConn, AgentARSName)) SafeExit(0);

    /* Has this user previously unpacked this upload_pk successfully?
     *    In this case we are done.  No new ars record is needed since no
     *    processing is initiated.
     * The unpack version is ignored.
     */
    snprintf(SQL,MAXSQL,
        "SELECT ars_pk from %s where upload_fk='%s' and ars_success=TRUE",
           AgentARSName, Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(101);

    if (PQntuples(result) > 0) /* if there is a value */
    {
      PQclear(result);
      LOG_WARNING("Upload_pk %s, has already been unpacked.  No further action required",
              Upload_Pk)
      SafeExit(0);
    }
    PQclear(result);

    /* write the unpack_ars start record */
    ars_pk = fo_WriteARS(pgConn, ars_pk, atoi(Upload_Pk), agent_pk, AgentARSName, 0, 0);

    /* Get Pfile path and Pfile_Pk, from Upload_Pk */
  snprintf(SQL,MAXSQL,
        "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile, pfile_fk, pfile_size FROM upload INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk WHERE upload.upload_pk = '%s'",
           Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(102);

    if (PQntuples(result) > 0) /* if there is a value */
    {
      Pfile = strdup(PQgetvalue(result,0,0));
      Pfile_Pk = strdup(PQgetvalue(result,0,1));
      Pfile_size = atol(PQgetvalue(result, 0, 2));
      if (Pfile_size == 0)
      {
        PQclear(result);
        LOG_WARNING("Uploaded file (Upload_pk %s), is zero length.  There is nothing to unpack.",
                      Upload_Pk)
        SafeExit(0);
      }

      PQclear(result);
    }

    // Determine if uploadtree records should go into a separate table.
    // If the input file size is > 500MB, then create a separate uploadtree_{upload_pk} table
    // that inherits from the master uploadtree table.
    // Save uploadtree_tablename, it will get written to upload.uploadtree_tablename later.
    if (Pfile_size > 500000000)
    {
      sprintf(uploadtree_tablename, "uploadtree_%s", Upload_Pk);
      if (!fo_tableExists(pgConn, uploadtree_tablename))
      {
        snprintf(SQL,MAXSQL,"CREATE TABLE %s (LIKE uploadtree INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES); ALTER TABLE %s ADD CONSTRAINT %s CHECK (upload_fk=%s); ALTER TABLE %s INHERIT uploadtree",
               uploadtree_tablename, uploadtree_tablename, uploadtree_tablename, Upload_Pk, uploadtree_tablename);
        PQsetNoticeProcessor(pgConn, SQLNoticeProcessor, SQL);  // ignore notice about implicit primary key index creation
        result =  PQexec(pgConn, SQL);
        // Ignore postgres notice about creating an implicit index
        if (PQresultStatus(result) != PGRES_NONFATAL_ERROR)
          if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(103);
        PQclear(result);
      }
    }
    else
      strcpy(uploadtree_tablename, "uploadtree_a");
  }

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
      LOG_ERROR("pfile %s Unable to write to %s\n",Pfile_Pk,ListOutName)
      SafeExit(104);
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

  // Set ReunpackSwitch if the uploadtree records are missing from the database.
  if (!ReunpackSwitch && UseRepository)
  {
    snprintf(SQL,MAXSQL,"SELECT uploadtree_pk FROM uploadtree WHERE upload_fk=%s limit 1;",Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(105);
    if (PQntuples(result) == 0) ReunpackSwitch=1;
    PQclear(result);
  }

  /*** process files from command line ***/
  for( ; optind<argc; optind++)
  {
    CksumFile *CF=NULL;
    Cksum *Sum;
    int i;
    if (Fname) { free(Fname); Fname=NULL; }
    if (ListOutName != NULL)
    {
      fprintf(ListOutFile,"<source source=\"%s\" ",argv[optind]);
      if (UseRepository && !fo_RepExist(REP_FILES,argv[optind]))
      {
        /* make sure the source exists in the src repository */
        if (fo_RepImport(argv[optind],REP_FILES,argv[optind],1) != 0)
        {
          LOG_ERROR("Failed to import '%s' as '%s' into the repository",argv[optind],argv[optind])
          SafeExit(106);
        }
      }
    }

    if (UseRepository)
    {
      if (fo_RepExist(REP_FILES,argv[optind]))
      {
        Fname=fo_RepMkPath(REP_FILES,argv[optind]);
      }
      else if (fo_RepExist(REP_GOLD,argv[optind]))
      {
        Fname=fo_RepMkPath(REP_GOLD,argv[optind]);
        if (fo_RepImport(Fname,REP_FILES,argv[optind],1) != 0)
        {
          LOG_ERROR("Failed to import '%s' as '%s' into the repository",Fname,argv[optind])
          SafeExit(107);
        }
      }

      if (Fname)
      {
        FnameCheck = Fname;
        CF = SumOpenFile(Fname);
      }
      else
      {
        LOG_ERROR("NO file unpacked.  File %s does not exist either in GOLD or FILES", Pfile);
        SafeExit(108);
      }
      /* else: Fname is NULL and CF is NULL */
    }
    else
    {
      FnameCheck = argv[optind];
      CF = SumOpenFile(argv[optind]);
    }

    /* Check file to unpack.  Does it exist?  Is it zero length? */
    if (stat(FnameCheck,&Stat))
    {
      LOG_ERROR("File to unpack is unavailable: %s, error: %s", Fname, strerror(errno));
      SafeExit(109);
    }
    else
    if (Stat.st_size < 1)
    {
      LOG_WARNING("File to unpack is empty: %s", Fname);
      SafeExit(110);
    }

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
        Fin = fopen(argv[optind],"rb");
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
    if (Fname)	TraverseStart(Fname,"called by main via args",NewDir,Recurse,ExcludePatterns);
    else		TraverseStart(argv[optind],"called by main",NewDir,Recurse,ExcludePatterns);
    if (ListOutName != NULL) fprintf(ListOutFile,"</source>\n");
  } /* end for */

  /* free memory */
  if (Fname) { free(Fname); Fname=NULL; }

  /* process pfile from scheduler */
  if (Pfile)
  {
    if (0 == (rvExist1 = fo_RepExist2(REP_FILES,Pfile)))
    {
      Fname=fo_RepMkPath(REP_FILES,Pfile);
    }
    else if (0 == (rvExist2 = fo_RepExist2(REP_GOLD,Pfile)))
    {
      Fname=fo_RepMkPath(REP_GOLD,Pfile);
      if (fo_RepImport(Fname,REP_FILES,Pfile,1) != 0)
      {
        LOG_ERROR("Failed to import '%s' as '%s' into the repository",Fname,Pfile)
        SafeExit(111);
      }
    }
    if (Fname)
    {
      TraverseStart(Fname,"called by main via env",NewDir,Recurse,ExcludePatterns);
      free(Fname);
      Fname=NULL;
    }
    else
    {
      LOG_ERROR("NO file unpacked!");
      if (rvExist1 > 0)
      {
        Fname=fo_RepMkPath(REP_FILES, Pfile);
        LOG_ERROR("Error is %s for %s", strerror(rvExist1), Fname);
      }
      if (rvExist2 > 0)
      {
        Fname=fo_RepMkPath(REP_GOLD, Pfile);
        LOG_ERROR("Error is %s for %s", strerror(rvExist2), Fname);
      }
      SafeExit(112);
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
          Traverse(Queue[Pid].ChildRecurse,NULL,"called by wait",NULL,Recurse-1,&Queue[Pid].PI,ExcludePatterns);
        else if (Recurse < 0)
          Traverse(Queue[Pid].ChildRecurse,NULL,"called by wait",NULL,Recurse,&Queue[Pid].PI,ExcludePatterns);
      }
    }
  } while(Pid >= 0);

  if (MagicCookie) magic_close(MagicCookie);
  if (ListOutFile)
  {
    fprintf(ListOutFile,"<summary files_regular=\"%d\" files_compressed=\"%d\" artifacts=\"%d\" directories=\"%d\" containers=\"%d\" />\n",
        TotalFiles,TotalCompressedFiles,TotalArtifacts,
        TotalDirectories,TotalContainers);
    fputs("</xml>\n",ListOutFile);
  }
  if (pgConn)
  {
    /* If it completes, mark it! */
    if (Upload_Pk)
    {
      snprintf(SQL,MAXSQL,"UPDATE upload SET upload_mode = (upload_mode | (1<<5)), uploadtree_tablename='%s' WHERE upload_pk = '%s';",uploadtree_tablename, Upload_Pk);
      result =  PQexec(pgConn, SQL); /* UPDATE upload */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(113);
      PQclear(result);

      int batch_size = 5000;
      long last_pk = 0;
      int rows_affected = 0;
      do {
    snprintf(SQL, MAXSQL,
      "UPDATE %s "
      "SET realparent = getItemParent(uploadtree_pk) "
      "WHERE upload_fk = '%s' "
      "AND uploadtree_pk > %ld "
      "ORDER BY uploadtree_pk "
      "LIMIT %d",
      uploadtree_tablename,
      Upload_Pk,
      last_pk,
      batch_size);

    result = PQexec(pgConn, SQL);
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
        SafeExit(114);

    rows_affected = atoi(PQcmdTuples(result));
    PQclear(result);

    if (rows_affected > 0) {
        last_pk += batch_size;
        printf("ununpack: processed batch, rows=%d\n", rows_affected);
    }
    } while (rows_affected > 0);

    }

    if (ars_pk) fo_WriteARS(pgConn, ars_pk, atoi(Upload_Pk), agent_pk, AgentARSName, 0, 1);
  }
  if (ListOutFile && (ListOutFile != stdout))
  {
    fclose(ListOutFile);
  }

  if (UnlinkAll && MaxThread > 1)
  {
    /* Delete temporary files */
    if (strcmp(NewDir, ".")) RemoveDir(NewDir);
  }

  SafeExit(0);
  return(0);  // never executed but makes the compiler happy
}
