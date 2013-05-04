/*******************************************************************
 Ununpack: The universal unpacker.

 Copyright (C) 2007-2013 Hewlett-Packard Development Company, L.P.
 
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
 *******************************************************************/

#define _GNU_SOURCE
#include "ununpack.h"
#include "ununpack_globals.h"

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
  int   Recurse=0;
  int   ars_pk = 0;
  int   user_pk = 0;
  long  Pfile_size = 0;
  char *ListOutName=NULL;
  char *Fname = NULL;
  char *FnameCheck = NULL;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[PATH_MAX];
  struct stat Stat;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);

  while((c = getopt(argc,argv,"ACc:d:FfHL:m:PQiqRr:T:t:U:vXx")) != -1)
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
      case 'v':	Verbose++; break;
      case 'X':	UnlinkSource=1; break;
      case 'x':	UnlinkAll=1; break;
      default:
        Usage(argv[0], Version);
        SafeExit(25);
    }
  }

  /* Open DB and Initialize CMD table */
  if (UseRepository) 
  {
    /* Check Permissions */
    if (GetUploadPerm(pgConn, atoi(Upload_Pk), user_pk) < PERM_WRITE)
    {
      LOG_ERROR("You have no update permissions on upload %s", Upload_Pk);
      SafeExit(99);
    }
        
    SVN_REV = fo_sysconfig(AgentName, "SVN_REV");
    VERSION = fo_sysconfig(AgentName, "VERSION");
    sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);
    /* Get the unpack agent key */
    agent_pk = fo_GetAgentKey(pgConn, AgentName, atoi(Upload_Pk), agent_rev,
                              "Unpacks archives (iso, tar, etc)");

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
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(51);

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
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(51);

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
      snprintf(SQL,MAXSQL,"CREATE TABLE %s (LIKE uploadtree INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES); ALTER TABLE %s ADD CONSTRAINT %s CHECK (upload_fk=%s); ALTER TABLE %s INHERIT uploadtree", 
               uploadtree_tablename, uploadtree_tablename, uploadtree_tablename, Upload_Pk, uploadtree_tablename);
      PQsetNoticeProcessor(pgConn, SQLNoticeProcessor, SQL);  // ignore notice about implicit primary key index creation
      result =  PQexec(pgConn, SQL);
      // Ignore postgres notice about creating an implicit index
      if (PQresultStatus(result) != PGRES_NONFATAL_ERROR)
        if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(151);
      PQclear(result);
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

  // Set ReunpackSwitch if the uploadtree records are missing from the database.
  if (!ReunpackSwitch && UseRepository)
  {
    snprintf(SQL,MAXSQL,"SELECT uploadtree_pk FROM uploadtree WHERE upload_fk=%s limit 1;",Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(14);
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
          SafeExit(28);
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
          SafeExit(29);
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
        SafeExit(31);
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
      SafeExit(102);
    }
    else
    if (Stat.st_size < 1)
    {
      LOG_WARNING("File to unpack is empty: %s", Fname);
      SafeExit(103);
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
    if (Fname)	TraverseStart(Fname,"called by main via args",NewDir,Recurse);
    else		TraverseStart(argv[optind],"called by main",NewDir,Recurse);
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
        SafeExit(30);
      }
    }
    if (Fname)
    {
      TraverseStart(Fname,"called by main via env",NewDir,Recurse);
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
      SafeExit(32);
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
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__)) SafeExit(44);
      PQclear(result);
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
