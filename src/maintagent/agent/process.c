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
 * \file process.c
 * \brief Functions to process a single file and process an upload
 */

#include "maintagent.h"

/**********  Globals  *************/
extern PGconn    *pgConn;        // database connection


/**
 * @brief Do database vacuum and analyze
 *
 * @returns void but writes status to stdout
 */
FUNCTION void VacAnalyze()
{
  PGresult* result; // the result of the database access
  long StartTime, EndTime;
  char *sql="vacuum analyze ";

  StartTime = (long)time(0);

  /* Vacuum and Analyze */
  result = PQexec(pgConn, sql);
  if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) ExitNow(-110);
  PQclear(result);

  EndTime = (long)time(0);
  printf("Vacuum Analyze took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Validate folder and foldercontents tables
 *
 * @returns void but writes status to stdout
 */
FUNCTION void ValidateFolders()
{
  PGresult* result; // the result of the database access
  char *StatStr;
  char *InvalidUploadRefs="DELETE FROM foldercontents WHERE foldercontents_mode = 2 AND child_id NOT IN (SELECT upload_pk FROM upload)";
  char *InvalidUploadtreeRefs="DELETE FROM foldercontents WHERE foldercontents_mode = 4 AND child_id NOT IN (SELECT uploadtree_pk FROM uploadtree)";
  char *UnrefFolders="DELETE FROM folder WHERE folder_pk \
   NOT IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode = 1) AND folder_pk != '1'";
  long StartTime, EndTime;

  StartTime = (long)time(0);

  /* Remove folder contents with invalid upload references */
  result = PQexec(pgConn, InvalidUploadRefs);
  if (fo_checkPQcommand(pgConn, result, InvalidUploadRefs, __FILE__, __LINE__)) ExitNow(-120);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder upload References\n", StatStr);

  /* Remove folder contents with invalid uploadtree references */
  result = PQexec(pgConn, InvalidUploadtreeRefs);
  if (fo_checkPQcommand(pgConn, result, InvalidUploadtreeRefs, __FILE__, __LINE__)) ExitNow(-121);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder uploadtree References\n", StatStr);

  /* Remove unreferenced folders */
  result = PQexec(pgConn, UnrefFolders);
  if (fo_checkPQcommand(pgConn, result, UnrefFolders, __FILE__, __LINE__)) ExitNow(-122);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  printf("%s unreferenced folders\n", StatStr);

  EndTime = (long)time(0);
  printf("Validate folders took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Verify and optionally fix file permissions
 /usr/share/fossology drwxr-sr-x 15 fossy fossy
 /srv/repostitory 
 path  /srv/fossology/repository drwxrws--- 3 fossy fossy
 /etc/fossology drwxr-sr-x 4 fossy fossy
 /usr/local/lib/fossology/ drwxr-sr-x 2 fossy fossy
 *
 * @param fix 0 to report bad permissions, 1 to report and fix them
 * @returns void but writes status to stdout
 */
FUNCTION void VerifyFilePerms(int fix)
{
/*
  long StartTime, EndTime;
  char *RepoPath;

  StartTime = (long)time(0);

  RepoPath = fo_sysconfig("FOSSOLOGY", "path");
  if (stat(RepoPath, &statbuf) == -1)
  {
  }

  EndTime = (long)time(0);
  printf("Verify File Permissions took %ld seconds\n", EndTime-StartTime);
*/
LOG_NOTICE("Verify file permissions is not implemented yet");

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove Uploads with no pfiles
 *
 * @returns void but writes status to stdout
 */
FUNCTION void RemoveUploads()
{
  PGresult* result; // the result of the database access
  long StartTime, EndTime;
  char *StatStr;
  char *sql="DELETE FROM upload WHERE upload_pk  \
    IN (SELECT upload_fk FROM uploadtree WHERE parent IS NULL AND pfile_fk IS NULL)  \
      OR upload_pk NOT IN (SELECT upload_fk FROM uploadtree)";

  StartTime = (long)time(0);

  result = PQexec(pgConn, sql);
  if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) ExitNow(-130);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  EndTime = (long)time(0);
  printf("%s Uploads with no pfiles (%ld seconds)\n", StatStr, EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove orphaned temp tables from deprecated pkgmettagetta and old delagent
 *
 * @returns void but writes status to stdout
 */
FUNCTION void RemoveTemps()
{
  PGresult* result; 
  PGresult* DropResult; 
  int row;
  int NumRows;
  int DroppedCount = 0;
  long StartTime, EndTime;
  char *sql="select table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE'  \
   AND table_schema = 'public' AND (table_name SIMILAR TO '^metaanalysis_[[:digit:]]+$' \
     or table_name similar to '^delup_%')";
  char sqlBuf[1024];

  StartTime = (long)time(0);

  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__)) ExitNow(-140);
  NumRows = PQntuples(result);

  /* Loop through the temp table names, dropping the tables */
  for (row = 0; row < NumRows; row++)
  {
    snprintf(sqlBuf, sizeof(sqlBuf), "drop table %s", PQgetvalue(result, row, 0));
    DropResult = PQexec(pgConn, sqlBuf);
    if (fo_checkPQcommand(pgConn, DropResult, sql, __FILE__, __LINE__)) ExitNow(-141);
    PQclear(DropResult);
    DroppedCount++;
  }

  PQclear(result);
  EndTime = (long)time(0);
  printf("%d Orphaned temp tables were dropped (%ld seconds)\n", DroppedCount, EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Process expired uploads (slow)
 *
 * @returns void but writes status to stdout
 */
FUNCTION void ProcessExpired()
{
/*
  PGresult* result; // the result of the database access
  int numrows;             // generic return value
  long StartTime, EndTime;

  StartTime = (long)time(0);

  EndTime = (long)time(0);
  printf("Process expired uploads took %ld seconds\n", EndTime-StartTime);
*/
LOG_NOTICE("Process expired uploads is not implemented yet");

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove orphaned files from the repository (slow)
 *        Loop through each file in the repository and make sure there is a pfile table entry.
 *        Then make sure the pfile_pk is used by uploadtree.
 *
 * @returns void but writes status to stdout
 */
FUNCTION void RemoveOrphanedFiles()
{
/*
  PGresult* result; // the result of the database access
  int numrows;             // generic return value
  long StartTime, EndTime;

  StartTime = (long)time(0);

  EndTime = (long)time(0);
  printf("Remove orphaned files from the repository took %ld seconds\n", EndTime-StartTime);
*/
LOG_NOTICE("Remove orphaned files from the repository is not implemented yet");

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Delete orphaned gold files from the repository
 *        Loop through each gold file in the repository and make sure there is a pfile entry in the upload table.
 *
 * @returns void but writes status to stdout
 */
FUNCTION void DeleteOrphanGold()
{
/*
  PGresult* result; // the result of the database access
  int numrows;             // generic return value
  long StartTime, EndTime;

  StartTime = (long)time(0);

  EndTime = (long)time(0);
  printf("Remove orphaned files from the repository took %ld seconds\n", EndTime-StartTime);
*/
LOG_NOTICE("Remove orphaned gold files from the repository is not implemented yet");

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}




/**
 * @brief Normalize priority of Uploads
  * @returns void but writes status to stdout
 */
FUNCTION void NormalizeUploadPriorities()
{
  PGresult* result; // the result of the database access
  long StartTime, EndTime;
  char *sql0="drop table if exists tmp_upload_prio";
  char *sql1="create table tmp_upload_prio(ordprio serial,uploadid int)";
  char *sql2="insert into tmp_upload_prio (uploadid) (   select upload_pk uploadid from upload order by priority asc  )";
  char *sql3="UPDATE upload SET priority = ordprio FROM tmp_upload_prio WHERE uploadid=upload_pk; drop table tmp_upload_prio";

  StartTime = (long)time(0);

  result = PQexec(pgConn, sql0);
  if (fo_checkPQcommand(pgConn, result, sql0, __FILE__, __LINE__)) ExitNow(-210);
  PQclear(result);
      
  result = PQexec(pgConn, sql1);
  if (fo_checkPQcommand(pgConn, result, sql1, __FILE__, __LINE__)) ExitNow(-211);
  PQclear(result);

  result = PQexec(pgConn, sql2);
  if (fo_checkPQcommand(pgConn, result, sql2, __FILE__, __LINE__)) ExitNow(-212);
  PQclear(result);

  result = PQexec(pgConn, sql3);
  if (fo_checkPQcommand(pgConn, result, sql3, __FILE__, __LINE__)) ExitNow(-213);
  PQclear(result);
  
  EndTime = (long)time(0);
  printf("Normalized upload priorities (%ld seconds)\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}