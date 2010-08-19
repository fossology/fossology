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
/**
 * file pkgagent.c
 * The package metadata agent puts data about each package (rpm and deb) into the database.
 * 
 * Pkgagent get RPM package info from rpm files using rpm library,
 * Build pkgagent.c need "rpm" and "librpm-dev", running binary just need "rpm".
 *
 * Pkgagent get Debian binary package info from .deb binary control file.
 *
 * Pkgagent get Debian source package info from .dsc file.
 */
#include "pkgagent.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */

/***********************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  int c;
  char *agent_desc = "Pulls metadata out of RPM or DEBIAN packages";
  struct rpmpkginfo *glb_rpmpi;
  struct debpkginfo *glb_debpi;
  int Agent_pk;

  glb_rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  glb_debpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));

  //extern long HBItemsProcessed;

  DB = DBopen();
  if (!DB)
  {
    printf("FATAL: Unable to connect to database\n");
    fflush(stdout);
    exit(-1);
  }

  Agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
  /* Process command-line */
  while((c = getopt(argc,argv,"iv")) != -1)
  {
    switch(c)
	{
	case 'i':
                DBclose(DB);  /* DB was opened above, now close it and exit */
                exit(0);
        case 'v':
                Verbose++;
                break;
	default:
		Usage(argv[0]);
		DBclose(DB);
		exit(-1);
	}
  }

  /* If no args, run from scheduler! */
  if (argc == 1)
  {
    char *repFile;
    signal(SIGALRM,ShowHeartbeat);
    alarm(60);

    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);

    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
    {
      if (Verbose) { printf("PKG: pkgagent read %s\n", Parm);}
      fflush(stdout);

      if (Parm[0] != '\0') 
      {
	memset(glb_rpmpi,0,sizeof(struct rpmpkginfo));
	memset(glb_debpi,0,sizeof(struct debpkginfo));
	ParseSchedInput(Parm,glb_rpmpi,glb_debpi);
        if (PKG_RPM) {
          repFile = RepMkPath("files", glb_rpmpi->pFile);
	  if (!repFile) {
	    printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
                            glb_rpmpi->pFileFk, glb_rpmpi->pFile);
            fflush(stdout);
            DBclose(DB);
            exit(-1);
	  }
          if (GetMetadata(repFile,glb_rpmpi)){
            RecordMetadataRPM(glb_rpmpi);
	  }
        } else if (PKG_DEB) {
	  if (GetMetadataDebBinary(glb_debpi)){
            RecordMetadataDEB(glb_debpi);
	  }	
	} else if (PKG_DEB_SRC) {
	  repFile = RepMkPath("files", glb_debpi->pFile);
	  if (!repFile) {
	    printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
                            glb_debpi->pFileFk, glb_debpi->pFile);
            fflush(stdout);
            DBclose(DB);
            exit(-1);
          }
	  if (GetMetadataDebSource(repFile,glb_debpi)){
	    RecordMetadataDEB(glb_debpi);
	  }
        } else {
	  /* Deal with the other package*/
	}

	//Heartbeat(++HBItemsProcessed);

        printf("OK\n");
        fflush(stdout);
      }
    }
  }
  else
  {
    /* printf("DEBUG: running in cli mode, processing file(s)\n"); */
    for (; optind < argc; optind++)
    {
       PKG_RPM=1;
       GetMetadata(argv[optind],glb_rpmpi);
    }
  }

  DBclose(DB);
  return(0);
} /* main() */
