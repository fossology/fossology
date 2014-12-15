/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "match.h"

#include "license.h"
#include "math.h"
#include "monk.h"
#include "diff.h"
#include "file_operations.h"

GArray* findAllMatchesBetween(File* file, Licenses* licenses,
                                     unsigned maxAllowedDiff, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  GArray* textTokens = file->tokens;
  guint textLength = textTokens->len;

  for (guint tPos=0; tPos<textLength; tPos++) {
    for (guint sPos = 0; sPos <= maxLeadingDiff; sPos++) {
      const GArray* availableLicenses = getLicenseArrayFor(licenses, sPos, file->tokens, tPos);

      if (!availableLicenses)
      {
        /* we hope to get here very often */
        continue;
      }

      for (guint i = 0; i < availableLicenses->len; i++) {
        License* license = &g_array_index(availableLicenses, License, i);
        findDiffMatches(file, license, tPos, sPos, matches, maxAllowedDiff, minAdjacentMatches);
      }
    }
  }

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  return filteredMatches;
}

void match_array_free(GArray* matches) {
#if GLIB_CHECK_VERSION(2,32,0)
  g_array_set_clear_func(matches, match_destroyNotify);
#else
  for (unsigned int i=0; i< matches->len; ++i) {
    Match* tmp = g_array_index(matches, Match*, i);
    match_free(tmp);
  }
#endif
    g_array_free(matches, TRUE);
}

Match* match_array_get(GArray* matches, guint i) {
  return g_array_index(matches, Match*, i);
}

int matchFileWithLicenses(MonkState* state, File* file, Licenses* licenses, MatchCallbacks* callbacks) {
  GArray* matches = findAllMatchesBetween(file, licenses,
                                          MAX_ALLOWED_DIFF_LENGTH, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);
  int result = processMatches(state, file, matches, callbacks);

  // we are done: free memory
  match_array_free(matches);

  return result;
}

static char* getFileName(MonkState* state, long pFileId) {
  char* pFile = queryPFileForFileId(state->dbManager, pFileId);

  if (!pFile) {
    printf("file not found for pFileId=%ld\n", pFileId);
    return NULL;
  }
  char* pFileName;

#ifdef MONK_MULTI_THREAD
  #pragma omp critical(getFileName)
#endif
  {
    pFileName = fo_RepMkPath("files", pFile);
  }

  if (!pFileName)
    printf("file '%s' not found\n", pFile);

  free(pFile);

  return pFileName;
}

int matchPFileWithLicenses(MonkState* state, long pFileId, Licenses* licenses, MatchCallbacks* callbacks) {
  File file;
  file.id = pFileId;

  file.fileName = getFileName(state, pFileId);

  int result = 0;
  if (file.fileName != NULL) {
    result = readTokensFromFile(file.fileName, &(file.tokens), DELIMITERS);

    if (result)
    {
      result = matchFileWithLicenses(state, &file, licenses, callbacks);

      g_array_free(file.tokens, TRUE);
    }

    free(file.fileName);
  }

  return result;
}

char* formatMatchArray(GArray * matchInfo) {
  GString* stringBuilder = g_string_new("");

  size_t len = matchInfo->len;
  for (size_t i = 0; i < len; i++) {
    DiffMatchInfo* current = &g_array_index(matchInfo, DiffMatchInfo, i);

    if (current->text.length > 0)
      g_string_append_printf(stringBuilder,
                             "t[%zu+%zu] %s ",
                             current->text.start, current->text.length, current->diffType);
    else
      g_string_append_printf(stringBuilder,
                             "t[%zu] %s ",
                             current->text.start, current->diffType);

    if (current->search.length > 0)
      g_string_append_printf(stringBuilder,
                             "s[%zu+%zu]",
                             current->search.start, current->search.length);
    else
      g_string_append_printf(stringBuilder,
                             "s[%zu]",
                             current->search.start);

    if (i < len-1) {
      g_string_append_printf(stringBuilder, ", ");
    }
  }

  return g_string_free(stringBuilder, FALSE);
}

static unsigned short match_rank(Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return 100;
  } else {
    DiffResult* diffResult = match->ptr.diff;
    License* license = match->license;
    unsigned int licenseLength = license->tokens->len;
    unsigned int numberOfMatches = diffResult->matched;
    unsigned int numberOfAdditions = diffResult->added;

    // calculate result percentage as jaccard index
    double rank = (100.0 * numberOfMatches) / (licenseLength + numberOfAdditions);
    int result = floor(rank);

    result = MIN(result, 99);
    result = MAX(result, 1);

    diffResult->rank = rank;
    diffResult->percentual = result;

    return (unsigned short) result;
  }
}

size_t match_getStart(const Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return match->ptr.full->start;
  } else {
    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint firstDiff = g_array_index(matchedInfo, DiffMatchInfo, 0).text;

    return firstDiff.start;
  }
}

size_t match_getEnd(const Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return match->ptr.full->length + match->ptr.full->start;
  } else {
    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint lastDiff = g_array_index(matchedInfo, DiffMatchInfo, matchedInfo->len-1).text;

    return lastDiff.start + lastDiff.length;
  }
}

