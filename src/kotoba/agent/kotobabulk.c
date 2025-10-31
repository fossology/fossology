/*
Author: Harshit Gandhi
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>

#include "libfossology.h"

#include "kotobabulk.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "common.h"
#include "kotoba.h"
#include "string_operations.h"

// Global hash table to map License refId (cpId) back to Phrase*
static GHashTable* phraseByCpId = NULL;

int phrase_onAllMatches(KotobaState* state, const File* file, const GArray* matches);

MatchCallbacks phraseCallbacks = {.onAll = phrase_onAllMatches};

/**
 * \brief Build a Licenses* index from phrases for matching
 * \param phrases GArray* of Phrase* structures
 * \param delimiters Token delimiters to use
 * \return Licenses* structure for matching (caller must free)
 */
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
    if (!phrase->licenseIds || phrase->licenseIds->len == 0) {
      continue;
    }
    
    License license = {0};
    license.refId = phrase->cpId;  // Use cpId as refId for lookup
    license.shortname = g_strdup_printf("phrase_%ld", phrase->cpId);
    license.tokens = tokenize(phrase->text, delimiters);
    
    g_array_append_val(licenseArray, license);
    
    // Store phrase mapping for callback lookup
    g_hash_table_insert(phraseByCpId, GINT_TO_POINTER((int)phrase->cpId), phrase);
  }
  
  return buildLicenseIndexes(licenseArray, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);
}

/**
 * \brief Callback for phrase matches - writes clearing decisions
 */
int phrase_onAllMatches(KotobaState* state, const File* file, const GArray* matches) {
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

  // Process each full match
  for (guint j = 0; j < matches->len; j++) {
    Match* match = match_array_index(matches, j);
    if (match->type != MATCH_TYPE_FULL)
      continue;

    // Get the phrase from the license refId (cpId)
    Phrase* phrase = g_hash_table_lookup(phraseByCpId, GINT_TO_POINTER((int)match->license->refId));
    if (!phrase) {
      continue;
    }

    // Create clearing decisions for each mapped license
    for (guint k = 0; k < phrase->licenseIds->len; k++) {
      long rfPk = g_array_index(phrase->licenseIds, long, k);
      
      char* uploadTreeTableName = getUploadTreeTableName(state->dbManager, args->uploadId);
      if (!uploadTreeTableName) {
        fo_dbManager_rollback(state->dbManager);
        return 0;
      }
      
      gchar* insertSql = g_strdup_printf(
        "INSERT INTO clearing_event(uploadtree_fk, user_fk, group_fk, job_fk, type_fk, rf_fk, removed, comment, reportinfo, acknowledgement) "
        "SELECT uploadtree_pk, $2, $3, $4, $5, $6, $7, $8, $9, $10 "
        "FROM %s WHERE upload_fk = $11 AND pfile_fk = $1 "
        "RETURNING clearing_event_pk",
        uploadTreeTableName
      );
      
      gchar* stmt = g_strdup_printf("phrase_decision.%s.%ld", uploadTreeTableName, phrase->cpId);
      
      PGresult* result = fo_dbManager_ExecPrepared(
        fo_dbManager_PrepareStamement(
          state->dbManager,
          stmt,
          insertSql,
          long, int, int, int, int, long, int, char*, char*, char*, int
        ),
        file->id,
        args->userId,
        args->groupId,
        args->jobId,
        BULK_DECISION_TYPE_KOTOBA,
        rfPk,
        0, // removed = false
        phrase->comments,
        phrase->text, // reportinfo
        phrase->acknowledgement,
        args->uploadId
      );
      
      g_free(uploadTreeTableName);
      g_free(insertSql);
      g_free(stmt);
      
      if (!result) {
        fo_dbManager_rollback(state->dbManager);
        return 0;
      }
      
      PQclear(result);
    }
  }

  return fo_dbManager_commit(state->dbManager);
}

/**
 * \brief Process a single upload with phrase-mode scanning
 */
int processUploadWithPhrases(KotobaState* state, int uploadId) {
  // Get scheduler context
  int userId = fo_scheduler_userID();
  int groupId = fo_scheduler_groupID();
  int jobId = fo_scheduler_jobId();
  
  // Load active phrases and their license mappings
  GArray* phrases = queryActiveCustomPhrases(state->dbManager);
  if (!phrases || phrases->len == 0) {
    // No active phrases - complete successfully with no work
    if (phrases) {
      phrases_free(phrases);
    }
    return 1;
  }
  
  // Set up phrase mode arguments
  PhraseModeArgs args = {
    .uploadId = uploadId,
    .userId = userId,
    .groupId = groupId,
    .jobId = jobId,
    .phrases = phrases,
    .delimiters = g_strdup(DELIMITERS)
  };
  
  state->ptr = &args;
  
  // Build license index from phrases
  Licenses* licenses = buildLicenseIndexFromPhrases(phrases, args.delimiters);
  if (!licenses) {
    phrases_free(phrases);
    g_free(args.delimiters);
    return 0;
  }
  
  // Get all file IDs for the entire upload
  PGresult* fileIdResult = queryFileIdsForUpload(state->dbManager, uploadId, false);
  if (!fileIdResult) {
    licenses_free(licenses);
    phrases_free(phrases);
    g_free(args.delimiters);
    return 0;
  }
  
  if (PQntuples(fileIdResult) == 0) {
    PQclear(fileIdResult);
    licenses_free(licenses);
    phrases_free(phrases);
    g_free(args.delimiters);
    fo_scheduler_heart(0);
    return 1;
  }
  
  // Process files in parallel
  int haveError = 0;
  int resultsCount = PQntuples(fileIdResult);
  
#ifdef KOTOBA_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    KotobaState threadLocalStateStore = *state;
    KotobaState* threadLocalState = &threadLocalStateStore;
    threadLocalState->ptr = &args;  // Ensure each thread has the args
    
    threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
    if (threadLocalState->dbManager) {
#ifdef KOTOBA_MULTI_THREAD
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
  
  return !haveError;
}

/**
 * \brief Main function - phrase-driven bulk scanning
 */
int main(int argc, char** argv) {
  KotobaState stateStore;
  KotobaState* state = &stateStore;

  fo_scheduler_connect_dbMan(&argc, argv, &(state->dbManager));

  queryAgentId(state, AGENT_BULK_NAME, AGENT_BULK_DESC);

  state->scanMode = MODE_BULK;

  while (fo_scheduler_next() != NULL) {
    const char* schedulerCurrent = fo_scheduler_current();

    int uploadId = atoi(schedulerCurrent);

    if (uploadId == 0) continue;

    int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                           0, uploadId, state->agentId, AGENT_BULK_ARS, NULL, 0);

    if (arsId <= 0)
      bail(state, 2);

    if (!processUploadWithPhrases(state, uploadId))
      bail(state, 3);

    fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                arsId, uploadId, state->agentId, AGENT_BULK_ARS, NULL, 1);

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
