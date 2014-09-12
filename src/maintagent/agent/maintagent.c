/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 \file maintagent.c
 \brief FOSSology maintenance agent

 This agent performs a variety of maintenance and repair functions.
 run ./maintagent -help to see usage.
 
 Anyone with execute access can run this agent.  This makes it easy to put in a cron job.
 If run from the FOSSology UI, you must be an admin.
 */

#include "maintagent.h"

#ifdef SVN_REV_S
char BuildVersion[]="maintagent build version: " VERSION_S " r(" SVN_REV_S ").\n";
#else
char BuildVersion[]="maintagent build version: NULL.\n";
#endif

/**********  Globals  *************/
PGconn    *pgConn = 0;        // database connection


/****************************************************/
int main(int argc, char **argv) 
{
  int cmdopt;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[myBUFSIZ];

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  SVN_REV = fo_sysconfig("maintagent", "SVN_REV");
  VERSION = fo_sysconfig("maintagent", "VERSION");
  snprintf(agent_rev, sizeof(agent_rev), "%s.%s", VERSION, SVN_REV);

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "aADFghpNPRTUZivVc:")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'a': /* All non slow operations */
          ValidateFolders();
          VerifyFilePerms(1);
          RemoveUploads();
          NormalizeUploadPriorities();
          RemoveTemps();
          VacAnalyze();
          break;
      case 'A': /* All operations */
          ValidateFolders();
          VerifyFilePerms(1);
          RemoveUploads();
          NormalizeUploadPriorities();
          RemoveTemps();
          VacAnalyze();
          ProcessExpired();
          RemoveOrphanedFiles();
          break;
      case 'D': /* Vac/Analyze (slow) */
          VacAnalyze();
          break;
      case 'F': /* Validate folder contents */
          ValidateFolders();
          break;
      case 'g': /* Delete orphan gold files */
          DeleteOrphanGold();
          break;
      case 'h':
            Usage(argv[0]);
            ExitNow(0);
      case 'N': /* Remove uploads with no pfiles */
          NormalizeUploadPriorities();
          break;
      case 'p': /* Verify file permissions */
          VerifyFilePerms(0);
          break;
      case 'P': /* Verify and fix file permissions */
          VerifyFilePerms(1);
          break;
      case 'R': /* Remove uploads with no pfiles */
          RemoveUploads();
          break;
      case 'T': /* Remove orphaned temp tables */
          RemoveTemps();
          break;
      case 'U': /* Process expired uploads (slow) */
          ProcessExpired();
          break;
      case 'Z': /* Remove orphaned files from the repository (slow) */
          RemoveOrphanedFiles();
          break;
      case 'i': /* "Initialize" */
            ExitNow(0);
      case 'v': /* verbose output for debugging  */
          agent_verbose++;   // global agent verbose flag.  Can be changed in running agent by the scheduler on each fo_scheduler_next() call
            break;
      case 'V': /* print version info */
            printf("%s", BuildVersion);           
            ExitNow(0);
      case 'c': break; /* handled by fo_scheduler_connect() */
      default:
            Usage(argv[0]);
            ExitNow(-1);
    }
  }


  ExitNow(0);  /* success */
  return(0);   /* Never executed but prevents compiler warning */
} /* main() */
