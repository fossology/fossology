/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014-2015,2019 Siemens AG

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
 * \brief Functions to process a single file and process an upload
 */

#include "maintagent.h"

/**********  Globals  *************/
PGconn* pgConn = NULL;        ///< the connection to Database
fo_dbManager* dbManager;      ///< fo_dbManager object

/**
 * \brief simple wrapper which includes PQexec and fo_checkPQcommand
 * \param exitNumber exit number
 * \param SQL  SQL command executed
 * \param file source file name
 * \param line source line number
 * \return PQexec query result
 */
PGresult * PQexecCheck(int exitNumber, char *SQL, char *file, const int line)
{
  PGresult *result;
  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, file, line)) {
    PQclear(result);
    exitNow(exitNumber);
  }
  return result;
}

/**
 * \brief Execute SQL query and create the result
 * and clear the result.
 * \see PQexecCheck()
 */
FUNCTION void PQexecCheckClear(int exitNumber, char *SQL, char *file, const int line)
{
  PGresult *result;
  result = PQexecCheck(exitNumber, SQL, file, line);
  PQclear(result);
}

/**
 * @brief Do database vacuum and analyze
 * @returns void but writes status to stdout
 */
FUNCTION void vacAnalyze()
{
  long startTime, endTime;
  char *SQL = "VACUUM ANALYZE";

  startTime = (long)time(0);

  /* Vacuum and Analyze */
  PQexecCheckClear(-110, SQL, __FILE__, __LINE__);

  endTime = (long)time(0);
  printf("Vacuum Analyze took %ld seconds\n", endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief Validate folder and foldercontents tables
 *
 * @returns void but writes status to stdout
 */
FUNCTION void validateFolders()
{
  PGresult* result; // the result of the database access
  char *countTuples;

  char *invalidUploadRefs = "DELETE FROM foldercontents WHERE foldercontents_mode = 2 AND child_id NOT IN (SELECT upload_pk FROM upload)";
  char *invalidUploadtreeRefs = "DELETE FROM foldercontents WHERE foldercontents_mode = 4 AND child_id NOT IN (SELECT uploadtree_pk FROM uploadtree)";
  char *unRefFolders = "DELETE FROM folder WHERE folder_pk \
                        NOT IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode = 1) AND folder_pk != '1'";
  long startTime, endTime;

  startTime = (long)time(0);

  /* Remove folder contents with invalid upload references */
  result = PQexecCheck(-120, invalidUploadRefs, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder upload References\n", countTuples);

  /* Remove folder contents with invalid uploadtree references */
  result = PQexecCheck(-121, invalidUploadtreeRefs, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Invalid folder uploadtree References\n", countTuples);

  /* Remove unreferenced folders */
  result = PQexecCheck(-122, unRefFolders, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s unreferenced folders\n", countTuples);

  endTime = (long)time(0);
  printf("Validate folders took %ld seconds\n", endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief Verify and optionally fix file permissions

 \code
 /usr/share/fossology drwxr-sr-x 15 fossy fossy
 /srv/repostitory
 path /srv/fossology/repository drwxrws--- 3 fossy fossy
 /etc/fossology drwxr-sr-x 4 fossy fossy
 /usr/local/lib/fossology/ drwxr-sr-x 2 fossy fossy
 \endcode
 *
 * @param fix 0 to report bad permissions, 1 to report and fix them
 * @returns void but writes status to stdout
 * @todo Verify file permissions is not implemented yet
 */
FUNCTION void verifyFilePerms(int fix)
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
 * @returns void but writes status to stdout
 * @todo Optimize query
 */
FUNCTION void removeUploads()
{
  PGresult* result; // the result of the database access
  char *countTuples;
  long startTime, endTime;

  char *SQL = "DELETE FROM upload WHERE upload_pk  \
               IN (SELECT upload_fk FROM uploadtree WHERE parent IS NULL AND pfile_fk IS NULL)  \
               OR (upload_pk NOT IN (SELECT upload_fk FROM uploadtree) \
                 AND (expire_action IS NULL OR expire_action != 'd') AND pfile_fk IS NOT NULL)";

  startTime = (long)time(0);

  result = PQexecCheck(-130, SQL, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);

  endTime = (long)time(0);
  printf("%s Uploads with no pfiles (%ld seconds)\n", countTuples, endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief Remove orphaned temp tables from deprecated pkgmettagetta and old delagent
 * @returns void but writes status to stdout
 */
FUNCTION void removeTemps()
{
  PGresult* result;
  int row;
  int countTuples;
  int droppedCount = 0;
  char SQLBuf[MAXSQL];
  long startTime, endTime;

  char *SQL = "SELECT table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE'"
              "AND table_schema = 'public' AND (table_name SIMILAR TO '^metaanalysis_[[:digit:]]+$'"
              "OR table_name SIMILAR TO '^delup_%');";

  startTime = (long)time(0);

  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) exitNow(-140);
  countTuples = PQntuples(result);
  /* Loop through the temp table names, dropping the tables */
  for (row = 0; row < countTuples; row++) {
    snprintf(SQLBuf, MAXSQL, "DROP TABLE %s", PQgetvalue(result, row, 0));
    PQexecCheckClear(-141, SQLBuf, __FILE__, __LINE__);
    droppedCount++;
  }
  PQclear(result);

  endTime = (long)time(0);
  printf("%d Orphaned temp tables were dropped (%ld seconds)\n", droppedCount, endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief Process expired uploads (slow)
 * @returns void but writes status to stdout
 * @todo Process expired uploads is not implemented yet
 */
FUNCTION void processExpired()
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
 * Loop through each file in the repository and make sure there is a pfile table entry.
 * Then make sure the pfile_pk is used by uploadtree.
 * @returns void but writes status to stdout
 * @todo Remove orphaned files from the repository is not implemented yet
 */
FUNCTION void removeOrphanedFiles()
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
 * Loop through each gold file in the repository and make sure there is a pfile entry in the upload table.
 * @returns void but writes status to stdout
 * @todo Remove orphaned gold files from the repository is not implemented yet
 */
FUNCTION void deleteOrphanGold()
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
FUNCTION void normalizeUploadPriorities()
{
  long startTime, endTime;

  char *SQL1 = "CREATE TEMPORARY TABLE tmp_upload_prio(ordprio serial, uploadid int, groupid int)";
  char *SQL2 = "INSERT INTO tmp_upload_prio (uploadid, groupid) (SELECT upload_fk uploadid, group_fk groupid FROM upload_clearing ORDER BY priority ASC);";
  char *SQL3 = "UPDATE upload_clearing SET priority = ordprio FROM tmp_upload_prio WHERE uploadid=upload_fk AND group_fk=groupid;";

  startTime = (long)time(0);

  PQexecCheckClear(-180, SQL1, __FILE__, __LINE__);
  PQexecCheckClear(-181, SQL2, __FILE__, __LINE__);
  PQexecCheckClear(-182, SQL3, __FILE__, __LINE__);

  endTime = (long)time(0);
  printf("Normalized upload priorities (%ld seconds)\n", endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief reindex of all indexes in fossology database
 * @returns void but writes status to stdout
 */
FUNCTION void reIndexAllTables()
{
  PGresult* result; // the result of the database access
  char SQLBuf[MAXSQL];
  long startTime, endTime;
  char *SQL= "SELECT table_catalog FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema = 'public' AND (table_name SIMILAR TO 'upload%') LIMIT 1";

  startTime = (long)time(0);

  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) exitNow(-190);

  memset(SQLBuf,'\0',sizeof(SQLBuf));
  snprintf(SQLBuf,sizeof(SQLBuf),"REINDEX DATABASE %s;", PQgetvalue(result, 0, 0));
  PQclear(result);
  PQexecCheckClear(-191, SQLBuf, __FILE__, __LINE__);

  endTime = (long)time(0);
  printf("Time taken for reindexing the database : %ld seconds\n", endTime-startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief remove orphaned rows from fossology database
 * @returns void but writes status to stdout
 */
FUNCTION void removeOrphanedRows()
{
  PGresult* result; // the result of the database access
  char *countTuples;
  long startTime, endTime;

  char *SQL1 = "DELETE FROM uploadtree UT "
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM upload U "
               "  WHERE UT.upload_fk = U.upload_pk "
               " );";

  char *SQL2 = "DELETE FROM clearing_decision AS CD "
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM uploadtree UT  "
               "  WHERE CD.uploadtree_fk = UT.uploadtree_pk "
               " ) AND CD.scope = '0';";

  char *SQL3 = "DELETE FROM clearing_event CE "
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM uploadtree UT  "
               "  WHERE CE.uploadtree_fk = UT.uploadtree_pk "
               " ) AND NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM clearing_decision CD"
               "  WHERE CD.uploadtree_fk = CE.uploadtree_fk "
               "  AND CD.scope = '1'"
               " );";

  char *SQL4 = "DELETE FROM clearing_decision_event CDE"
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM clearing_event CE  "
               "  WHERE CE.clearing_event_pk = CDE.clearing_event_fk "
               " );";

  char *SQL5 = "DELETE FROM obligation_map OM "
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM license_ref LR  "
               "  WHERE OM.rf_fk = LR.rf_pk "
               " );";

  char *SQL6 = "DELETE FROM obligation_candidate_map OCM "
               " WHERE NOT EXISTS ( "
               "  SELECT 1 "
               "  FROM license_ref LR  "
               "  WHERE OCM.rf_fk = LR.rf_pk "
               " );";

  startTime = (long)time(0);

  result = PQexecCheck(-200, SQL1, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from uploadtree table\n", countTuples);
  fo_scheduler_heart(1);

  result = PQexecCheck(-201, SQL2, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_decision table\n", countTuples);
  fo_scheduler_heart(1);

  result = PQexecCheck(-202, SQL3, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_event table\n", countTuples);
  fo_scheduler_heart(1);

  result = PQexecCheck(-203, SQL4, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_decision_event table\n", countTuples);
  fo_scheduler_heart(1);

  result = PQexecCheck(-204, SQL5, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from obligation_map table\n", countTuples);
  fo_scheduler_heart(1);

  result = PQexecCheck(-205, SQL6, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from obligation_candidate_map table\n", countTuples);
  fo_scheduler_heart(1); // Tell the scheduler that we are alive and update item count

  endTime = (long)time(0);

  printf("Time taken for removing orphaned rows from database : %ld seconds\n", endTime-startTime);

  return;  // success
}


/**
 * Remove orphan log files created to store the logs from agents on disk.
 * @returns void but writes status to stdout
 */
FUNCTION void removeOrphanedLogFiles()
{
  PGresult* result;
  PGresult* updateResult;
  int row;
  int countTuples;
  int removedCount = 0;
  int jobQueueId;
  char* logPath;
  long startTime, endTime;
  fo_dbManager_PreparedStatement* updateStatement;
  struct stat statbuf;

  char *SQL = "SELECT jq_pk, jq_log FROM job ja "
    "INNER JOIN job jb ON ja.job_upload_fk = jb.job_upload_fk "
    "INNER JOIN jobqueue jq ON jb.job_pk = jq.jq_job_fk "
    "WHERE ja.job_name = 'Delete' AND jq_log IS NOT NULL "
    "AND jq_log != 'removed';";

  startTime = (long)time(0);

  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    exitNow(-140);
  }
  countTuples = PQntuples(result);

  updateStatement = fo_dbManager_PrepareStamement(dbManager,
    "updateRemovedLogFromJobqueue",
    "UPDATE jobqueue SET jq_log = 'removed' WHERE jq_pk = $1;", int);
  /* Loop through the logs found and delete them. Also update the database */
  for (row = 0; row < countTuples; row++)
  {
    fo_dbManager_begin(dbManager);
    jobQueueId = atoi(PQgetvalue(result, row, 0));
    logPath = PQgetvalue(result, row, 1);
    if (stat(logPath, &statbuf) == -1)
    {
      LOG_NOTICE("Log file '%s' does not exists", logPath);
    }
    else
    {
      remove(logPath);
      LOG_VERBOSE2("Removed file '%s'", logPath);
    }
    updateResult = fo_dbManager_ExecPrepared(updateStatement, jobQueueId);
    if (updateResult)
    {
      free(updateResult);
      removedCount++;
    }
    else
    {
      LOG_ERROR("Unable to update the value of jobqueue.jq_log to 'removed' "
        "for jq_pk = %d", jobQueueId);
    }
    fo_dbManager_commit(dbManager);
  }
  PQclear(result);

  endTime = (long)time(0);
  printf("%d / %d Orphaned log files were removed "
    "(%ld seconds)\n", removedCount, countTuples, endTime - startTime);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return;  // success
}

/**
 * @brief remove expired personal access tokens from fossology database
 * @returns void but writes status to stdout
 */
FUNCTION void removeExpiredTokens(long int retentionPeriod)
{
  PGresult* result; // the result of the database access
  char *countTuples;
  long startTime, endTime;
  char SQL[1024];
  time_t current_time = time(0);
  time_t shifted_time = current_time - (retentionPeriod*24*60*60);
  struct tm time_structure = *localtime(&shifted_time);

  snprintf(SQL, sizeof(SQL),
          "DELETE FROM personal_access_tokens WHERE active = 'FALSE' OR expire_on < '%d-%02d-%02d'",
          time_structure.tm_year + 1900,
          time_structure.tm_mon + 1,
          time_structure.tm_mday
  );

  startTime = (long)time(0);

  result = PQexecCheck(-220, SQL, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Expired personal access tokens have been removed from personal_access_tokens table\n", countTuples);
  fo_scheduler_heart(1);

  endTime = (long)time(0);

  printf("Time taken for removing expired personal access tokens from database : %ld seconds\n", endTime-startTime);

  return;  // success
}
