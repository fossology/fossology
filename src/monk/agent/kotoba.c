/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>

#include "libfossology.h"

#include "kotoba.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "common.h"
#include "monk.h"
#include "string_operations.h"
#include "highlight.h"

// Global hash table to map License refId (cpId) back to Phrase*
static GHashTable* phraseByCpId = NULL;

int phrase_onAllMatches(MonkState* state, const File* file, const GArray* matches);

 MatchCallbacks phraseCallbacks = {.onAll = phrase_onAllMatches};

/* Parse comma-separated delimiter string; essential delimiters always included.
 * Caller must free result with g_free. */
char* parseDelimiters(const char* input) {
  if (input == NULL || strlen(input) == 0) {
    return g_strdup(" ,\t\n\r\f");
  }

  GString* result = g_string_new(" ,\t\n\r\f");

  gchar** tokens = g_strsplit(input, ",", -1);

  for (int i = 0; tokens[i] != NULL; i++) {
    gchar* token = g_strstrip(tokens[i]);

    if (strlen(token) > 0) {
      for (int j = 0; token[j] != '\0'; j++) {
        char c = token[j];
        if (strchr(result->str, c) == NULL) {
          g_string_append_c(result, c);
        }
      }
    }
  }

  g_strfreev(tokens);

  /* Ensure essential delimiters are always present. */
  const char* essentialDelims = " ,\t\n\r\f";
  for (const char* p = essentialDelims; *p != '\0'; p++) {
    if (strchr(result->str, *p) == NULL)
      g_string_append_c(result, *p);
  }

  return g_string_free(result, FALSE);
}

/* Build a Licenses* index from phrases for matching. Caller must free. */
Licenses* buildLicenseIndexFromPhrases(GArray* phrases, const char* delimiters) {
  GArray* licenseArray = g_array_new(FALSE, FALSE, sizeof(License));

  // Initialize global phrase mapping
  if (phraseByCpId) {
    g_hash_table_destroy(phraseByCpId);
  }
  phraseByCpId = g_hash_table_new(g_direct_hash, g_direct_equal);

  for (guint i = 0; i < phrases->len; i++) {
    Phrase* phrase = g_array_index(phrases, Phrase*, i);

    // Skip phrases with no mapped licenses
    if (!phrase->licenseMappings || phrase->licenseMappings->len == 0) {
      continue;
    }

    License license = {0};
    license.refId = phrase->cpId;  // Use cpId as refId for lookup
    license.shortname = g_strdup_printf("phrase_%ld", phrase->cpId);
    license.tokens = tokenize(phrase->text, delimiters);

    g_array_append_val(licenseArray, license);

    // Store phrase mapping for callback lookup
    g_hash_table_insert(phraseByCpId, GSIZE_TO_POINTER(phrase->cpId), phrase);
  }

  return buildLicenseIndexes(licenseArray, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);
}

/* Save highlights for full matches to highlight_kotoba. Returns 1 on success. */
int saveKotobaHighlights(MonkState* state, const File* file, const GArray* matches,
                        long clearingEventId, long phraseId) {
  for (guint j = 0; j < matches->len; j++) {
    Match* match = match_array_index(matches, j);
    
    // Only save highlights for full matches (exact phrase matches)
    if (match->type != MATCH_TYPE_FULL)
      continue;
    
    // Calculate byte positions from token indices
    DiffPoint* highlightTokens = match->ptr.full;
    DiffPoint highlight = getFullHighlightFor(file->tokens, 
                                             highlightTokens->start, 
                                             highlightTokens->length);
    
    // Insert into highlight_kotoba table
    PGresult* highlightResult = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        state->dbManager,
        "saveKotobaHighlight",
        "INSERT INTO highlight_kotoba(clearing_event_fk, cp_fk, start, len) VALUES($1,$2,$3,$4)",
        long, long, size_t, size_t
      ),
      clearingEventId,
      phraseId,
      highlight.start,
      highlight.length
    );
    
    if (!highlightResult) {
      return 0;
    }
    
    PQclear(highlightResult);
  }
  
  return 1;
}

