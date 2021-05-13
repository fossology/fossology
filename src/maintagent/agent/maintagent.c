/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014,2019 Siemens AG

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
 \page maintagent Maintenance agent
 \tableofcontents

 \section maintagentabout About
 This agent performs a variety of maintenance and repair functions.
 \section maintagentactions Supported actions
 Commandline flag|Description|
 ---:|:---|
 -a|Run all non slow maintenance operations|
 -A|Run all maintenance operations|
 -D|Vacuum Analyze the database|
 -F|Validate folder contents|
 -g|Delete orphan gold files|
 -h|Print help (usage)|
 -N|Normalize the (internal) priority numbers|
 -p|Verify file permissions (report only)
 -P|Verify and fix file permissions|
 -R|Remove uploads with no pfiles|
 -T|Remove orphaned temp tables|
 -L|Remove orphaned log files from file systems|
 -U|Process expired uploads (slow)|
 -Z|Remove orphaned files from the repository (slow)|
 -i|Initialize the database, then exit|
 -I|Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use)|
 -v|verbose (turns on debugging output)|
 -V|print the version info, then exit|
 -c SYSCONFDIR|Specify the directory for the system configuration.|

 Anyone with execute access can run this agent. This makes it easy to put in a
 cron job.
 If run from the FOSSology UI, you must be an admin.
 \section maintagentsource Agent source
  - \link src/maintagent/agent \endlink
  - \link src/maintagent/ui \endlink
 */

#include "maintagent.h"

#ifdef COMMIT_HASH_S
char BuildVersion[]="maintagent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="maintagent build version: NULL.\n";
#endif

/**
 * \brief Main entry point for the agent
 */
