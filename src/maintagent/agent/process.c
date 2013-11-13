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
 * \file process.c
 * \brief Functions to process a single file and process an upload
 */

#include "maintagent.h"

/**********  Globals  *************/
extern PGconn    *pgConn;        // database connection


/**
 * @brief Do database vacuum and analyze
 *
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void VacAnalyze()
{
  PGresult* result; // the result of the database access
  long StartTime, EndTime;
  char *sql="vacuum analyze ";

  StartTime = (long)time(0);

  /* Vacuume and Analyze */
  result = PQexec(pgConn, sql);
  if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) ExitNow(-102);
  PQclear(result);

  EndTime = (long)time(0);
  printf("Vacuum Analyze took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Validate folder and foldercontents tables
 *
 * @returns void but writes status to LOG_NOTICE
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
  if (fo_checkPQcommand(pgConn, result, InvalidUploadRefs, __FILE__, __LINE__)) ExitNow(-100);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder upload References\n", StatStr);

  /* Remove folder contents with invalid uploadtree references */
  result = PQexec(pgConn, InvalidUploadtreeRefs);
  if (fo_checkPQcommand(pgConn, result, InvalidUploadtreeRefs, __FILE__, __LINE__)) ExitNow(-101);
  StatStr = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder uploadtree References\n", StatStr);

  /* Remove unreferenced folders */
  result = PQexec(pgConn, UnrefFolders);
  if (fo_checkPQcommand(pgConn, result, UnrefFolders, __FILE__, __LINE__)) ExitNow(-102);
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
 *
 * @param fix 0 to report bad permissions, 1 to report and fix them
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void VerifyFilePerms(int fix)
{
  long StartTime, EndTime;

  StartTime = (long)time(0);

LOG_NOTICE("Verify File Permissions is not implemented yet");

  EndTime = (long)time(0);
  printf("Verify File Permissions took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove Uploads with no pfiles
 *
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void RemoveUploads()
{
//  PGresult* result; // the result of the database access
//  int numrows;             // generic return value
  long StartTime, EndTime;
//  char *sql="vacuum analyze";

  StartTime = (long)time(0);

/*
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) ExitNow(-102);
  PQclear(result);
*/
LOG_NOTICE("Remove uploads with no pfiles is not implemented yet");

  EndTime = (long)time(0);
  printf("Remove uploads with no pfiles took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove orphaned temp files
 *
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void RemoveTemps()
{
//  PGresult* result; // the result of the database access
//  int numrows;             // generic return value
  long StartTime, EndTime;
//  char *sql="vacuum analyze";

  StartTime = (long)time(0);

  /* Vacuume and Analyze */
/*
  result = PQexec(pgConn, VacAnalyze);
  if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) ExitNow(-102);
  PQclear(result);
*/
LOG_NOTICE("Remove orphaned temp files is not implemented yet");

  EndTime = (long)time(0);
  printf("Remove orphaned temp files took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Process expired uploads (slow)
 *
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void ProcessExpired()
{
//  PGresult* result; // the result of the database access
//  int numrows;             // generic return value
  long StartTime, EndTime;
//  char *sql="vacuum analyze";

  StartTime = (long)time(0);

  /* Vacuume and Analyze */
/*
  result = PQexec(pgConn, VacAnalyze);
  if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) ExitNow(-102);
  PQclear(result);
*/
LOG_NOTICE("Process expired uploads is not implemented yet");

  EndTime = (long)time(0);
  printf("Process expired uploads took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}


/**
 * @brief Remove orphaned files from the repository (slow)
 *
 * @returns void but writes status to LOG_NOTICE
 */
FUNCTION void RemoveOrphanedFiles()
{
//  PGresult* result; // the result of the database access
//  int numrows;             // generic return value
  long StartTime, EndTime;
//  char *sql="vacuum analyze";

  StartTime = (long)time(0);

  /* Vacuume and Analyze */
/*
  result = PQexec(pgConn, VacAnalyze);
  if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) ExitNow(-102);
  PQclear(result);
*/
LOG_NOTICE("Remove orphaned files from the repository is not implemented yet");

  EndTime = (long)time(0);
  printf("Remove orphaned files from the repository took %ld seconds\n", EndTime-StartTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}
