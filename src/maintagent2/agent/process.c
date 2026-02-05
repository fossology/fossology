/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015, 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Functions to process a single file and process an upload
 */

#include "maintagent.h"
#include "libfossdb_adapter.h"

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
  char SQL_vacuum[64];
  char SQL_analyze[64];
  strncpy(SQL_analyze, "ANALYZE", sizeof(SQL_analyze));

  /* Determine server major version and use VACUUM FULL for Postgres 17+ */
  int serverVersion =
      PQserverVersion(pgConn); /* returns ver as int, e.g., 170000 */
  int serverMajor = 0;
  if (serverVersion > 0)
  {
    serverMajor = serverVersion / 10000;
  }
  if (serverMajor >= 17)
  {
    snprintf(SQL_vacuum, sizeof(SQL_vacuum), "VACUUM FULL");
  }
  else
  {
    snprintf(SQL_vacuum, sizeof(SQL_vacuum), "VACUUM");
  }

  /* If very verbose (-vvvv) is requested, add VERBOSE to the commands */
  if (agent_verbose >= 4)
  {
    strncat(SQL_vacuum, " VERBOSE",
            sizeof(SQL_vacuum) - strlen(SQL_vacuum) - 1);
    strncat(SQL_analyze, " VERBOSE",
            sizeof(SQL_analyze) - strlen(SQL_analyze) - 1);
  }

  startTime = (long) time(0);
  double _start = now_monotonic_seconds();
  log_action_start("vacAnalyze (2 Queries)");

  /* Prepare descriptions for logs */
  char desc_vac[80];
  char desc_analyze[80];
  snprintf(desc_vac, sizeof(desc_vac), "vacAnalyze: 1/2 - VACUUM");
  if (serverMajor >= 17)
  {
    strncat(desc_vac, " FULL", sizeof(desc_vac) - strlen(desc_vac) - 1);
  }
  if (agent_verbose >= 4)
  {
    strncat(desc_vac, " VERBOSE", sizeof(desc_vac) - strlen(desc_vac) - 1);
  }
  snprintf(desc_analyze, sizeof(desc_analyze), "vacAnalyze: 2/2 - ANALYZE");
  if (agent_verbose >= 4)
  {
    strncat(desc_analyze, " VERBOSE",
            sizeof(desc_analyze) - strlen(desc_analyze) - 1);
  }

  /* Vacuum */
  PQexecCheckClear(-110, SQL_vacuum, __FILE__, __LINE__);
  log_action_end(desc_vac, _start);

  /* Analyze */
  PQexecCheckClear(-111, SQL_analyze, __FILE__, __LINE__);
  log_action_end(desc_analyze, _start);

  endTime = (long) time(0);
  printf("Vacuum/Analyze took %ld seconds\n", endTime - startTime);

  log_action_end("vacAnalyze", _start);

  fo_scheduler_heart(
      1); // Tell the scheduler that we are alive and update item count
  return; // success
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

  double _start = now_monotonic_seconds();
  log_action_start("validateFolders");

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

  log_action_end("validateFolders", _start);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return; // success
}

