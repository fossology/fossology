/*******************************************************************
 Ununpack: The universal unpacker.

 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.
 
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
#include "ununpack.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#else
char Version[]="0.9.9";
#endif

int Verbose=0;
int Quiet=0;
int UnlinkSource=0;
int UnlinkAll=0;
int ForceContinue=0;
int ForceDuplicate=0;	/* when using db, should it process duplicates? */
int PruneFiles=0;
int SetContainerArtifact=1;	/* should initial container be an artifact? */
FILE *ListOutFile=NULL;
int ReunpackSwitch=0;

/* for the repository */
int UseRepository=0;
char REP_GOLD[16]="gold";
char REP_FILES[16]="files";

char UploadFileName[FILENAME_MAX];  /* upload file name */

/*** For DB queries ***/
char *Pfile = NULL;
char *Pfile_Pk = NULL; /* PK for *Pfile */
char *Upload_Pk = NULL; /* PK for upload table */
PGconn *pgConn = NULL; /* PGconn from DB */
int agent_pk=-1;	/* agent ID */
char SQL[MAXSQL];
magic_t MagicCookie;

unpackqueue Queue[MAXCHILD+1];    /* manage children */
int MaxThread=1; /* value between 1 and MAXCHILD */
int Thread=0;

/*** Global Stats (for summaries) ***/
long TotalItems=0;	/* number of records inserted */
int TotalFiles=0;
int TotalCompressedFiles=0;
int TotalDirectories=0;
int TotalContainers=0;
int TotalArtifacts=0;