int main(int argc, char **argv)
{
  int cmdopt;
  char *COMMIT_HASH;
  char *VERSION;
  char agentRev[myBUFSIZ];
  char *agentDesc = "Maintenance Agent";

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);
  dbManager = fo_dbManager_new(pgConn);

  /* get agent pk
   * Note, if GetAgentKey fails, this process will exit.
   */
  COMMIT_HASH = fo_sysconfig("maintagent", "COMMIT_HASH");
  VERSION = fo_sysconfig("maintagent", "VERSION");
  snprintf(agentRev, sizeof(agentRev), "%s.%s", VERSION, COMMIT_HASH);
  /* insert/update agent data if necessary */
  fo_GetAgentKey(pgConn, basename(argv[0]), 0, agentRev, agentDesc);

  int validateFoldersExe = 0;
  int verifyFilePermsExe = 0;
  int removeUploadsExe = 0;
  int normalizeUploadPrioritiesExe = 0;
  int removeTempsExe = 0;
  int vacAnalyzeExe = 0;
  int processExpiredExe = 0;
  int removeOrphanedFilesExe = 0;
  int reIndexAllTablesExe = 0;
  int removeOrphanedRowsExe = 0;
  int removeOrphanedLogs = 0;
  int removeExpiredTokensExe = 0;
  int tokenRetentionPeriod = 30;

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "aAc:DEFghiILNpPRt:TUvVZ")) != -1)
  {
    switch (cmdopt)
    {
      case 'a': /* All non slow operations */
        if (validateFoldersExe == 0)
        {
          validateFolders();
          validateFoldersExe = 1;
        }
        if (verifyFilePermsExe == 0)
        {
          verifyFilePerms(1);
          verifyFilePermsExe = 1;
        }
        if (removeUploadsExe == 0)
        {
          removeUploads();
          removeUploadsExe = 1;
        }
        if (normalizeUploadPrioritiesExe == 0)
        {
          normalizeUploadPriorities();
          normalizeUploadPrioritiesExe = 1;
        }
        if (removeTempsExe == 0)
        {
          removeTemps();
          removeTempsExe = 1;
        }
        if (vacAnalyzeExe == 0)
        {
          vacAnalyze();
          vacAnalyzeExe = 1;
        }
        if (removeOrphanedLogs == 0)
        {
          removeOrphanedLogFiles();
          removeOrphanedLogs = 1;
        }
        break;
      case 'A': /* All operations */
        if (validateFoldersExe == 0)
        {
          validateFolders();
          validateFoldersExe = 1;
        }
        if (verifyFilePermsExe == 0)
        {
          verifyFilePerms(1);
          verifyFilePermsExe = 1;
        }
        if (removeUploadsExe == 0)
        {
          removeUploads();
          removeUploadsExe = 1;
        }
        if (normalizeUploadPrioritiesExe == 0)
        {
          normalizeUploadPriorities();
          normalizeUploadPrioritiesExe = 1;
        }
        if (removeTempsExe == 0)
        {
          removeTemps();
          removeTempsExe = 1;
        }
        if (vacAnalyzeExe == 0)
        {
          vacAnalyze();
          vacAnalyzeExe = 1;
        }
        if (processExpiredExe == 0)
        {
          processExpired();
          processExpiredExe = 1;
        }
        if (removeOrphanedFilesExe == 0)
        {
          removeOrphanedFiles();
          removeOrphanedFilesExe = 1;
        }
        if (reIndexAllTablesExe == 0)
        {
          reIndexAllTables();
          reIndexAllTablesExe = 1;
        }
        if (removeOrphanedRowsExe == 0)
        {
          removeOrphanedRows();
          removeOrphanedRowsExe = 1;
        }
        if (removeOrphanedLogs == 0)
        {
          removeOrphanedLogFiles();
          removeOrphanedLogs = 1;
        }
        break;
      case 'D': /* Vac/Analyze (slow) */
        if (vacAnalyzeExe == 0)
        {
          vacAnalyze();
          vacAnalyzeExe = 1;
        }
        break;
      case 'F': /* Validate folder contents */
        if (validateFoldersExe == 0)
        {
          validateFolders();
          validateFoldersExe = 1;
        }
        break;
      case 'g': /* Delete orphan gold files */
        deleteOrphanGold();
        break;
      case 'h':
        usage(argv[0]);
        exitNow(0);
      case 'N': /* Remove uploads with no pfiles */
        if (normalizeUploadPrioritiesExe == 0)
        {
          normalizeUploadPriorities();
          normalizeUploadPrioritiesExe = 1;
        }
        break;
      case 'p': /* Verify file permissions */
        verifyFilePerms(0);
        break;
      case 'P': /* Verify and fix file permissions */
        if (verifyFilePermsExe == 0)
        {
          verifyFilePerms(1);
          verifyFilePermsExe = 1;
        }
        break;
      case 'R': /* Remove uploads with no pfiles */
        if (removeUploadsExe == 0)
        {
          removeUploads();
          removeUploadsExe = 1;
        }
        break;
      case 't': /* Remove expired personal access token */
        if (removeExpiredTokensExe == 0)
        {
          tokenRetentionPeriod = atol(optarg);
          removeExpiredTokens(tokenRetentionPeriod);
          removeExpiredTokensExe = 1;
        }
        break;      
      case 'T': /* Remove orphaned temp tables */
        if (removeTempsExe == 0)
        {
          removeTemps();
          removeTempsExe = 1;
        }
        break;
      case 'U': /* Process expired uploads (slow) */
        if (processExpiredExe == 0)
        {
          processExpired();
          processExpiredExe = 1;
        }
        break;
      case 'Z': /* Remove orphaned files from the repository (slow) */
        if (removeOrphanedFilesExe == 0)
        {
          removeOrphanedFiles();
          removeOrphanedFilesExe = 1;
        }
        break;
      case 'I': /* Reindexing of database */
        if (reIndexAllTablesExe == 0)
        {
          reIndexAllTables();
          reIndexAllTablesExe = 1;
        }
        break;
      case 'E': /* Remove orphaned files from the database */
        if (removeOrphanedRowsExe == 0)
        {
          removeOrphanedRows();
          removeOrphanedRowsExe = 1;
        }
        break;
      case 'L': /* Remove orphaned log files from file system */
        if (removeOrphanedLogs == 0)
        {
          removeOrphanedLogFiles();
          removeOrphanedLogs = 1;
        }
        break;
      case 'i': /* "Initialize" */
        exitNow(0);
      case 'v': /* verbose output for debugging  */
        agent_verbose++;  ///< global agent verbose flag. Can be changed in running agent by the scheduler on each fo_scheduler_next() call
        break;
      case 'V': /* print version info */
        printf("%s", BuildVersion);
        exitNow(0);
      case 'c':
        break; /* handled by fo_scheduler_connect() */
      default:
        usage(argv[0]);
        exitNow(-1);
    }
  }

  exitNow(0); /* success */
  return (0); /* Never executed but prevents compiler warning */
} /* main() */
