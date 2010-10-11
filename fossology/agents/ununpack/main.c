/***************************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
#include "ununpack.h"

int SetContainerArtifact=1;	/* should initial container be an artifact? */
char REP_GOLD[16]="gold";

/***********************************************************************/
int	main	(int argc, char *argv[])
{
  int Pid;
  int c;
  char *NewDir=".";
  int Recurse=0;
  char *ListOutName=NULL;
  char *Fname = NULL;
  char *agent_desc = "Unpacks archives.  Also available from the command line";

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
    {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    exit(-1);
    }

  magic_load(MagicCookie,NULL);

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
		GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
		DBclose(DB);
		if (!IsExe("dpkg-source",Quiet))
			printf("WARNING: dpkg-source is not available on this system.  This means that debian source packages will NOT be unpacked.\n");
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
		Pfile = getenv("ARG_pfile");
		if (!Pfile) Pfile = getenv("pfile");
		Pfile_Pk = getenv("ARG_pfile_fk");
		if (!Pfile_Pk) Pfile_Pk = getenv("pfile_fk");
		Upload_Pk = getenv("ARG_upload_pk");
		if (!Upload_Pk) Upload_Pk = getenv("upload_pk");
		GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
	
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
  //Begin add by vincent
  if (!ReunpackSwitch && UseRepository)
	{
	memset(SQL,'\0',MAXSQL);
  	snprintf(SQL,MAXSQL,"SELECT uploadtree_pk FROM uploadtree WHERE upload_fk=%s limit 1;",Upload_Pk);
  	int result=MyDBaccess(DB,SQL);
  	if (result < 0)
        	{
        	printf("FATAL: Database access error.\n");
        	printf("LOG: Database access error in ununpack: %s\n",SQL);
        	SafeExit(14);
        	}
  	if(DBdatasize(DB) <= 0)
  		{
		ReunpackSwitch=1;
		}
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

  // add by larry, start
  if (UnlinkAll && MaxThread > 1)
  {
    deleteTmpFiles(NewDir);
  }
  // add by larry, end

  return(0);
} /* main() */

