/*****************************************************************
 wc_agent: the word count agent
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
 * \file 
 * \brief the word count agent, count the word count for one file
 *  This should be used directly from the scheduler, do not support running from command line.
 *  wc_agent get upload_id from the scheduler, then get all pfiles (the pfiles are belonging to this upload) 
 *  which are not in the table agent_wc. if one pfile already in the table, it means that we arleady counted the word count
 *  for this pfile, ignore it
 */

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>

#include "libfossology.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXCMD  2048

/* for the DB */
void *pgConn = NULL;

/* input for this system */
long GlobalPfileFk=-1; /* the pfile_fk to process */
char GlobalPfile[MAXCMD]; /* the pfile (sha1.md5.len) to process */

/**
 * \brief check if the pfile_id is a file
 * 
 * \param long mode - mode of the pfile, if is from ufile_mode in table upload_tree
 * container=1<<29, artifact=1<<28, project=1<<27, replica(same pfile)=1<<26, package=1<<25,directory=1<<18 
 *
 * \return 1 on yes, is a file; 0 on no,  not a file
 */
int IsFile(long mode)
{
 /** 
  * if ((mode & 1<<18) + (mode & 0040000) != 0), is dir
  * if ((mode & 1<<28) != 0), is artifact
  * if ((mode & 1<<29) != 0), is container
  * a file is not dir, artifact, and container
  */
 if (((mode & 1<<18) + (mode & 0040000) == 0) && ((mode & 1<<28) == 0) && ((mode & 1<<29) != 0)) return 1;
 else return 0;
}

/** 
 * \brief This function does the work.
 * In this example, we'll just run wc and store the
 * results in the database.
 * Requires: DB open and ready.
 * 
 * \param long PfileFk - pfile id 
 * \param char * Pfile - the file path in repo
 *
 * \return 0 on success, != 0 on failure.
 */
int ProcessData(long PfileFk, char *Pfile)
{
  char *RepFile;
  char Cmd[MAXCMD];
  char SQL[MAXCMD];
  long Bytes,Words,Lines;
  FILE *Fin;
  PGresult *result;

  /* Get the path to the actual file */
  RepFile = fo_RepMkPath("files",Pfile);
  if (!RepFile)
  {
    LOG_FATAL("pfile %ld Word count unable to open file.\n",GlobalPfileFk);
    printf("LOG pfile %ld Word count unable to open file: pfile_fk=%ld pfile=%s\n",GlobalPfileFk,GlobalPfileFk,GlobalPfile);
    PQfinish(pgConn);
    exit(-1);
  }

  /* Create the command to run */
  memset(Cmd,'\0',MAXCMD);
  snprintf(Cmd,MAXCMD,"/usr/bin/wc '%s' 2>/dev/null",RepFile);

  /* Run the command and read the results */
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    LOG_FATAL("pfile %ld Word count unable to count words.\n",GlobalPfileFk);
    printf("LOG pfile %ld Word count unable to run command: %s\n",GlobalPfileFk,Cmd);
    PQfinish(pgConn);
    exit(-1);
  }

  /* Read the results */
  fscanf(Fin,"%ld %ld %ld",&Lines,&Words,&Bytes);

  /* Store the results */
  if (!pgConn)
  {
    printf("%s:  Bytes=%ld  Words=%ld  Lines=%ld\n",Pfile,Bytes,Words,Lines);
  }
  else
  {
    /* Store results in the database */
    memset(Cmd,'\0',MAXCMD);
    snprintf(Cmd,MAXCMD,"INSERT INTO agent_wc (pfile_fk,wc_words,wc_lines) VALUES (%ld,%ld,%ld);",
        PfileFk,Words,Lines);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
    {
      LOG_FATAL("pfile %ld Database insert failed.\n",GlobalPfileFk);
      printf("LOG pfile %ld Database insert failed: %s\n",GlobalPfileFk,SQL);
      PQfinish(pgConn);
      exit(-1);
    }
    PQclear(result);
  }

  /* Clean up */
  pclose(Fin);
  if(RepFile)
  {
    free(RepFile);
    RepFile = NULL;
  }
  return(0);
} /* ProcessData() */

/**
 * \brief Say how to run this program.
 *  Many agents permit running from the command-line
 *  for testing.
 *  At minimum, you need "-i" to initialize the DB and exit.
 */
void Usage(char *Name)
{
  printf("Usage: %s [options]\n",Name);
  printf("  -i  :: Initialize the DB connection then exit (nothing downloaded)\n");
  printf("  -c  :: Specify the directory for the system configuration.\n");
  printf("  -C  :: Run from command line.\n");
} /* Usage() */