/* Callback for phrase matches; writes clearing decisions. */
int phrase_onAllMatches(MonkState* state, const File* file, const GArray* matches) {
  int haveAFullMatch = 0;
  for (guint j = 0; j < matches->len; j++) {
    Match* match = match_array_index(matches, j);
    if (match->type == MATCH_TYPE_FULL) {
      haveAFullMatch = 1;
      break;
    }
  }

  if (!haveAFullMatch)
    return 1;

  PhraseModeArgs* args = (PhraseModeArgs*)state->ptr;

  if (!fo_dbManager_begin(state->dbManager))
    return 0;

  for (guint j = 0; j < matches->len; j++) {
    Match* match = match_array_index(matches, j);
    if (match->type != MATCH_TYPE_FULL)
      continue;

    Phrase* phrase = g_hash_table_lookup(phraseByCpId, GSIZE_TO_POINTER(match->license->refId));
    if (!phrase)
      continue;

    for (guint k = 0; k < phrase->licenseMappings->len; k++) {
      LicenseMapping mapping = g_array_index(phrase->licenseMappings, LicenseMapping, k);

      PGresult* result = fo_dbManager_ExecPrepared(
        fo_dbManager_PrepareStamement(
          state->dbManager,
          phrase->stmtName,
          args->insertSql,
          long, int, int, int, int, long, int, char*, char*, char*, int
        ),
        file->id,
        args->userId,
        args->groupId,
        args->jobId,
        BULK_DECISION_TYPE_KOTOBA,
        mapping.rfPk,
        mapping.removing ? 1 : 0,
        phrase->comments ? phrase->comments : "",
        phrase->text,
        phrase->acknowledgement ? phrase->acknowledgement : "",
        args->uploadId
      );

      if (!result) {
        fo_dbManager_rollback(state->dbManager);
        return 0;
      }

      long clearingEventId = -1;
      if (PQntuples(result) == 1)
        clearingEventId = atol(PQgetvalue(result, 0, 0));
      PQclear(result);

      if (clearingEventId <= 0)
        continue;

      if (k == 0) {
        if (!saveKotobaHighlights(state, file, matches, clearingEventId, phrase->cpId)) {
          fo_dbManager_rollback(state->dbManager);
          return 0;
        }
      }
    }
  }

  return fo_dbManager_commit(state->dbManager);
}

