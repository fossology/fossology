/***************************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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
 * @file main.c
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
#endif /* SVN_REV */

/***********************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  int c;
  char *agent_desc = "Pulls metadata out of RPM or DEBIAN packages";
  //struct rpmpkginfo *glb_rpmpi;
  //struct debpkginfo *glb_debpi;
  int Agent_pk;

  long upload_pk = 0;           // the upload primary key

  fo_scheduler_connect(&argc, argv);
  
  //glb_rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  //glb_debpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));

  db_conn = fo_dbconnect();
  if (!db_conn)
  {
    printf("FATAL: Unable to connect to database\n");
    fflush(stdout);
    exit(-1);
  }

  Agent_pk = fo_GetAgentKey(db_conn, basename(argv[0]), 0, SVN_REV, agent_desc);

  /* Process command-line */
  while((c = getopt(argc,argv,"iv")) != -1)
  {
    switch(c)
    {
      case 'i':
        PQfinish(db_conn);  /* DB was opened above, now close it and exit */
        exit(0);
      case 'v':
        Verbose++;
        break;
      default:
        Usage(argv[0]);
        PQfinish(db_conn);        
        exit(-1);
    }
  }

  /* If no args, run from scheduler! */
  if (argc == 1)
  {
    while(fo_scheduler_next())
    {
      if (Verbose) { printf("PKG: pkgagent read %s\n", Parm);}
      fflush(stdout);

      upload_pk = atoi(fo_scheduler_current());
      if (upload_pk ==0) continue;

      if(!ProcessUpload(upload_pk)) return -1;
    }
  }
  else
  {
    /* printf("DEBUG: running in cli mode, processing file(s)\n"); */
    for (; optind < argc; optind++)
    {
      struct rpmpkginfo *rpmpi;
      rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
      GetMetadata(argv[optind],rpmpi);
    }
  }

  PQfinish(db_conn);
  fo_scheduler_disconnect();
  return(0);
} /* main() */