/**
 * \brief main function
 * \return 0 on success, exit(-1) on failure.
 */
int main(int argc, char *argv[])
{
  int c;
  int InitFlag=0; /* is the system just going to initialize? */
  int CmdlineFlag = 0; /** run from command line flag, 1 yes, 0 not */
  char *Parm = NULL;
  char *agent_desc = "File character, line, word count.";
  int pfile_count = 0;
  int Agent_pk = 0;
  int ars_pk = 0;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[MAXCMD];

  int upload_pk = 0;           // the upload primary key
  char *AgentARSName = "wc_agent_ars";
  int rv;
  PGresult *result;
  char sqlbuf[MAXCMD];
  char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;


  /** initialize the scheduler connection */
  fo_scheduler_connect(&argc, argv, NULL);

  /* Process command-line */
  while((c = getopt(argc,argv,"iCc:")) != -1)
  {
    switch(c)
    {
      case 'i':
        InitFlag=1;
        break;
      case 'c': break; /* handled by fo_scheduler_connect() */
      case 'C':
        CmdlineFlag = 1;
        break;
      default:
        Usage(argv[0]);
        exit(-1);
    }
  }

  /* Init */
  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!pgConn )
  {
    LOG_FATAL("Unable to connect to database\n");
    exit(-1);
  }

  SVN_REV = fo_sysconfig("wc_agent", "SVN_REV");
  VERSION = fo_sysconfig("wc_agent", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);

  Agent_pk = fo_GetAgentKey(pgConn , basename(argv[0]), 0, agent_rev, agent_desc);

  /* When initializing the DB, don't do anything else */
  if (InitFlag)
  {
    PQfinish(pgConn);
    return(0);
  }

  /* Run from scheduler! */
  if (0 == CmdlineFlag)
  {
    while(fo_scheduler_next())
    {
      Parm = fo_scheduler_current();
      if (Parm[0] != '\0')
      {
        
        fo_scheduler_heart(1);
        /* 1 parameter: upload_pk */
        upload_pk = atoi(Parm);
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
            "select ars_pk from wc_agent_ars,agent \
            where agent_pk=agent_fk and ars_success=true \
            and upload_fk='%d' and agent_fk='%d'",
            upload_pk, Agent_pk);
        result = PQexec(pgConn, sqlbuf);
        if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) 
        {
          PQfinish(pgConn);
          exit(-1);
        }
        if (PQntuples(result) > 0)
        {
          PQclear(result);
          LOG_WARNING("Ignoring requested wc_agent analysis of upload %d - Results are already in database.\n",upload_pk);
          continue;
        }
        PQclear(result);
        /* Record analysis start in wc_agent_ars, the wc_agent audit trail. */
        ars_pk = fo_WriteARS(pgConn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 0);

        /** get all pfile ids on a upload record */
        memset(sqlbuf, 0, sizeof(sqlbuf));
        snprintf(sqlbuf, sizeof(sqlbuf), "SELECT DISTINCT(pfile_pk) as pfile_id, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile_path, ufile_mode FROM uploadtree, pfile  WHERE uploadtree.pfile_fk = pfile.pfile_pk AND pfile.pfile_pk not in(SELECT pfile_fk from agent_wc) AND upload_fk = '%d' LIMIT 5000;", upload_pk);
        result = PQexec(pgConn, sqlbuf);
        if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) 
        {
          PQfinish(pgConn);
          exit(-1);
        }
        pfile_count = PQntuples(result);
        int i;
        long ufile_mode =  0;
        for(i=0; i < pfile_count; i++)
        {
          ufile_mode = atoi(PQgetvalue(result, i, 2));
          if (IsFile(ufile_mode)) /**< is a file? */
          {
            GlobalPfileFk = atoi(PQgetvalue(result, i, 0));
            strncpy(GlobalPfile, PQgetvalue(result, i, 1), sizeof(GlobalPfile));
            if (ProcessData(GlobalPfileFk,GlobalPfile) != 0)
            {
              LOG_FATAL("pfile %ld Word count failed.\n",GlobalPfileFk);
              printf("LOG pfile %ld Word count failed: pfile_fk=%ld pfile=%s\n",GlobalPfileFk,GlobalPfileFk,GlobalPfile);
              PQfinish(pgConn);
              PQclear(result);
              exit(-1);
            }
          }
        }
        PQclear(result);
      }
    }
  } /* if run from scheduler */

  /* Clean up */
  PQfinish(pgConn);
  fo_scheduler_disconnect(0);
  return(0);
} /* main() */


