/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2017, Siemens AG

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
*/

#define _GNU_SOURCE
#include <stdio.h>

#include "database.h"

#define LICENSE_REF_TABLE "ONLY license_ref"
#define DECISION_TYPE_FOR_IRRELEVANT 4

PGresult* queryFileIdsForUploadAndLimits(fo_dbManager* dbManager, int uploadId, long left, long right, long groupId) {
  return fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "queryFileIdsForUploadAndLimits"
      ,
      "SELECT distinct (pfile_fk) FROM ("  
        "SELECT distinct ON(ut.uploadtree_pk, ut.pfile_fk, scopesort) ut.pfile_fk pfile_fk, ut.uploadtree_pk, decision_type,"
          " CASE cd.scope WHEN 1 THEN 1 ELSE 0 END AS scopesort"
        " FROM uploadtree ut "
        " LEFT JOIN clearing_decision cd ON cd.group_fk=$5 AND (ut.uploadtree_pk=cd.uploadtree_fk AND scope=0 OR ut.pfile_fk=cd.pfile_fk AND scope=1) "
        " WHERE upload_fk=$1 and (ufile_mode&x'3C000000'::int)=0 AND (lft between $2 and $3) AND ut.pfile_fk != 0"
        " ORDER BY ut.uploadtree_pk, scopesort, ut.pfile_fk, clearing_decision_pk DESC"
      ") itemView WHERE decision_type!=$4 OR decision_type IS NULL"
      ,
      int, long, long, int, long),
    uploadId, left, right, DECISION_TYPE_FOR_IRRELEVANT, groupId
  );
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