gint compareMatchIncluded (gconstpointer a, gconstpointer b) {
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
double match_getRank(const Match* match) {
  if (match->type == MATCH_TYPE_FULL)
    return 100.0;
  else
    return match->ptr.diff->rank;
}

gint compareMatchByRank (gconstpointer a, gconstpointer b) {
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
 * in this case compareMatchIncluded(&m1, &m2) == 1
 *
 * input: [GArray of Match*]
 * output: [GArray of GArray of Match*]
 */
GArray* groupOverlapping(GArray* matches) {
  g_array_sort(matches, compareMatchIncluded);

  GArray* result = g_array_new(TRUE, FALSE, sizeof(GArray*));

  if (matches->len == 0)
    return result;

  Match* firstMatch = match_array_get(matches, 0);
  size_t currentGroupEnd = match_getEnd(firstMatch);

  GArray* currentGroup = g_array_new(TRUE, FALSE, sizeof(GArray*));

  g_array_append_val(currentGroup, firstMatch);

  for (guint i = 1; i < matches->len; ++i) {
    Match* currentMatch = match_array_get(matches, i);
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
Match* greatestMatchInGroup(GArray* matches, GCompareFunc compare) {
  Match* result = match_array_get(matches, 0);

  for (guint j = 0; j < matches->len; j++) {
    Match* match = match_array_get(matches, j);
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

    Match* biggestInGroup = match_array_get(currentGroup, 0);
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

  // some discarded matches could have distorted grouping
  // if grouping again would put some matches together redo all filtering
  GArray* regroupResult = groupOverlapping(result);
  guint regroupCount = regroupResult->len;
  for (guint i = 0; i<regroupResult->len; i++) {
    g_array_free(g_array_index(regroupResult, GArray*, i), TRUE);
  }
  g_array_free(regroupResult, TRUE);
  if (result->len != regroupCount) {
    result = filterNonOverlappingMatches(result);
  }

  return result;
}

int processMatch(MonkState* state, File* file, Match* match, MatchCallbacks* callbacks) {
  License* license = match->license;
  if (match->type == MATCH_TYPE_DIFF) {
    DiffResult* diffResult = match->ptr.diff;

    return callbacks->onDiff(state, file, license, diffResult);
  } else {
    DiffMatchInfo matchInfo;
    matchInfo.text = getFullHighlightFor(file->tokens, match->ptr.full->start, match->ptr.full->length);
    matchInfo.search = getFullHighlightFor(license->tokens, 0, license->tokens->len);
    matchInfo.diffType = FULL_MATCH;

    return callbacks->onFull(state, file, license, &matchInfo);
  }
}

int processMatches(MonkState* state, File* file, GArray* matches, MatchCallbacks* callbacks) {
  if (callbacks->onAll) {
    return callbacks->onAll(state, file, matches);
  }

  if (matches->len == 0)
  {
    return callbacks->onNo(state, file);
  }

  int result = 1;
  for (guint matchIndex = 0; result && (matchIndex < matches->len); matchIndex++)
  {
    Match* match = match_array_get(matches, matchIndex);
    result &= processMatch(state, file, match, callbacks);
  }

  return result;
}

Match* diffResult2Match(DiffResult* diffResult, License* license){
  Match* newMatch = malloc(sizeof(Match));
  newMatch->license = license;

  /* it's full only if we have no diffs and the license was not truncated */
  if (diffResult->matchedInfo->len == 1 && (diffResult->matched == license->tokens->len)) {
    newMatch->type = MATCH_TYPE_FULL;
    newMatch->ptr.full = malloc(sizeof(DiffPoint));
    *(newMatch->ptr.full) = g_array_index(diffResult->matchedInfo, DiffMatchInfo, 0).text;
    diffResult_free(diffResult);
  } else {
    newMatch->type = MATCH_TYPE_DIFF;
    newMatch->ptr.diff = diffResult;

  }
  return newMatch;
}

void findDiffMatches(File* file, License* license,
  size_t textStartPosition, size_t searchStartPosition,
  GArray* matches,
  unsigned int maxAllowedDiff, unsigned int minAdjacentMatches) {

  if (!matchNTokens(file->tokens, textStartPosition, file->tokens->len,
                    license->tokens, searchStartPosition, license->tokens->len,
    minAdjacentMatches)) {
    return;
  }

  DiffResult* diffResult = findMatchAsDiffs(file->tokens, license->tokens,
                                            textStartPosition, searchStartPosition,
                                            maxAllowedDiff, minAdjacentMatches);

  if (diffResult) {
    Match* newMatch = diffResult2Match(diffResult, license);

    if (match_rank(newMatch) > MIN_ALLOWED_RANK)
      g_array_append_val(matches, newMatch);
    else
      match_free(newMatch);
  }
}

#if GLIB_CHECK_VERSION(2,32,0)
void match_destroyNotify(gpointer matchP) {
  match_free(*((Match**) matchP));
}
#endif

void match_free(Match* match) {
  if (match->type == MATCH_TYPE_DIFF)
    diffResult_free(match->ptr.diff);
  else
    free(match->ptr.full);
  free(match);
}