/**
 * @brief Verify and optionally fix file permissions

 \code
 /usr/share/fossology drwxr-sr-x 15 fossy fossy
 /srv/repository
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
  return; // success
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

  char SQLBuf[MAXSQL];
  char tempTable[64];

  double _start = now_monotonic_seconds();
  log_action_start("removeUploads (4 Queries)");

  startTime = (long)time(0);

  /* Create a session-local temp table and populate it with candidate upload ids */
  snprintf(tempTable, sizeof(tempTable), "tmp_ids_%d", getpid());

  snprintf(SQLBuf, MAXSQL, "CREATE TEMP TABLE %s(id bigint);", tempTable);
  PQexecCheckClear(-130, SQLBuf, __FILE__, __LINE__);
  log_action_end("removeUploads: 1/4 - temp table creation", _start);

  /* Insert first set: upload_fk from uploadtree where parent IS NULL and
   * pfile_fk IS NULL */
  snprintf(SQLBuf, MAXSQL,
           "INSERT INTO %s (id) SELECT upload_fk FROM uploadtree WHERE parent "
           "IS NULL AND pfile_fk IS NULL;",
           tempTable);
  PQexecCheckClear(-131, SQLBuf, __FILE__, __LINE__);
  log_action_end("removeUploads: 2/4 - first set insertion", _start);

  /* Insert second set: uploads not referenced by uploadtree (and other
   * predicates) */
  snprintf(SQLBuf, MAXSQL,
           "INSERT INTO %s (id) SELECT upload_pk FROM upload WHERE upload_pk "
           "NOT IN (SELECT upload_fk FROM uploadtree) AND (expire_action IS "
           "NULL OR expire_action != 'd') AND pfile_fk IS NOT NULL;",
           tempTable);
  PQexecCheckClear(-132, SQLBuf, __FILE__, __LINE__);
  log_action_end("removeUploads: 3/4 - second set insertion", _start);

  /* Now delete via join using the temp table to avoid client-side IN lists */
  snprintf(SQLBuf, MAXSQL,
           "DELETE FROM upload USING %s WHERE upload.upload_pk = %s.id;",
           tempTable, tempTable);
  result = PQexecCheck(-133, SQLBuf, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  log_action_end("removeUploads: 4/4 - deletion", _start);

  endTime = (long)time(0);
  printf("%s Uploads with no pfiles (%ld seconds)\n", countTuples, endTime-startTime);

  log_action_end("removeUploads (4 Queries)", _start);

  /* Drop the session-local temp table created earlier */
  snprintf(SQLBuf, MAXSQL, "DROP TABLE IF EXISTS %s;", tempTable);
  PQexecCheckClear(-134, SQLBuf, __FILE__, __LINE__);

  fo_scheduler_heart(
      1); // Tell the scheduler that we are alive and update item count
  return; // success
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

  double _start = now_monotonic_seconds();
  log_action_start("removeTemps");

  char* SQL = 
      "SELECT table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' "
      "AND table_schema = 'public' AND (table_name ~ '^metaanalysis_[[:digit:]]+$' "
      "OR table_name LIKE 'delup_%');";

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

  log_action_end("removeTemps", _start);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return; // success
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
  return; // success
}

/**
 * @brief Remove orphaned files from the repository (slow)
 * Loop through each file in the repository and make sure there is a pfile
 * table entry.
 * Then make sure the pfile_pk is used by uploadtree.
 * @returns void but writes status to stdout
 */
FUNCTION void removeOrphanedFiles()
{
  long StartTime, EndTime;
  double _start = now_monotonic_seconds();
  log_action_start("removeOrphanedFiles");

  char* repoPath;           ///< Path to fossology repository
  char filesPath[myBUFSIZ]; ///< Path to files directory

  StartTime = (long)time(0);

  repoPath = fo_sysconfig("FOSSOLOGY", "path");
  strncpy(filesPath, repoPath, myBUFSIZ - 1);
  strncat(filesPath, "/localhost/files", myBUFSIZ - 1);

  if (access(filesPath, R_OK | W_OK) != 0)
  {
    LOG_ERROR("Files path is not readable/writeable: '%s'", filesPath);
  }
  else
  {
    recurseDir("files", filesPath, 3);
  }

  EndTime = (long)time(0);
  printf("Remove orphaned files from the repository took %ld seconds\n",
         EndTime - StartTime);

  log_action_end("removeOrphanedFiles", _start);

  return; // success
}

/**
 * @brief Delete orphaned gold files from the repository
 *
 * Loop through each gold file in the repository and make sure there is a pfile
 * entry in the upload table.
 * @returns void but writes status to stdout
 */