/* Process a single upload with phrase-mode scanning. */
int processUploadWithPhrases(MonkState* state, int uploadId) {
  int userId = fo_scheduler_userID();
  int groupId = fo_scheduler_groupID();
  int jobId = fo_scheduler_jobId();

  GArray* phrases = queryActiveCustomPhrases(state->dbManager);
  if (!phrases || phrases->len == 0) {
    if (phrases)
      phrases_free(phrases);
    return 1;
  }

  char* configDelimiters = NULL;
  PGresult* delimitersResult = fo_dbManager_Exec_printf(state->dbManager,
    "SELECT conf_value FROM sysconfig WHERE variablename='KotobaDelimiters'");
  if (delimitersResult && PQntuples(delimitersResult) > 0 && !PQgetisnull(delimitersResult, 0, 0))
    configDelimiters = g_strdup(PQgetvalue(delimitersResult, 0, 0));
  if (delimitersResult)
    PQclear(delimitersResult);

  char* delimiters = parseDelimiters(configDelimiters);
  g_free(configDelimiters);

  /* Cache upload tree table name and pre-build the INSERT SQL once. */
  char* uploadTreeTableName = getUploadTreeTableName(state->dbManager, uploadId);
  gchar* insertSql = g_strdup_printf(
    "INSERT INTO clearing_event(uploadtree_fk, user_fk, group_fk, job_fk, type_fk, rf_fk, removed, comment, reportinfo, acknowledgement) "
    "SELECT uploadtree_pk, $2, $3, $4, $5, $6, $7, $8, $9, $10 "
    "FROM %s WHERE upload_fk = $11 AND pfile_fk = $1 "
    "RETURNING clearing_event_pk",
    uploadTreeTableName);

  /* Pre-build per-phrase statement names (read-only in the parallel loop). */
  for (guint i = 0; i < phrases->len; i++) {
    Phrase* p = g_array_index(phrases, Phrase*, i);
    g_free(p->stmtName);
    p->stmtName = g_strdup_printf("phrase_decision.%s.%ld", uploadTreeTableName, p->cpId);
  }
  g_free(uploadTreeTableName);

  PhraseModeArgs args = {
    .uploadId = uploadId,
    .userId = userId,
    .groupId = groupId,
    .jobId = jobId,
    .phrases = phrases,
    .delimiters = delimiters,
    .uploadTreeTableName = NULL, /* not needed by callback; SQL is in insertSql */
    .insertSql = insertSql
  };

  state->ptr = &args;

  Licenses* licenses = buildLicenseIndexFromPhrases(phrases, args.delimiters);
  if (!licenses) {
    phrases_free(phrases);
    g_free(args.delimiters);
    g_free(args.insertSql);
    return 0;
  }

  PGresult* fileIdResult = queryFileIdsForUpload(state->dbManager, uploadId, false);
  if (!fileIdResult) {
    licenses_free(licenses);
    phrases_free(phrases);
    g_free(args.delimiters);
    g_free(args.insertSql);
    return 0;
  }

  if (PQntuples(fileIdResult) == 0) {
    PQclear(fileIdResult);
    licenses_free(licenses);
    phrases_free(phrases);
    g_free(args.delimiters);
    g_free(args.insertSql);
    fo_scheduler_heart(0);
    return 1;
  }

  int haveError = 0;
  int resultsCount = PQntuples(fileIdResult);

#ifdef MONK_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    MonkState threadLocalStateStore = *state;
    MonkState* threadLocalState = &threadLocalStateStore;
    threadLocalState->ptr = &args;

    threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
    if (threadLocalState->dbManager) {
#ifdef MONK_MULTI_THREAD
      #pragma omp for schedule(dynamic)
#endif
      for (int i = 0; i < resultsCount; i++) {
        if (haveError)
          continue;

        long fileId = atol(PQgetvalue(fileIdResult, i, 0));

        if (matchPFileWithLicenses(threadLocalState, fileId, licenses,
                                   &phraseCallbacks, args.delimiters)) {
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

  PQclear(fileIdResult);
  licenses_free(licenses);
  phrases_free(phrases);
  g_free(args.delimiters);
  g_free(args.insertSql);

  return !haveError;
}

/* Main function — phrase-driven bulk scanning. */
int main(int argc, char** argv) {
  MonkState stateStore;
  MonkState* state = &stateStore;

  fo_scheduler_connect_dbMan(&argc, argv, &(state->dbManager));

  queryAgentId(state, AGENT_NAME, AGENT_DESC);

  state->scanMode = MODE_BULK;

  while (fo_scheduler_next() != NULL) {
    const char* schedulerCurrent = fo_scheduler_current();

    int uploadId = atoi(schedulerCurrent);

    if (uploadId == 0) continue;

    int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                           0, uploadId, state->agentId, AGENT_ARS, NULL, 0);

    if (arsId <= 0)
      bail(state, 2);

    if (!processUploadWithPhrases(state, uploadId))
      bail(state, 3);

    fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                arsId, uploadId, state->agentId, AGENT_ARS, NULL, 1);

    fo_scheduler_heart(0);
  }

  // Clean up global hash table
  if (phraseByCpId) {
    g_hash_table_destroy(phraseByCpId);
    phraseByCpId = NULL;
  }

  scheduler_disconnect(state, 0);
  return 0;
}
