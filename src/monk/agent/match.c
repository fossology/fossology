/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include "match.h"

#include "monk.h"
#include "extended.h"
#include "string_operations.h"
#include "file_operations.h"
#include "license.h"
#include "database.h"
#include "highlight.h"
#include "diff.h"
#include "math.h"
#include "_squareVisitor.h"

/*
 * @param matches: array of Match* where to save found matches
 */
void appendMatchesBetween(File* file, License* license, GArray* matches,
                          int maxAllowedDiff, int minTrailingMatches) {
  size_t matchStart;
  if (findMatchFull(file->tokens, license->tokens, &matchStart)) {

    Match* match = malloc(sizeof(Match));
    match->type = MATCH_TYPE_FULL;
    match->license = license;
    match->ptr.full = malloc(sizeof(DiffPoint));
    *(match->ptr.full) = (DiffPoint){.start = matchStart, .length = license->tokens->len};
    g_array_append_val(matches, match);
  } else {
    findDiffMatches(file, license, matches, maxAllowedDiff, minTrailingMatches);
  }
}

inline GArray* findAllMatchesBetween(File* file, GArray* licenses,
                                     int maxAllowedDiff, int minTrailingMatches) {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  for (unsigned int i = 0; i < licenses->len; i++) {
    License* license = &g_array_index(licenses, License, i);

    appendMatchesBetween(file, license, matches, maxAllowedDiff, minTrailingMatches);
  }

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  return filteredMatches;
}

char* getFileName(MonkState* state, long pFileId) {
  char* pFile = queryPFileForFileId(state->dbManager, pFileId);

  if (!pFile) {
    printf("file not found for pFileId=%ld\n", pFileId);
    return NULL;
  }

  char* pFileName = fo_RepMkPath("files", pFile);

  if (!pFileName)
    printf("file '%s' not found\n", pFile);

  free(pFile);

  return pFileName;
}

void match_array_free(GArray* matches) {
#ifndef GLIB_VERSION_2_32
    for (unsigned int i=0; i< matches->len; ++i) {
      Match* tmp = g_array_index(matches, Match*, i);
      match_free(tmp);
    }
#else
    g_array_set_clear_func(matches, match_destroyNotify);
#endif
    g_array_free(matches, TRUE);
}

void matchFileWithLicenses(MonkState* state, File* file, GArray* licenses){
  GArray* matches = findAllMatchesBetween(file, licenses,
                                          MAX_ALLOWED_DIFF_LENGTH, MIN_TRAILING_MATCHES);
  processMatches(state, file, matches);

  // we are done: free memory
  match_array_free(matches);
}

void matchPFileWithLicenses(MonkState* state, long pFileId, GArray* licenses) {
  File file;
  file.id = pFileId;

  file.fileName = getFileName(state, pFileId);
  if (file.fileName != NULL) {
    file.tokens = readTokensFromFile(file.fileName, DELIMITERS);

    matchFileWithLicenses(state, &file, licenses);

    g_array_free(file.tokens, TRUE);
    free(file.fileName);
  }
}

char* formatMatchArray(GArray * matchInfo){
  char* result;

  StringBuilder* stringBuilder = stringBuilder_new();

  size_t len = matchInfo->len;
  for (size_t i = 0; i < len; i++) {
    DiffMatchInfo* current = &g_array_index(matchInfo, DiffMatchInfo, i);

    if(current->text.length > 0)
      stringBuilder_printf(stringBuilder,
                           "t[%zu+%zu] %s ",
                           current->text.start, current->text.length, current->diffType);
    else
      stringBuilder_printf(stringBuilder,
                           "t[%zu] %s ",
                           current->text.start, current->diffType);

    if(current->search.length > 0)
      stringBuilder_printf(stringBuilder,
                           "s[%zu+%zu]",
                           current->search.start, current->search.length);
    else
      stringBuilder_printf(stringBuilder,
                           "s[%zu]",
                           current->search.start);

    if (i < len-1) {
      stringBuilder_printf(stringBuilder, ", ");
    }
  }

  result = stringBuilder_build(stringBuilder);
  stringBuilder_free(stringBuilder);

  return result;
}

inline unsigned short match_rank(Match* match){
  if (match->type == MATCH_TYPE_FULL) {
    return 100;
  } else {
    DiffResult* diffResult = match->ptr.diff;
    License* license = match->license;
    unsigned int licenseLength = license->tokens->len;
    unsigned int numberOfMatches = diffResult->matched;
    unsigned int numberOfAdditions = diffResult->added;

    // calculate result percentage as jaccard index
    double rank = (100.0 * numberOfMatches) /  (licenseLength + numberOfAdditions);
    int result = floor( rank );

    result = MIN(result, 99);
    result = MAX(result, 1);

    diffResult->rank = rank;
    diffResult->percentual = result;

    return (unsigned short) result;
  }
}

