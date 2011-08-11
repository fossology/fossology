/***************************************************************
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
 ***************************************************************/
/**
 * \file mimetype.c
 * \brief Get the mimetype for a package.
 * Lots of different agents generate mimetype information, but they have
 * limitations.  For example:
 *  - Ununpack: it knows mimetypes!  But only for the files it extracts.
 *    Unknown files are not assigned mimetypes.
 *  - Pkgmetagetta: it knows mimetypes!  But only for the files it supports.
 *    And the mimetypes are not the same as ununpack.  For example,
 *    Ununpack uses Magic and says "application/x-rpm" while libextractor
 *    says "application/x-redhat-package-manager".  These are different
 *    strings.
 * This agent is intended as be the official source for mimetypes.
 * What it does:
 *  (1) If ununpack found a mimetype, us it.  This is because ununpack
 *      actually unpacks the files.  Thus, if the file can ben unpacked
 *      then this must be the right mimetype.
 *      Also ununpack uses /etc/UnMagic.mime which identifies more
 *      special types than regular magic(5).
 *  (2) If ununpack did not find a mimetype, then use magic(5).
 */

#include "finder.h"
/**
 * \brief Get the mimetype for a package
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int	main	(int argc, char *argv[])
{
  int arg;
  char *Parm = NULL;
  char *Path = NULL;
  int c;
  char *agent_desc = "Determines mimetype for each file";

  /** initialize the scheduler connection */
  fo_scheduler_connect(&argc, argv);

  /* Init */
  pgConn = fo_dbconnect();
  if (!pgConn)
  {
    FATAL("Unable to connect to database");
    exit(-1);
  }
  fo_GetAgentKey(pgConn, basename(argv[0]), 0, SVN_REV, agent_desc);

  FMimetype = fopen("/etc/mime.types","rb");
  if (!FMimetype)
  {
    printf("WARNING: Unable to open /etc/mime.types\n");
  }

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    FATAL("Failed to initialize magic cookie\n");
    PQfinish(pgConn);
    exit(-1);
  }
  if (magic_load(MagicCookie,NULL) != 0)
  {
    FATAL("Failed to load magic file: UnMagic\n");
    PQfinish(pgConn);
    exit(-1);
  }

  /* Process command-line */
  while((c = getopt(argc,argv,"iv")) != -1)
  {
    switch(c)
    {
      case 'i':
        PQfinish(pgConn);
        return(0);
      case 'v':
        Verbose++;
        break;
      default:
        Usage(argv[0]);
        PQfinish(pgConn);
        exit(-1);
    }
  }

  /* Run from the command-line (for testing) */
  for(arg=optind; arg < argc; arg++)
  {
    Akey = -1;
    memset(A,'\0',sizeof(A));
    strncpy(A,argv[arg],sizeof(A));
    DBCheckMime(A);
  }

  /* Run from scheduler! */
  if (argc == 1)
  {
		while(fo_scheduler_next())
    {
      /** get piece of information, including upload_pk, downloadfile url, and parameters */
      Parm = fo_scheduler_current();
      if (Parm && Parm[0])
      {
        SetEnv(Parm); /* set environment (A and Akey globals) */
        /* Process the repository file */
        /** Find the path **/
        Path = fo_RepMkPath("files",A);
        if (Path && fo_RepExist("files",A))
        {
          /* Get the mimetype! */
          DBCheckMime(Path);
        }
        else
        {
          printf("ERROR pfile %d Unable to process.\n",Akey);
          printf("LOG pfile %d File '%s' not found.\n",Akey,A);
          PQfinish(pgConn);
          exit(-1);
        }
      }
    }
  } /* if run from scheduler */

  /* Clean up */
	if(Path)
	{
    free(Path);
    Path = NULL;
	}
	fo_config_free();
  if (FMimetype) fclose(FMimetype);
  magic_close(MagicCookie);
  if (DBMime) PQclear(DBMime);
  if (pgConn) PQfinish(pgConn);
	/** after cleaning up agent, disconnect from the scheduler, this doesn't return */
	fo_scheduler_disconnect();
  return(0);
} /* main() */

