/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#define _GNU_SOURCE
#include <stdio.h>

#include "database.h"
#include "libfossdb.h"
#include "libfossdbmanager.h"
#define LICENSE_REF_TABLE "ONLY license_ref"

PGresult* queryFileIdsForUploadAndLimits(fo_dbManager* dbManager, int uploadId, long left, long right) {
  return fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "queryFileIdsForUploadAndLimits",
      "select distinct(pfile_fk) from uploadtree where upload_fk=$1 and (ufile_mode&x'3C000000'::int)=0 and lft between $2 and $3",
      int, long, long),
    uploadId, left, right
  );
}

//TODO use correct parameters to filter only "good" licenses
PGresult* queryAllLicenses(fo_dbManager* dbManager) {
  return fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "queryAllLicenses",
      "select rf_pk, rf_shortname from " LICENSE_REF_TABLE " where rf_detector_type = 1"
    )
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
    return "";
  }

  char* result = strdup(PQgetvalue(licenseTextResult, 0, 0));
  PQclear(licenseTextResult);
  return result;
}

char* getFileNameForFileId(fo_dbManager* dbManager, long pFileId) {
 //TODO refactor to use filetreeBounds struct as input
 
  char* result;
  PGresult* resultUploadFilename = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "getFileNameForFileId",
      "select ufile_name from uploadtree where pfile_fk = $1",
      long),
    pFileId
  );

  if (!resultUploadFilename)
    return NULL;

  if (PQntuples(resultUploadFilename) == 0) {
    PQclear(resultUploadFilename);
    return NULL;
  }

  result = strdup(PQgetvalue(resultUploadFilename, 0, 0));
  PQclear(resultUploadFilename);
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

inline int saveDiffHighlightToDb(fo_dbManager* dbManager, DiffMatchInfo* diffInfo, long licenseFileId) {
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

int saveDiffHighlightsToDb(fo_dbManager* dbManager, GArray* matchedInfo, long licenseFileId) {
  size_t matchedInfoLen = matchedInfo->len ;
  for (size_t i = 0; i < matchedInfoLen; i++) {
    DiffMatchInfo* diffMatchInfo = &g_array_index(matchedInfo, DiffMatchInfo, i);
    if (!saveDiffHighlightToDb(dbManager, diffMatchInfo, licenseFileId))
      return 0;
  }

  return 1;
};
