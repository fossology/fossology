/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#define _GNU_SOURCE
#include <libfossology.h>
#include <string.h>
#include <stddef.h>

#include "bulk.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "monk.h"

int setLeftAndRight(MonkState* state) {
  /* if we are scanning from a file we want to extend left and right to its parent
   * (we want to scan all its siblings), if it is a container we want all the contents
   */

  PGresult* leftAndRightResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "setLeftAndRight",
      "WITH isFile AS ("
      "SELECT 1 FROM uploadtree WHERE (ufile_mode&x'3C000000'::int)=0 AND uploadtree_pk = $1 LIMIT 1"
      "),"
      "dirResult AS ("
      "SELECT lft, rgt FROM uploadtree WHERE"
      " NOT EXISTS(SELECT * FROM isFile)"
      " AND uploadtree_pk = $1"
      "),"
      "fileResult AS ("
      "SELECT lft, rgt FROM uploadtree WHERE"
      " EXISTS(SELECT * FROM isFile)"
      " AND uploadtree_pk = (SELECT parent FROM uploadtree WHERE uploadtree_pk = $1)"
      ")"
      "SELECT * FROM fileResult UNION SELECT * FROM dirResult",
      long
    ),
    state->bulkArguments->uploadTreeId
  );

  int result = 0;

  if (leftAndRightResult) {
    if (PQntuples(leftAndRightResult)==1) {
      BulkArguments* bulkArguments = state->bulkArguments;

      int i = 0;
      bulkArguments->uploadTreeLeft = atol(PQgetvalue(leftAndRightResult, 0, i++));
      bulkArguments->uploadTreeRight = atol(PQgetvalue(leftAndRightResult, 0, i));

      result = 1;
    }
    PQclear(leftAndRightResult);
  }
  return result;
}

int queryBulkArguments(long bulkId, MonkState* state) {
  PGresult* bulkArgumentsResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "queryBulkArguments",
      "SELECT upload_fk, uploadtree_pk, user_fk, group_fk, rf_fk, rf_text, removing "
      "FROM license_ref_bulk INNER JOIN uploadtree "
      "ON uploadtree.uploadtree_pk = license_ref_bulk.uploadtree_fk "
      "WHERE lrb_pk = $1",
      long
    ),
    bulkId
  );

  int result = 0;

  if (bulkArgumentsResult) {
    if (PQntuples(bulkArgumentsResult)==1) {
      BulkArguments* bulkArguments = malloc(sizeof(BulkArguments));

      int i = 0;
      bulkArguments->uploadId = atol(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->uploadTreeId = atol(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->userId = atoi(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->groupId = atoi(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->licenseId = atol(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->refText = g_strdup(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->removing = (strcmp(PQgetvalue(bulkArgumentsResult, 0, i), "t") == 0);


      bulkArguments->bulkId = bulkId;

      state->bulkArguments = bulkArguments;

      if (!setLeftAndRight(state)) {
        bulkArguments_contents_free(state->bulkArguments);
      } else {
        result = 1;
      }
    }
    PQclear(bulkArgumentsResult);
  }
  return result;
}

void bulkArguments_contents_free(BulkArguments* bulkArguments) {
  g_free(bulkArguments->refText);

  free(bulkArguments);
}

int bulk_identification(MonkState* state) {
  BulkArguments* bulkArguments = state->bulkArguments;

  License license = (License){
    .refId = bulkArguments->licenseId,
  };
  license.tokens = tokenize(bulkArguments->refText, DELIMITERS);

  GArray* licenses = g_array_new(TRUE, FALSE, sizeof (License));
  g_array_append_val(licenses, license);

  PGresult* filesResult = queryFileIdsForUploadAndLimits(
    state->dbManager,
    bulkArguments->uploadId,
    bulkArguments->uploadTreeLeft,
    bulkArguments->uploadTreeRight
  );

  int haveError = 1;
  if (filesResult != NULL) {
    int resultsCount = PQntuples(filesResult);
    haveError = 0;
#ifdef MONK_MULTI_THREAD
    #pragma omp parallel
#endif
    {
      MonkState threadLocalStateStore = *state;
      MonkState* threadLocalState = &threadLocalStateStore;

      threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
      if (threadLocalState->dbManager) {
#ifdef MONK_MULTI_THREAD
        #pragma omp for schedule(dynamic)
#endif
        for (int i = 0; i<resultsCount; i++) {
          long fileId = atol(PQgetvalue(filesResult, i, 0));

          // this will call onFullMatch_Bulk if it finds matches
          matchPFileWithLicenses(threadLocalState, fileId, licenses);
          fo_scheduler_heart(1);
        }
        fo_dbManager_finish(threadLocalState->dbManager);
      } else {
        haveError = 1;
      }
    }
    PQclear(filesResult);
  }

  freeLicenseArray(licenses);

  return !haveError;
}

int handleBulkMode(MonkState* state, long bulkId) {
  if (queryBulkArguments(bulkId, state)) {
    BulkArguments* bulkArguments = state->bulkArguments;

    int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                            0, bulkArguments->uploadId, state->agentId, AGENT_ARS, NULL, 0);

    int result = bulk_identification(state);

    fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                arsId, bulkArguments->uploadId, state->agentId, AGENT_ARS, NULL, 1);

    return result;
  } else {
    return 0;
  }
}

void onFullMatch_Bulk(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  PGresult* highlightResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "saveBulkResult:highlight",
      "INSERT INTO highlight_bulk(lrb_fk, pfile_fk, start, len) VALUES($1,$2,$3,$4)",
      long, long, size_t, size_t
    ),
    state->bulkArguments->bulkId,
    file->id,
    matchInfo->text.start,
    matchInfo->text.length
  );

  /* ignore errors */
  if (highlightResult)
    PQclear(highlightResult);

  /* we add a clearing decision for each uploadtree_fk corresponding to this pfile_fk
   * For each bulk scan scan we only have a n the other hand we have only one license per clearing decision
   */
  PGresult* clearingInsertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "saveBulkResult:clearing",
      "WITH clearingIds AS ("
      " INSERT INTO clearing_decision(uploadtree_fk, pfile_fk, user_fk, type_fk, scope_fk)"
      "  SELECT uploadtree_pk, $1, $2, type_pk, scope_pk"
      "  FROM uploadtree, clearing_decision_types, clearing_decision_scopes"
      "  WHERE upload_fk = $3 AND pfile_fk = $1 AND lft BETWEEN $6 AND $7"
      "  AND clearing_decision_types.meaning = '" BULK_DECISION_TYPE "'"
      "  AND clearing_decision_scopes.meaning = '" BULK_DECISION_SCOPE "'"
      " RETURNING clearing_pk "
      ")"
      "INSERT INTO clearing_licenses(clearing_fk, rf_fk, removed) "
      "SELECT clearing_pk,$4,$5 FROM clearingIds",
      long, long, int, long, int, long, long
    ),
    file->id,
    state->bulkArguments->userId,
    state->bulkArguments->uploadId,
    license->refId,
    state->bulkArguments->removing ? 1 : 0,
    state->bulkArguments->uploadTreeLeft,
    state->bulkArguments->uploadTreeRight
  );

  /* ignore errors */
  if (clearingInsertResult)
    PQclear(clearingInsertResult);
}