/***  Command table ***/
cmdlist CMD[] =
{
  { "","","","","",CMD_NULL,0,0177000,0177000, },
  { "application/x-gzip","zcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
  { "application/x-compress","zcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
  { "application/x-bzip","bzcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
  { "application/x-bzip2","bzcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
  { "application/x-upx","upx","-d -o'%s'",">/dev/null 2>&1","",CMD_PACK,1,0177000,0177000, },
  { "application/pdf","pdftotext","-htmlmeta","'%s' >/dev/null 2>&1","",CMD_PACK,1,0100000,0100000, },
  { "application/x-pdf","pdftotext","-htmlmeta","'%s' >/dev/null 2>&1","",CMD_PACK,1,0100000,0100000, },
  { "application/x-zip","unzip","-q -P none -o","-x / >/dev/null 2>&1","unzip -Zhzv '%s' > '%s'",CMD_ARC,1,0177000,0177000, },
  { "application/zip","unzip","-q -P none -o","-x / >/dev/null 2>&1","unzip -Zhzv '%s' > '%s'",CMD_ARC,1,0177000,0177000, },
  { "application/x-tar","tar","-xSf","2>&1 ; echo ''","",CMD_ARC,1,0177000,0177777, },
  { "application/x-gtar","tar","-xSf","2>&1 ; echo ''","",CMD_ARC,1,0177000,0177777, },
  { "application/x-cpio","cpio","--no-absolute-filenames -i -d <",">/dev/null 2>&1","",CMD_ARC,1,0177777,0177777, },
  { "application/x-rar","unrar","x -o+ -p-",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
  { "application/x-cab","cabextract","",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
  { "application/x-7z-compressed","7zr","x -y",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
  { "application/x-7z-w-compressed","7z","x -y",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
  { "application/x-rpm","rpm2cpio","","> '%s' 2> /dev/null","rpm -qip '%s' > '%s' 2>&1",CMD_RPM,1,0177000,0177000, },
  { "application/x-archive","ar","x",">/dev/null 2>&1","",CMD_AR,1,0177000,0177777, },
  { "application/x-debian-package","ar","x",">/dev/null 2>&1","dpkg -I '%s' > '%s'",CMD_AR,1,0177000,0177777, },
  { "application/x-iso","","","","isoinfo -d -i '%s' > '%s'",CMD_ISO,1,0177777,0177777, },
  { "application/x-iso9660-image","","","","isoinfo -d -i '%s' > '%s'",CMD_ISO,1,0177777,0177777, },
  { "application/x-fat","fat","","","",CMD_DISK,1,0177700,0177777, },
  { "application/x-ntfs","ntfs","","","",CMD_DISK,1,0177700,0177777, },
  { "application/x-ext2","linux-ext","","","",CMD_DISK,1,0177777,0177777, },
  { "application/x-ext3","linux-ext","","","",CMD_DISK,1,0177777,0177777, },
  { "application/x-x86_boot","departition","","> /dev/null 2>&1","",CMD_PARTITION,1,0177000,0177000, },
  { "application/x-debian-source","dpkg-source","-x","'%s' >/dev/null 2>&1","",CMD_DEB,1,0177000,0177000, },
  { "","","",">/dev/null 2>&1","",CMD_DEFAULT,1,0177000,0177000, },
  { NULL,NULL,NULL,NULL,NULL,-1,-1,0177000,0177000, },
};

/***********************************************************************/
int	main(int argc, char *argv[])
{
  int Pid;
  int c;
  int rv;
  PGresult *result;
  char *NewDir=".";
  char *AgentName = "ununpack";
  char *AgentARSName = "ununpack_ars";
  int   Recurse=0;
  int   ars_pk = 0;
  char *ListOutName=NULL;
  char *Fname = NULL;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv);

  while((c = getopt(argc,argv,"ACd:FfHL:m:PQiqRr:T:t:U:vXx")) != -1)
  {
    switch(c)
    {
      case 'A':	SetContainerArtifact=0; break;
      case 'C':	ForceContinue=1; break;
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
        pgConn = fo_dbconnect();
        if (!pgConn)
        {
          FATAL("Unable to access database")
          SafeExit(20);
        }
        PQfinish(pgConn);
        if (!IsExe("dpkg-source",Quiet))
          WARNING("dpkg-source is not available on this system.  This means that debian source packages will NOT be unpacked.")
        return(0);
        break; /* never reached */
      case 'Q':
        UseRepository=1;

        /* Get the upload_pk from the scheduler */
        if((Upload_Pk = fo_scheduler_next()) == NULL)
        {
          fo_scheduler_disconnect(0);
          SafeExit(0);
        }
        DEBUG("Upload_Pk is %s", Upload_Pk)
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

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    FATAL("Failed to initialize magic cookie")
    SafeExit(-1);
  }

  magic_load(MagicCookie,NULL);

DEBUG("bobg UseRepository is %d", UseRepository)
  /* Open DB and Initialize CMD table */
  if (UseRepository) 
  {
    pgConn = fo_dbconnect();
    if (!pgConn)
    {
      FATAL("Unable to access database")
      SafeExit(21);
    }

    /* Get the unpack agent key */
    agent_pk = fo_GetAgentKey(pgConn, AgentName, atoi(Upload_Pk), 0,
                              "Unpacks archives (iso, tar, etc)");

DEBUG("bobg agent_pk is %d", agent_pk)
    InitCmd();

    /* does ars table exist? 
     * If not, create it.
     */
    rv = fo_tableExists(pgConn, AgentARSName);
    if (!rv)
    {
      rv = fo_CreateARSTable(pgConn, AgentARSName);
      if (!rv) return(0);
    }

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
      WARNING("Upload_pk %s, has already been unpacked.  No further action required", 
              Upload_Pk)
      fo_scheduler_disconnect(0);
      return(0);
    }
    PQclear(result);

    /* write the unpack_ars start record */
    ars_pk = fo_WriteARS(pgConn, ars_pk, atoi(Upload_Pk), agent_pk, AgentARSName, 0, 0);

    /* Get Pfile path and Pfile_Pk, from Upload_Pk */
  snprintf(SQL,MAXSQL,
        "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile, pfile_fk FROM upload INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk WHERE upload.upload_pk = '%s'", 
           Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(51);

    if (PQntuples(result) > 0) /* if there is a value */
    {  
      Pfile = strdup(PQgetvalue(result,0,0));
      Pfile_Pk = strdup(PQgetvalue(result,0,1));
      PQclear(result);
    }
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
      ERROR("pfile %s Unable to write to %s\n",Pfile_Pk,ListOutName)
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

  //Begin add by vincent
  if (!ReunpackSwitch && UseRepository)
  {
    snprintf(SQL,MAXSQL,"SELECT uploadtree_pk FROM uploadtree WHERE upload_fk=%s limit 1;",Upload_Pk);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
    {
      SafeExit(14);
    }
    if (PQntuples(result) == 0)
    {
      ReunpackSwitch=1;
    }
    PQclear(result);
  }
  //End add by vincent

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
      if (UseRepository && !fo_RepExist(REP_FILES,argv[optind]))
      {
        /* make sure the source exists in the src repository */
        if (fo_RepImport(argv[optind],REP_FILES,argv[optind],1) != 0)
        {
          ERROR("Failed to import '%s' as '%s' into the repository",argv[optind],argv[optind])
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
          ERROR("Failed to import '%s' as '%s' into the repository",Fname,argv[optind])
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
    if (fo_RepExist(REP_FILES,Pfile))
    {
      Fname=fo_RepMkPath(REP_FILES,Pfile);
    }
    else if (fo_RepExist(REP_GOLD,Pfile))
    {
      Fname=fo_RepMkPath(REP_GOLD,Pfile);
      if (fo_RepImport(Fname,REP_FILES,Pfile,1) != 0)
      {
        ERROR("Failed to import '%s' as '%s' into the repository",Fname,Pfile)
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
  if (pgConn)
  {
    /* If it completes, mark it! */
    if (Upload_Pk)
    {
      memset(SQL,'\0',MAXSQL);
      snprintf(SQL,MAXSQL,"UPDATE upload SET upload_mode = upload_mode | (1<<5) WHERE upload_pk = '%s';",Upload_Pk);
      result =  PQexec(pgConn, SQL); /* UPDATE upload */
      if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
      {
        SafeExit(44);
      }
      PQclear(result);
    }

    if (ars_pk) fo_WriteARS(pgConn, ars_pk, atoi(Upload_Pk), agent_pk, AgentARSName, 0, 1);
    PQfinish(pgConn);
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
 
  fo_scheduler_disconnect(0);

  return(0);
} /* UnunpackEntry() */
