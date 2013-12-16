/***************************************************************
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

#ifdef SVN_REV_S
char BuildVersion[]="mimetype build version: " VERSION_S " r(" SVN_REV_S ").\n";
#else
char BuildVersion[]="mimetype build version: NULL.\n";
#endif

/**
 * \brief Get the mimetype for a package
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int main(int argc, char *argv[])
{
  int arg;
  char *Parm = NULL;
  char *Path = NULL;
  int c;
  char *agent_desc = "Determines mimetype for each file";
  int pfile_count = 0;
  int Agent_pk;
  int ars_pk = 0;

  int upload_pk = 0;           // the upload primary key
  int user_pk = 0;
  char *AgentARSName = "mimetype_ars";
  int rv;
  PGresult *result;
  char sqlbuf[1024];
  int CmdlineFlag = 0; /** run from command line flag, 1 yes, 0 not */
  char *SVN_REV;
  char *VERSION;
  char agent_rev[MAXCMD];
                           
  /** initialize the scheduler connection */
  fo_scheduler_connect(&argc, argv, &pgConn);

  /* Process command-line */
  while((c = getopt(argc,argv,"iCc:hvV")) != -1)
  {
    switch(c)
    {
      case 'i':
        PQfinish(pgConn);
        return(0);
      case 'c':
        /* do nothing with this option */
        break;
      case 'C':
        CmdlineFlag = 1;
        break;
      case 'v':
        agent_verbose++;
        break;
      case 'V':
        printf("%s", BuildVersion);
        PQfinish(pgConn);
        return(0);
      default:
        Usage(argv[0]);
        PQfinish(pgConn);
        exit(-1);
    }
  }

  SVN_REV = fo_sysconfig("mimetype", "SVN_REV");
  VERSION = fo_sysconfig("mimetype", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);
  /* Get the Agent Key from the DB */
  Agent_pk = fo_GetAgentKey(pgConn, basename(argv[0]), 0, agent_rev, agent_desc);

  FMimetype = fopen("/etc/mime.types","rb");
  if (!FMimetype)
  {
    LOG_WARNING("Unable to open /etc/mime.types\n");
  }

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    LOG_FATAL("Failed to initialize magic cookie\n");
    PQfinish(pgConn);
    exit(-1);
  }
  if (magic_load(MagicCookie,NULL) != 0)
  {
    LOG_FATAL("Failed to load magic file: UnMagic\n");
    PQfinish(pgConn);
    exit(-1);
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
  if (0 == CmdlineFlag)
  {
    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */

    while(fo_scheduler_next())
    {
      /** get piece of information, including upload_pk, others */
      Parm = fo_scheduler_current();
      if (Parm && Parm[0])
      {
        upload_pk = atoi(Parm);

        /* Check Permissions */
        if (GetUploadPerm(pgConn, upload_pk, user_pk) < PERM_WRITE)
        {
          LOG_ERROR("You have no update permissions on upload %d", upload_pk);
          continue;
        }

        /* does ars table exist?
         * If not, create it.
         */
        rv = fo_tableExists(pgConn, AgentARSName);
        if (!rv)
        {
          rv = fo_CreateARSTable(pgConn, AgentARSName);
          if (!rv) return(0);
        }

        /* check ars table if this is duplicate request*/
        memset(sqlbuf, 0, sizeof(sqlbuf));
        snprintf(sqlbuf, sizeof(sqlbuf),
            "select ars_pk from mimetype_ars,agent \
            where agent_pk=agent_fk and ars_success=true \
            and upload_fk='%d' and agent_fk='%d'",
            upload_pk, Agent_pk);
        result = PQexec(pgConn, sqlbuf);
        if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
        if (PQntuples(result) > 0)
        {
          PQclear(result);
          LOG_WARNING("Ignoring requested mimetype analysis of upload %d - Results are already in database.\n",upload_pk);
          continue;
        }
        PQclear(result);

        /* Record analysis start in mimetype_ars, the mimetype audit trail. */
        ars_pk = fo_WriteARS(pgConn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 0);

        /** get all pfile ids on a upload record */
        memset(sqlbuf, 0, sizeof(sqlbuf));
        snprintf(sqlbuf, sizeof(sqlbuf), "SELECT DISTINCT(pfile_pk) as Akey, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A FROM uploadtree, pfile WHERE uploadtree.pfile_fk = pfile.pfile_pk AND pfile_mimetypefk is NULL AND upload_fk = '%d';", upload_pk);
        result = PQexec(pgConn, sqlbuf);
        if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
        pfile_count = PQntuples(result);
        int i;
        for(i=0; i < pfile_count; i++)
        {
          Akey = atoi(PQgetvalue(result, i, 0));
          strncpy(A, PQgetvalue(result, i, 1), sizeof(A));
          if (Akey <= 0 || A[0]=='\0')
          {
            printf("ERROR: Data is in an unknown format.\n");
            PQfinish(pgConn);
            exit(-1);
          }

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
          /* Clean up Path memory */
          if(Path)
          {
            free(Path);
            Path = NULL;
          }
          fo_scheduler_heart(1);
        }
        PQclear(result);
        
        /* Record analysis success in mimetype_ars. */
        if (ars_pk) fo_WriteARS(pgConn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 1);
      }
    }
  } /* if run from scheduler */

  /* Clean up */
  if (FMimetype) fclose(FMimetype);
  magic_close(MagicCookie);
  if (DBMime) PQclear(DBMime);
  if (pgConn) PQfinish(pgConn);
  /** after cleaning up agent, disconnect from the scheduler, this doesn't return */
  fo_scheduler_disconnect(0);
  return(0);
} /* main() */