inline size_t match_getStart(const Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return match->ptr.full->start;
  } else {

    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint firstDiff = g_array_index(matchedInfo, DiffMatchInfo, 0).text;

    return firstDiff.start;
  }
}

inline size_t match_getEnd(const Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return match->ptr.full->length + match->ptr.full->start;
  } else {

    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint lastDiff = g_array_index(matchedInfo, DiffMatchInfo, matchedInfo->len-1).text;

    return lastDiff.start + lastDiff.length;
  }
}

gint compareMatchIncuded (gconstpointer  a, gconstpointer  b) {
  const Match* matchA = *(Match**)a;
  const Match* matchB = *(Match**)b;

  size_t matchAStart = match_getStart(matchA);
  size_t matchBStart = match_getStart(matchB);

  if (matchAStart > matchBStart)
    return 1;
  if (matchAStart < matchBStart)
    return -1;

  size_t matchAEnd = match_getEnd(matchA);
  size_t matchBEnd = match_getEnd(matchB);

  if (matchAEnd > matchBEnd)
    return -1;
  if (matchAEnd < matchBEnd)
    return 1;

  return 0;
}

// make sure to call it after a match_rank or the result will be junk
inline double match_getRank(const Match* match){
  if (match->type == MATCH_TYPE_FULL)
    return 100.0;
  else
    return match->ptr.diff->rank;
 }

gint compareMatchByRank (gconstpointer  a, gconstpointer  b) {
  const Match* matchA = *(Match**)a;
  const Match* matchB = *(Match**)b;

  double matchARank = match_getRank(matchA);
  double matchBRank = match_getRank(matchB);

  if (matchARank > matchBRank)
    return 1;
  if (matchARank < matchBRank)
    return -1;

  return 0;
}

/* divide an array of Match* in groups
 * each first element of a group contains all the successive elements in the same group
 *
 * a match m1 is contained in another m2 if the set [m1.start, m1.end] is contained in [m2.start, m2.end]
 * in this case compareMatchIncuded(&m1, &m2) == 1
 *
 * input: [GArray of Match*]
 * output: [GArray of GArray of Match*]
 */
inline GArray* groupOverlapping(GArray* matches) {
  g_array_sort(matches, compareMatchIncuded);

  GArray* result = g_array_new(TRUE, FALSE, sizeof(GArray*));

  if (matches->len == 0)
    return result;

  Match* firstMatch = g_array_index(matches, Match*, 0);
  size_t currentGroupEnd = match_getEnd(firstMatch);

  GArray* currentGroup = g_array_new(TRUE, FALSE, sizeof(GArray*));

  g_array_append_val(currentGroup, firstMatch);

  for (guint i = 1; i < matches->len; ++i) {
    Match* currentMatch = g_array_index(matches, Match*, i);
    size_t currentMatchEnd = match_getEnd(currentMatch);

    if (currentMatchEnd > currentGroupEnd) {
      g_array_append_val(result, currentGroup);
      currentGroup = g_array_new(TRUE, FALSE, sizeof(GArray*));
      currentGroupEnd = currentMatchEnd;
    }
    g_array_append_val(currentGroup, currentMatch);
  }
  g_array_append_val(result, currentGroup);

  return result;
}

// match must not be empty
Match* greatestMatchInGroup(GArray* matches, GCompareFunc compare){
  Match* result = g_array_index(matches, Match*, 0);

  for (guint j = 0; j < matches->len; j++) {
    Match* match = g_array_index(matches, Match*, j);
    if ((*compare)(&match, &result) == 1) {
      result = match;
    }
  }

  return result;
}

// destructively filter overlapping licenses to contain only best match
// discarded matches and the array are freed
GArray* filterNonOverlappingMatches(GArray* matches) {
  GArray* result = g_array_new(TRUE, FALSE, sizeof(Match*));

  GArray* overlappingGroups = groupOverlapping(matches);

  for (guint i = 0; i < overlappingGroups->len; i++) {
    GArray* currentGroup = g_array_index(overlappingGroups, GArray*, i);

    Match* biggestInGroup = g_array_index(currentGroup, Match*, 0);
    Match* bestInGroup = greatestMatchInGroup(currentGroup, compareMatchByRank);

    if (bestInGroup != biggestInGroup) {
      // the biggest match in this group was not the one with the best match
      // let's split the group and find the best matches by recursively calling ourselves
      match_free(biggestInGroup);
      g_array_remove_index_fast(currentGroup, 0);

      GArray* subGroupFiltered = filterNonOverlappingMatches(currentGroup);

      g_array_append_vals(result, subGroupFiltered->data, subGroupFiltered->len);
      g_array_free(subGroupFiltered, TRUE);
    } else {
      g_array_append_val(result, bestInGroup);

      // keep this (exclude from freeing)
      g_array_remove_index_fast(currentGroup, 0);
      // and free memory used by discarded matches
      match_array_free(currentGroup);
    }
  }

  g_array_free(matches, TRUE);
  g_array_free(overlappingGroups, TRUE);

  return result;
}

