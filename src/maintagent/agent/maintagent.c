/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

#ifdef COMMIT_HASH_S
char BuildVersion[]="maintagent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="maintagent build version: NULL.\n";
#endif

/**********  Globals  *************/
PGconn    *pgConn = 0;        // database connection


/****************************************************/
int main(int argc, char **argv) 
{
  int cmdopt;
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[myBUFSIZ];
  char *agent_desc = "Maintenance Agent";

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  COMMIT_HASH = fo_sysconfig("maintagent", "COMMIT_HASH");
  VERSION = fo_sysconfig("maintagent", "VERSION");
  snprintf(agent_rev, sizeof(agent_rev), "%s.%s", VERSION, COMMIT_HASH);
  /* insert/update agent data if necessary */
  fo_GetAgentKey(pgConn, basename(argv[0]), 0, agent_rev, agent_desc);

  int ValidateFoldersExe = 0;
  int VerifyFilePermsExe = 0;
  int RemoveUploadsExe = 0;
  int NormalizeUploadPrioritiesExe = 0;
  int RemoveTempsExe = 0;
  int VacAnalyzeExe = 0;
  int ProcessExpiredExe = 0;
  int RemoveOrphanedFilesExe = 0;
  int reIndexAllTablesExe = 0;
  /* command line options */
  while ((cmdopt = getopt(argc, argv, "aADFghIpNPRTUZivVc:")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'a': /* All non slow operations */
          if(ValidateFoldersExe == 0){
            ValidateFolders();
            ValidateFoldersExe = 1; 
          }
          if(VerifyFilePermsExe == 0){
            VerifyFilePerms(1);
            VerifyFilePermsExe = 1;
          }
          if(RemoveUploadsExe == 0){
            RemoveUploads();
            RemoveUploadsExe = 1;
          }
          if(NormalizeUploadPrioritiesExe == 0){
            NormalizeUploadPriorities();
            NormalizeUploadPrioritiesExe = 1;
          }
          if(RemoveTempsExe == 0){ 
            RemoveTemps();
            RemoveTempsExe = 1;
          }
          if(VacAnalyzeExe == 0){
            VacAnalyze();
            VacAnalyzeExe = 1;
          }
          break;
      case 'A': /* All operations */
          if(ValidateFoldersExe == 0){
            ValidateFolders();
            ValidateFoldersExe = 1; 
          }
          if(VerifyFilePermsExe == 0){
            VerifyFilePerms(1);
            VerifyFilePermsExe = 1;
          }
          if(RemoveUploadsExe == 0){
            RemoveUploads();
            RemoveUploadsExe = 1;
          }
          if(NormalizeUploadPrioritiesExe == 0){
            NormalizeUploadPriorities();
            NormalizeUploadPrioritiesExe = 1;
          }
          if(RemoveTempsExe == 0){ 
            RemoveTemps();
            RemoveTempsExe = 1;
          }
          if(VacAnalyzeExe == 0){
            VacAnalyze();
            VacAnalyzeExe = 1;
          }
          if(ProcessExpiredExe == 0){ 
            ProcessExpired();
            ProcessExpiredExe = 1;
          }
          if(RemoveOrphanedFilesExe == 0){   
            RemoveOrphanedFiles();
            RemoveOrphanedFilesExe = 1;
          } 
          break;
      case 'D': /* Vac/Analyze (slow) */
          if(VacAnalyzeExe == 0){
            VacAnalyze();
            VacAnalyzeExe = 1;
          }
          break;
      case 'F': /* Validate folder contents */
          if(ValidateFoldersExe == 0){
            ValidateFolders();
            ValidateFoldersExe = 1;
          }
          break;
      case 'g': /* Delete orphan gold files */
          DeleteOrphanGold();
          break;
      case 'h':
            Usage(argv[0]);
            ExitNow(0);
      case 'N': /* Remove uploads with no pfiles */
          if(NormalizeUploadPrioritiesExe == 0){
            NormalizeUploadPriorities();
            NormalizeUploadPrioritiesExe = 1;
          }
          break;
      case 'p': /* Verify file permissions */
          VerifyFilePerms(0);
          break;
      case 'P': /* Verify and fix file permissions */
          if(VerifyFilePermsExe == 0){
            VerifyFilePerms(1);
            VerifyFilePermsExe = 1;
          }
          break;
      case 'R': /* Remove uploads with no pfiles */
          if(RemoveUploadsExe == 0){
            RemoveUploads();
            RemoveUploadsExe = 1;
          } 
          break;
      case 'T': /* Remove orphaned temp tables */
          if(RemoveTempsExe == 0){
            RemoveTemps();
            RemoveTempsExe = 1;
          }
          break;
      case 'U': /* Process expired uploads (slow) */
          if(ProcessExpiredExe == 0){
            ProcessExpired();
            ProcessExpiredExe = 1;
          }
          break;
      case 'Z': /* Remove orphaned files from the repository (slow) */
          if(RemoveOrphanedFilesExe == 0){
            RemoveOrphanedFiles();
            RemoveOrphanedFilesExe = 1;
          } 
          break;
      case 'I': /* Reindexing of database */
          if(reIndexAllTablesExe == 0){   
            reIndexAllTables();
            reIndexAllTablesExe = 1;
          }
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
