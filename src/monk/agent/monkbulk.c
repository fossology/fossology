/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include <libfossology.h>

#include "monkbulk.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "common.h"
#include "monk.h"

int bulk_onAllMatches(MonkState* state, File* file, GArray* matches);

MatchCallbacks bulkCallbacks = {.onAll = bulk_onAllMatches};

int setLeftAndRight(MonkState* state) {
  BulkArguments* bulkArguments = state->ptr;

  char* tableName = getUploadTreeTableName(state->dbManager, bulkArguments->uploadId);

  if (!tableName)
    return 0;

  char* sql = g_strdup_printf( "SELECT lft, rgt FROM %s WHERE uploadtree_pk = $1", tableName);
  char* stmt = g_strdup_printf("setLeftAndRight.%s", tableName);

  if ((!sql) || (!stmt))
    return 0;

  PGresult* leftAndRightResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      stmt,
      sql,
      long
    ),
    bulkArguments->uploadTreeId
  );

  g_free(stmt);
  g_free(sql);

  int result = 0;

  if (leftAndRightResult) {
    if (PQntuples(leftAndRightResult)==1) {
      int i = 0;
      bulkArguments->uploadTreeLeft = atol(PQgetvalue(leftAndRightResult, 0, i++));
      bulkArguments->uploadTreeRight = atol(PQgetvalue(leftAndRightResult, 0, i));

      result = 1;
    }
    PQclear(leftAndRightResult);
  }
  return result;
}

void bulkArguments_contents_free(BulkArguments* bulkArguments);

int queryBulkArguments(MonkState* state, long bulkId) {
  int result = 0;

  PGresult* bulkArgumentsResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "queryBulkArguments",
      "SELECT ut.upload_fk, ut.uploadtree_pk, lrb.user_fk, lrb.group_fk, lrb.rf_fk, lrb.rf_text, lrb.removing "
      "FROM license_ref_bulk lrb INNER JOIN uploadtree ut "
      "ON ut.uploadtree_pk = lrb.uploadtree_fk "
      "WHERE lrb_pk = $1",
      long
    ),
    bulkId
  );

  if (bulkArgumentsResult) {
    if (PQntuples(bulkArgumentsResult)==1) {
      BulkArguments* bulkArguments = malloc(sizeof(BulkArguments));

      int i = 0;
      bulkArguments->uploadId = atoi(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->uploadTreeId = atol(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->userId = atoi(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->groupId = atoi(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->licenseId = atol(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->refText = g_strdup(PQgetvalue(bulkArgumentsResult, 0, i++));
      bulkArguments->removing = (strcmp(PQgetvalue(bulkArgumentsResult, 0, i), "t") == 0);

      bulkArguments->bulkId = bulkId;
      bulkArguments->jobId = fo_scheduler_jobId();

      state->ptr = bulkArguments;

      if (!setLeftAndRight(state)) {
        printf("FATAL: could not retrieve left and right for bulk id=%ld\n", bulkId);
        bulkArguments_contents_free(state->ptr);
      } else {
        result = 1;
      }
    } else {
      printf("FATAL: could not retrieve arguments for bulk scan with id=%ld\n", bulkId);
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
  BulkArguments* bulkArguments = state->ptr;

  License license = (License){
    .refId = bulkArguments->licenseId,
  };
  license.tokens = tokenize(bulkArguments->refText, DELIMITERS);

  GArray* licenseArray = g_array_new(FALSE, FALSE, sizeof (License));
  g_array_append_val(licenseArray, license);

  Licenses* licenses = buildLicenseIndexes(licenseArray, MIN_ADJACENT_MATCHES, 0);

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
          if (haveError)
            continue;

          long fileId = atol(PQgetvalue(filesResult, i, 0));

          if (matchPFileWithLicenses(threadLocalState, fileId, licenses, &bulkCallbacks)) {
            fo_scheduler_heart(1);
          } else {
            fo_scheduler_heart(0);
            haveError = 1;
          }
        }
        fo_dbManager_finish(threadLocalState->dbManager);
      } else {
        haveError = 1;
      }
    }
    PQclear(filesResult);
  }

  licenses_free(licenses);

  return !haveError;
}

int main(int argc, char** argv) {
  MonkState stateStore;
  MonkState* state = &stateStore;

  fo_scheduler_connect_dbMan(&argc, argv, &(state->dbManager));

  queryAgentId(state, AGENT_BULK_NAME, AGENT_BULK_DESC);

  state->scanMode = MODE_BULK;

  while (fo_scheduler_next() != NULL) {
    const char* schedulerCurrent = fo_scheduler_current();

    long bulkId = atol(schedulerCurrent);

    if (bulkId == 0) continue;

    if (!queryBulkArguments(state, bulkId)) {
      bail(state, 1);
    }

    BulkArguments* bulkArguments = state->ptr;

    int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
      0, bulkArguments->uploadId, state->agentId, AGENT_BULK_ARS, NULL, 0);

    if (arsId<=0)
      bail(state, 2);

    if (!bulk_identification(state))
      bail(state, 3);

    fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
      arsId, bulkArguments->uploadId, state->agentId, AGENT_BULK_ARS, NULL, 1);

    bulkArguments_contents_free(bulkArguments);
    fo_scheduler_heart(0);
  }

  scheduler_disconnect(state, 0);
  return 0;
}

