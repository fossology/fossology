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

int queryDecisionType(MonkState* state) {
  PGresult* bulkDecisionType = fo_dbManager_Exec_printf(
    state->dbManager,
    "SELECT type_pk FROM license_decision_types WHERE meaning = '" BULK_DECISION_TYPE "'"
  );

  int result = 0;

  if (bulkDecisionType) {
    if (PQntuples(bulkDecisionType)==1) {
       int decisionType = atoi(PQgetvalue(bulkDecisionType,0,0));
       state->bulkArguments->decisionType = decisionType;
       result = 1;
    }
    PQclear(bulkDecisionType);
  }

  if (!result) {
    printf("FATAL: could not read decision type for '" BULK_DECISION_TYPE "'\n");
  }

  return result;
}

int queryBulkArguments(long bulkId, MonkState* state) {
  int result = 0;

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

      if ((!setLeftAndRight(state)) || (!queryDecisionType(state))) {
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
  if (!fo_dbManager_begin(state->dbManager))
    return;

  PGresult* clearingDecisionIds = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "saveBulkResult:decision",
      "INSERT INTO license_decision_events(uploadtree_fk, pfile_fk, user_fk, type_fk, rf_fk, is_removed)"
      " SELECT uploadtree_pk, $1, $2, $3, $4, $5"
      " FROM uploadtree, license_decision_types"
      " WHERE upload_fk = $6 AND pfile_fk = $1 AND lft BETWEEN $7 AND $8"
      "RETURNING license_decision_events_pk",
      long, int, int, long, int, int, long, long
    ),
    file->id,
    state->bulkArguments->userId,
    state->bulkArguments->decisionType,
    license->refId,
    state->bulkArguments->removing ? 1 : 0,
    state->bulkArguments->uploadId,
    state->bulkArguments->uploadTreeLeft,
    state->bulkArguments->uploadTreeRight
  );

  if (clearingDecisionIds) {
    for (int i=0; i<PQntuples(clearingDecisionIds);i++) {
      long clearingId = atol(PQgetvalue(clearingDecisionIds,i,0));

      PGresult* highlightResult = fo_dbManager_ExecPrepared(
        fo_dbManager_PrepareStamement(
          state->dbManager,
          "saveBulkResult:highlight",
          "INSERT INTO highlight_bulk(license_decision_events_fk, lrb_fk, start, len) VALUES($1,$2,$3,$4)",
          long, long, size_t, size_t
        ),
        clearingId,
        state->bulkArguments->bulkId,
        matchInfo->text.start,
        matchInfo->text.length
      );

      /* ignore errors */
      if (highlightResult)
        PQclear(highlightResult);
    }
    PQclear(clearingDecisionIds);
  }

  fo_dbManager_commit(state->dbManager);
}