inline void processFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  if (state->scanMode == MODE_SCHEDULER) {
    long licenseFileId = saveToDb(state->dbManager, state->agentId,
                                  license->refId, file->id, 100);
    if (licenseFileId > 0)
      saveDiffHighlightToDb(state->dbManager, matchInfo, licenseFileId);
#ifdef DEBUG
    printf("found full match between \"%s\" (pFile=%ld) and \"%s\" (rf_pk=%ld)\n",
             getFileNameForFileId(state->dbManager, file->id), file->id, license->shortname, license->refId);
#endif //DEBUG
  } else {
    onFullMatch(file, license, matchInfo);
  }
}

inline void processDiffMatch(MonkState* state, File* file, License* license, DiffResult* diffResult) {
  unsigned short matchPercent = diffResult->percentual;
  convertToAbsolutePositions(diffResult->matchedInfo, file->tokens, license->tokens);
  if (state->scanMode == MODE_SCHEDULER) {
    long licenseFileId = saveToDb(state->dbManager, state->agentId, license->refId, file->id, matchPercent);
    if (licenseFileId > 0)
      saveDiffHighlightsToDb(state->dbManager, diffResult->matchedInfo, licenseFileId);
#ifdef DEBUG
    printf("found diff match between \"%s\" (pFile=%ld) and \"%s\" (rf_pk=%ld); ",
           getFileNameForFileId(state->dbManager, file->id), file->id, license->shortname, license->refId);
    printf("%u%%; ", diffResult->percentual);

    char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);
    printf("diffs: {%s}\n", formattedMatchArray);
    free(formattedMatchArray);
#endif //DEBUG
  } else {
    onDiffMatch(file, license, diffResult, matchPercent);
  }
}

inline void processMatch(MonkState* state, File* file, Match* match) {
  License* license = match->license;
  if (match->type == MATCH_TYPE_DIFF) {
    DiffResult* diffResult = match->ptr.diff;
    processDiffMatch(state, file, license, diffResult);
  } else {
    DiffMatchInfo matchInfo;
    matchInfo.text = getFullHighlightFor(file->tokens, match->ptr.full->start, match->ptr.full->length );
    matchInfo.search = getFullHighlightFor(license->tokens, 0, license->tokens->len );
    matchInfo.diffType = FULL_MATCH;

    processFullMatch(state, file, license, &matchInfo);
  }
}

inline void processMatches(MonkState* state, File* file, GArray* matches) {
  /* check if we have other results for this file.
   * We do it now to minimize races with a concurrent scan of this file:
   * the same file could be inside more than upload
   */
  if ((state->scanMode == MODE_SCHEDULER) &&
       hasAlreadyResultsFor(state->dbManager, state->agentId, file->id))
    return;

  if ((state->scanMode != MODE_SCHEDULER) && (state->verbosity >= 1) && (matches->len == 0)) {
    onNoMatch(file);
    return;
  }

  for (size_t matchIndex = 0; matchIndex < matches->len; matchIndex++)  {
    Match* match = g_array_index(matches, Match*, matchIndex);
    processMatch(state, file, match);
  }
}

void findDiffMatches(File* file, License* license, GArray* matches,
                     int maxAllowedDiff, int minTrailingMatches) {
  size_t textStartPosition = 0;
  size_t textLength = file->tokens->len;
  DiffResult* diffResult = findMatchAsDiffs(file->tokens, license->tokens,
                                           &textStartPosition,
                                           maxAllowedDiff,
                                           minTrailingMatches);

  while (textStartPosition < textLength) {
    if (diffResult) {
      DiffPoint firstMatch = g_array_index(diffResult->matchedInfo, DiffMatchInfo, 0).text;
      textStartPosition = firstMatch.start + firstMatch.length + 1;

      Match* diffMatch = malloc(sizeof(Match));
      diffMatch->type = MATCH_TYPE_DIFF;
      diffMatch->ptr.diff = diffResult;
      diffMatch->license = license;

      if (match_rank(diffMatch) > MIN_ALLOWED_RANK)
        g_array_append_val(matches, diffMatch);
      else
        match_free(diffMatch);
    }

    diffResult = findMatchAsDiffs(file->tokens, license->tokens,
                                 &textStartPosition,
                                 maxAllowedDiff,
                                 minTrailingMatches);
  }
}

#ifdef GLIB_VERSION_2_32
void match_destroyNotify(gpointer matchP){
  match_free(*((Match**) matchP));
}
#endif

void match_free(Match* match){
  if (match->type == MATCH_TYPE_DIFF)
    diffResult_free(match->ptr.diff);
  else
    free(match->ptr.full);
  free(match);
}