int bulk_onAllMatches(MonkState* state, File* file, GArray* matches) {
  int haveAFullMatch = 0;
  for (guint j=0; j<matches->len; j++) {
    Match* match = match_array_get(matches, j);

    if (match->type == MATCH_TYPE_FULL) {
      haveAFullMatch = 1;
      break;
    }
  }

  if (!haveAFullMatch)
    return 1;

  BulkArguments* bulkArguments = state->ptr;

  long licenseId = bulkArguments->licenseId;

  if (!fo_dbManager_begin(state->dbManager))
    return 0;

  PGresult* licenseDecisionIds = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "saveBulkResult:decision",
      "INSERT INTO clearing_event(uploadtree_fk, user_fk, group_fk, job_fk, type_fk, rf_fk, removed)"
      " SELECT uploadtree_pk, $2, $3, $4, $5, $6, $7"
      " FROM uploadtree"
      " WHERE upload_fk = $8 AND pfile_fk = $1 AND lft BETWEEN $9 AND $10"
      "RETURNING clearing_event_pk",
      long, int, int, int, int, long, int,
      int, long, long
    ),
    file->id,

    bulkArguments->userId,
    bulkArguments->groupId,
    bulkArguments->jobId,
    BULK_DECISION_TYPE,
    licenseId,
    bulkArguments->removing ? 1 : 0,

    bulkArguments->uploadId,
    bulkArguments->uploadTreeLeft,
    bulkArguments->uploadTreeRight
  );

  if (licenseDecisionIds) {
    for (int i=0; i<PQntuples(licenseDecisionIds);i++) {
      long licenseDecisionEventId = atol(PQgetvalue(licenseDecisionIds,i,0));

      for (guint j=0; j<matches->len; j++) {
        Match* match = match_array_get(matches, j);

        if (match->type != MATCH_TYPE_FULL)
          continue;

        DiffPoint* highlightTokens = match->ptr.full;
        DiffPoint highlight = getFullHighlightFor(file->tokens, highlightTokens->start, highlightTokens->length);

        PGresult* highlightResult = fo_dbManager_ExecPrepared(
          fo_dbManager_PrepareStamement(
            state->dbManager,
            "saveBulkResult:highlight",
            "INSERT INTO highlight_bulk(clearing_event_fk, lrb_fk, start, len) VALUES($1,$2,$3,$4)",
            long, long, size_t, size_t
          ),
          licenseDecisionEventId,
          bulkArguments->bulkId,
          highlight.start,
          highlight.length
        );

        if (highlightResult) {
          PQclear(highlightResult);
        } else {
          fo_dbManager_rollback(state->dbManager);
          return 0;
        }
      }
    }
    PQclear(licenseDecisionIds);
  } else {
    fo_dbManager_rollback(state->dbManager);
    return 0;
  }

  return fo_dbManager_commit(state->dbManager);
}
