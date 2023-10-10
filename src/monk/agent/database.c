/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2017, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#define _GNU_SOURCE
#include <stdio.h>

#include "database.h"

#define LICENSE_REF_TABLE "ONLY license_ref"

PGresult* queryFileIdsForUploadAndLimits(fo_dbManager* dbManager, int uploadId,
                                         long left, long right, long groupId,
                                         bool ignoreIrre, bool scanFindings) {
  char* tablename = getUploadTreeTableName(dbManager, uploadId);
  gchar* stmt;
  gchar* sql;
  PGresult* result;

  char* distinctPfile = "SELECT DISTINCT pfile_fk FROM %s"
                        " WHERE upload_fk = $1 AND (ufile_mode&x'3C000000'::int) = 0 "
                        " AND (lft BETWEEN $2 AND $3) AND pfile_fk != 0";
  char* distinctPfileNoDec = "SELECT DISTINCT pfile_fk FROM ("
                             "SELECT distinct ON(ut.uploadtree_pk, ut.pfile_fk, scopesort) ut.pfile_fk pfile_fk, ut.uploadtree_pk, decision_type,"
                             " CASE cd.scope WHEN 1 THEN 1 ELSE 0 END AS scopesort"
                             " FROM %s AS ut "
                             " LEFT JOIN clearing_decision cd ON "
                             "  ((ut.uploadtree_pk = cd.uploadtree_fk AND scope = 0 AND cd.group_fk = $5) "
                             "  OR (ut.pfile_fk = cd.pfile_fk AND scope = 1)) "
                             " WHERE upload_fk=$1 AND (ufile_mode&x'3C000000'::int)=0 AND (lft BETWEEN $2 AND $3) AND ut.pfile_fk != 0"
                             " ORDER BY ut.uploadtree_pk, scopesort, ut.pfile_fk, clearing_decision_pk DESC"
                             ") itemView WHERE decision_type!=$4 OR decision_type IS NULL";
  char* nonVoidPfile = "SELECT pfile_fk FROM allPfileData"
                       " WHERE pfile_fk NOT IN (SELECT pfile_fk FROM license_file WHERE rf_fk IN"
                       " (SELECT rf_pk FROM " LICENSE_REF_TABLE
                       " WHERE rf_shortname = ANY(VALUES('No_license_found'), ('Void'))))";

  if (!ignoreIrre && !scanFindings)
  {
    sql = g_strdup_printf(distinctPfile, tablename);
    stmt = g_strdup_printf("queryFileIdsForUploadAndLimits.%s", tablename);
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        stmt,
        sql,
        int, long, long),
      uploadId, left, right
    );
  }
  else if(!ignoreIrre && scanFindings)
  {
    sql = g_strdup_printf(
      g_strconcat("WITH allPfileData AS (", distinctPfile, ") ", nonVoidPfile,
        NULL), tablename);
    stmt = g_strdup_printf("queryFileIdsForUploadAndLimitswithlicensefinding.%s", tablename);
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        stmt,
        sql,
        int, long, long),
      uploadId, left, right
    );
  }
  else if(ignoreIrre && scanFindings)
  {
    sql = g_strdup_printf(
      g_strconcat("WITH allPfileData AS (", distinctPfileNoDec, ") ",
        nonVoidPfile, NULL), tablename);
    stmt = g_strdup_printf("queryFileIdsForUploadAndLimits.%s.ignoreirreandscanFindings", tablename);
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        stmt,
        sql,
        int, long, long, int, long),
      uploadId, left, right, DECISION_TYPE_FOR_IRRELEVANT, groupId
    );
  }
  else
  {
    sql = g_strdup_printf(distinctPfileNoDec, tablename);
    stmt = g_strdup_printf("queryFileIdsForUploadAndLimits.%s.ignoreirre", tablename);
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        stmt,
        sql,
        int, long, long, int, long),
      uploadId, left, right, DECISION_TYPE_FOR_IRRELEVANT, groupId
    );
  }
  g_free(sql);
  g_free(stmt);
  return result;
}

PGresult* queryAllLicenses(fo_dbManager* dbManager) {
  return fo_dbManager_Exec_printf(
    dbManager,
    "select rf_pk, rf_shortname from " LICENSE_REF_TABLE " where rf_detector_type = 1 and rf_active = 'true'"
  );
}

char* getLicenseTextForLicenseRefId(fo_dbManager* dbManager, long refId) {
  PGresult* licenseTextResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "getLicenseTextForLicenseRefId",
      "select rf_text from " LICENSE_REF_TABLE " where rf_pk = $1",
      long),
    refId
  );

  if (PQntuples(licenseTextResult) != 1) {
    printf("cannot find license text!\n");
    PQclear(licenseTextResult);
    return g_strdup("");
  }

  char* result = g_strdup(PQgetvalue(licenseTextResult, 0, 0));
  PQclear(licenseTextResult);
  return result;
}

int hasAlreadyResultsFor(fo_dbManager* dbManager, int agentId, long pFileId) {
  PGresult* insertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "hasAlreadyResultsFor",
      "SELECT 1 WHERE EXISTS (SELECT 1"
      " FROM license_file WHERE agent_fk = $1 AND pfile_fk = $2"
      ")",
      int, long),
    agentId, pFileId
  );

  int exists = 0;
  if (insertResult) {
    exists = (PQntuples(insertResult) == 1);
    PQclear(insertResult);
  }

  return exists;
}

int saveNoResultToDb(fo_dbManager* dbManager, int agentId, long pFileId) {
  PGresult* insertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "saveNoResultToDb",
      "insert into license_file(agent_fk, pfile_fk) values($1,$2)",
      int, long),
    agentId, pFileId
  );

  int result = 0;
  if (insertResult) {
    result = 1;
    PQclear(insertResult);
  }

  return result;
}

long saveToDb(fo_dbManager* dbManager, int agentId, long refId, long pFileId, unsigned percent) {
  PGresult* insertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "saveToDb",
      "insert into license_file(rf_fk, agent_fk, pfile_fk, rf_match_pct) values($1,$2,$3,$4) RETURNING fl_pk",
      long, int, long, unsigned),
    refId, agentId, pFileId, percent
  );

  long licenseFilePk = -1;
  if (insertResult) {
    if (PQntuples(insertResult) == 1) {
      licenseFilePk = atol(PQgetvalue(insertResult, 0, 0));
    }
    PQclear(insertResult);
  }

  return licenseFilePk;
}

int saveDiffHighlightToDb(fo_dbManager* dbManager, const DiffMatchInfo* diffInfo, long licenseFileId) {
  PGresult* insertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "saveDiffHighlightToDb",
      "insert into highlight(fl_fk, type, start, len, rf_start, rf_len) values($1,$2,$3,$4,$5,$6)",
      long, char*, size_t, size_t, size_t, size_t),
    licenseFileId,
    diffInfo->diffType,
    diffInfo->text.start, diffInfo->text.length,
    diffInfo->search.start, diffInfo->search.length
  );

  if (!insertResult)
    return 0;

  PQclear(insertResult);

  return 1;
}

int saveDiffHighlightsToDb(fo_dbManager* dbManager, const GArray* matchedInfo, long licenseFileId) {
  size_t matchedInfoLen = matchedInfo->len ;
  for (size_t i = 0; i < matchedInfoLen; i++) {
    DiffMatchInfo* diffMatchInfo = &g_array_index(matchedInfo, DiffMatchInfo, i);
    if (!saveDiffHighlightToDb(dbManager, diffMatchInfo, licenseFileId))
      return 0;
  }

  return 1;
}