FUNCTION void deleteOrphanGold()
{
  long StartTime, EndTime;
  double _start = now_monotonic_seconds();
  log_action_start("deleteOrphanGold");

  char* repoPath;          ///< Path to fossology repository
  char goldPath[myBUFSIZ]; ///< Path to gold directory

  StartTime = (long)time(0);

  repoPath = fo_sysconfig("FOSSOLOGY", "path");
  strncpy(goldPath, repoPath, myBUFSIZ - 1);
  strncat(goldPath, "/localhost/gold", myBUFSIZ - 1);

  if (access(goldPath, R_OK | W_OK) != 0)
  {
    LOG_ERROR("Gold path is not readable/writeable: '%s'", goldPath);
  }
  else
  {
    recurseDir("gold", goldPath, 3);
  }

  EndTime = (long)time(0);
  printf("Remove orphaned gold files from the repository took %ld seconds\n",
         EndTime - StartTime);

  log_action_end("deleteOrphanGold", _start);

  return; // success
}

/**
 * @brief Normalize priority of Uploads
 * @returns void but writes status to stdout
 */
FUNCTION void normalizeUploadPriorities()
{
  long startTime, endTime;
  double _start = now_monotonic_seconds();
  log_action_start("normalizeUploadPriorities (3 Queries)");

  char* SQL1 =
      "CREATE TEMPORARY TABLE tmp_upload_prio(ordprio serial, uploadid int, groupid int)";
  char* SQL2 =
      "INSERT INTO tmp_upload_prio (uploadid, groupid) "
      "(SELECT upload_fk uploadid, group_fk groupid FROM upload_clearing ORDER BY priority ASC);";
  char* SQL3 =
      "UPDATE upload_clearing "
      "SET priority = ordprio FROM tmp_upload_prio "
      "WHERE uploadid=upload_fk AND group_fk=groupid;";

  startTime = (long)time(0);

  PQexecCheckClear(-180, SQL1, __FILE__, __LINE__);
  log_action_end("normalizeUploadPriorities: 1/3", _start);

  PQexecCheckClear(-181, SQL2, __FILE__, __LINE__);
  log_action_end("normalizeUploadPriorities: 2/3", _start);

  PQexecCheckClear(-182, SQL3, __FILE__, __LINE__);
  log_action_end("normalizeUploadPriorities: 3/3", _start);

  endTime = (long)time(0);
  printf("Normalized upload priorities (%ld seconds)\n", endTime-startTime);

  log_action_end("normalizeUploadPriorities (3 Queries)", _start);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return; // success
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
  double _start = now_monotonic_seconds();
  log_action_start("reIndexAllTables");

  char* SQL =
      "SELECT table_catalog FROM information_schema.tables WHERE table_type = "
      "'BASE TABLE' AND table_schema = 'public' AND table_name LIKE"
      "'upload%' LIMIT 1";

  startTime = (long)time(0);

  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) exitNow(-190);

  memset(SQLBuf,'\0',sizeof(SQLBuf));
  snprintf(SQLBuf,sizeof(SQLBuf),"REINDEX DATABASE %s;", PQgetvalue(result, 0, 0));
  PQclear(result);
  PQexecCheckClear(-191, SQLBuf, __FILE__, __LINE__);

  endTime = (long)time(0);
  printf("Time taken for reindexing the database : %ld seconds\n", endTime-startTime);

  log_action_end("reIndexAllTables", _start);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return; // success
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
  double _start = now_monotonic_seconds();
  log_action_start("removeOrphanedRows (6 Queries)");

  char* SQL1 =
      "DELETE FROM uploadtree UT "
      " WHERE NOT EXISTS ( "
      "  SELECT 1 "
      "  FROM upload U "
      "  WHERE UT.upload_fk = U.upload_pk "
      " );";

  char* SQL2 =
      "DELETE FROM clearing_decision AS CD "
      " WHERE NOT EXISTS ( "
      "  SELECT 1 "
      "  FROM uploadtree UT  "
      "  WHERE CD.uploadtree_fk = UT.uploadtree_pk "
      " ) AND CD.scope = '0';";

  char* SQL3 =
      "DELETE FROM clearing_event CE "
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

  char* SQL4 =
      "DELETE FROM clearing_decision_event CDE"
      " WHERE NOT EXISTS ( "
      "  SELECT 1 "
      "  FROM clearing_event CE  "
      "  WHERE CE.clearing_event_pk = CDE.clearing_event_fk "
      " );";

  char* SQL5 =
      "DELETE FROM obligation_map OM "
      " WHERE NOT EXISTS ( "
      "  SELECT 1 "
      "  FROM license_ref LR  "
      "  WHERE OM.rf_fk = LR.rf_pk "
      " );";

  char* SQL6 =
      "DELETE FROM obligation_candidate_map OCM "
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
  log_action_end("removeOrphanedRows: 1/6 - uploadtree", _start);

  result = PQexecCheck(-201, SQL2, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_decision table\n", countTuples);
  fo_scheduler_heart(1);
  log_action_end("removeOrphanedRows: 2/6 - clearing_decision", _start);

  result = PQexecCheck(-202, SQL3, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_event table\n", countTuples);
  fo_scheduler_heart(1);
  log_action_end("removeOrphanedRows: 3/6 - clearing_event", _start);

  result = PQexecCheck(-203, SQL4, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from clearing_decision_event table\n", countTuples);
  fo_scheduler_heart(1);
  log_action_end("removeOrphanedRows: 4/6 - clearing_decision_event", _start);

  result = PQexecCheck(-204, SQL5, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from obligation_map table\n", countTuples);
  fo_scheduler_heart(1);
  log_action_end("removeOrphanedRows: 5/6 - obligation_map", _start);

  result = PQexecCheck(-205, SQL6, __FILE__, __LINE__);
  countTuples = PQcmdTuples(result);
  PQclear(result);
  printf("%s Orphaned records have been removed from obligation_candidate_map table\n", countTuples);
  fo_scheduler_heart(1); // Tell the scheduler that we are alive and update item count
  log_action_end("removeOrphanedRows: 6/6 - obligation_candidate_map", _start);

  endTime = (long)time(0);

  printf("Time taken for removing orphaned rows from database : %ld seconds\n", endTime-startTime);

  log_action_end("removeOrphanedRows (6 Queries)", _start);

  return; // success
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

  double _start = now_monotonic_seconds();
  log_action_start("removeOrphanedLogFiles");

  char* SQL =
      "SELECT jq_pk, jq_log FROM job ja "
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
      PQclear(updateResult);
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

  log_action_end("removeOrphanedLogFiles", _start);

  fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count
  return; // success
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

  double _start = now_monotonic_seconds();
  log_action_start("removeExpiredTokens");

  snprintf(SQL, sizeof(SQL),
          "DELETE FROM personal_access_tokens WHERE (active = 'FALSE' OR expire_on < '%d-%02d-%02d') AND client_id IS NULL",
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

  log_action_end("removeExpiredTokens", _start);

  return; // success
}

/**
 * @brief Delete gold files which are older than specified date
 *
 * List all pfiles which are older than given date and are not used by other
 * upload. Delete all such pfiles from the repository.
 * @returns void but writes status to stdout
 */
FUNCTION void deleteOldGold(char* date)
{
  PGresult* result; // the result of the database access
  int numrows = 0;  // generic return value
  long StartTime, EndTime;
  int countTuples, row, remval;
  int day, month, year; // Date validation
  char* filepath;
  char sql[MAXSQL];

  day = month = year = -1;
  sscanf(date, "%4d-%2d-%2d", &year, &month, &day);
  if (((year < 1900) || (year > 9999)) || ((month < 1) || (month > 12)) ||
      ((day < 1) || (day > 31)))
  {
    LOG_FATAL("Invalid date! Require yyyy-mm-dd, '%s' given.", date);
    exitNow(-144);
  }

  double _start = now_monotonic_seconds();
  log_action_start("deleteOldGold");

  snprintf(sql, MAXSQL,
           "SELECT DISTINCT ON(pfile_pk) "
           "CONCAT(LOWER(pfile_sha1), '.', LOWER(pfile_md5), '.', pfile_size) "
           "AS filename FROM upload INNER JOIN pfile ON pfile_pk = pfile_fk "
           "WHERE upload_ts < '%s' AND pfile_fk NOT IN ("
           "SELECT pfile_fk FROM upload WHERE upload_ts > '%s' "
           "AND pfile_fk IS NOT NULL);",
           date, date);

  StartTime = (long)time(0);

  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
  {
    exitNow(-145);
  }
  countTuples = PQntuples(result);
  if (agent_verbose)
  {
    LOG_DEBUG("Read %d rows from DB.", countTuples);
  }
  /* Loop through the pfiles and remove them from repository */
  for (row = 0; row < countTuples; row++)
  {
    filepath = fo_RepMkPath("gold", PQgetvalue(result, row, 0));
    remval = remove(filepath);
    if (remval < 0)
    {
      if (errno != ENOENT)
      {
        // Removal failed and error != file not exist
        LOG_WARNING("Unable to remove '%s' file.", filepath);
      }
    }
    else
    {
      numrows++;
    }
    free(filepath);
  }
  PQclear(result);

  EndTime = (long)time(0);
  printf("Removed %d old gold files from the repository, took %ld seconds\n",
         numrows, EndTime - StartTime);

  log_action_end("deleteOldGold", _start);

  fo_scheduler_heart(
      1); // Tell the scheduler that we are alive and update item count
  return; // success
}

/*
 * @brief Delete all log files older than given date in olderThan param
 *
 * -# Use find to list all files in a temporary location.
 * -# Use the number of lines in the file to get the total no of files.
 * -# Feed the file to xargs and call rm to remove them.
 * -# Close the fd and unlink the temporary file.
 * @param olderThan Delete logs older than this date (YYYY-MM-DD)
 */
FUNCTION void removeOldLogFiles(const char* olderThan)
{
  time_t current_time = time(0); ///< Now
  time_t shifted_time;           ///< Target time
  time_t ellapsed_time;      ///< Difference between now and target time in days
  struct tm ti = {0};        ///< Input time
  char cmd[myBUFSIZ];        ///< Command to run
  unsigned int numfiles = 0; ///< Number of files removed
  long StartTime, EndTime;
  char ch;
  FILE* tempFile;
  int retval; ///< Return value of find

  double _start = now_monotonic_seconds();
  log_action_start("removeOldLogFiles");
  StartTime = (long)time(0);
  if (sscanf(olderThan, "%d-%d-%d", &ti.tm_year, &ti.tm_mon, &ti.tm_mday) != 3)
  {
    LOG_FATAL("Unable to parse date '%s' in YYYY-MM-DD format.", olderThan);
    exitNow(-148);
  }
  ti.tm_year -= 1900;
  ti.tm_mon -= 1;
  shifted_time = mktime(&ti);
  ellapsed_time = (current_time - shifted_time) / 60 / 60 / 24 - 1;

  char file_template[] = "/tmp/foss-XXXXXX";
  int fd = mkstemp(file_template);

  snprintf(cmd, myBUFSIZ, "/usr/bin/find %s/logs -type f -mtime +%ld -fprint %s",
           fo_sysconfig("FOSSOLOGY", "path"), ellapsed_time, file_template);
  retval = system(cmd); // Find and print files in temp location
  if (!WIFEXITED(retval))
  { // find fail
    LOG_FATAL("Unable run find for logs files.");
    unlink(file_template);
    exitNow(-148);
  }
  tempFile = fdopen(fd, "r");
  if (tempFile == NULL)
  {
    LOG_FATAL("Unable to open temp file.");
    unlink(file_template);
    exitNow(-148);
  }
  while ((ch = fgetc(tempFile)) != EOF)
  {
    if (ch == '\n')
    {
      numfiles++;
    }
  }

  snprintf(cmd, myBUFSIZ, "/usr/bin/xargs --arg-file=%s /bin/rm -f", file_template);
  retval = system(cmd);
  if (!WIFEXITED(retval))
  { // xargs fail
    LOG_FATAL("Unable delete log files with xargs.");
    fclose(tempFile);
    unlink(file_template);
    exitNow(-148);
  }
  fclose(tempFile);
  unlink(file_template);

  EndTime = (long)time(0);

  printf("Removed %d log files.\n", numfiles);

  printf(
      "Removing log files older than '%s' from the repository took %ld "
      "seconds\n",
      olderThan, EndTime - StartTime);

  log_action_end("removeOldLogFiles", _start);

  fo_scheduler_heart(1);
  return;
}
