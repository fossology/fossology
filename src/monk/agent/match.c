/*
 Authors: Daniele Fognini, Andreas Wuerl, Marion Deveaud
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "match.h"

#include "license.h"
#include "file_operations.h"

static inline void doFindAllMatches(const File* file, const GArray* licenseArray,
                                    guint tPos, guint sPos,
                                    unsigned maxAllowedDiff, unsigned minAdjacentMatches,
                                    GArray* matches) {
  if (!licenseArray) {
    /* we hope to get here very often */
    return;
  }

  for (guint i = 0; i < licenseArray->len; i++) {
    License* license = license_index(licenseArray, i);
    findDiffMatches(file, license, tPos, sPos, matches, maxAllowedDiff, minAdjacentMatches);
  }
}

GArray* findAllMatchesBetween(const File* file, const Licenses* licenses,
        unsigned maxAllowedDiff, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  GArray* matches = g_array_new(FALSE, FALSE, sizeof(Match*));

  const GArray* textTokens = file->tokens;
  const guint textLength = textTokens->len;

  for (guint tPos = 0; tPos < textLength; tPos++) {
    for (guint sPos = 0; sPos <= maxLeadingDiff; sPos++) {
      const GArray* availableLicenses = getLicenseArrayFor(licenses, sPos, textTokens, tPos);
      doFindAllMatches(file, availableLicenses, tPos, sPos, maxAllowedDiff, minAdjacentMatches, matches);
    }

    /* now search short licenses only fully (i.e. maxAllowedDiff = 0, minAdjacentMatches = 1) */
    const GArray* shortLicenses = getShortLicenseArray(licenses);
    doFindAllMatches(file, shortLicenses, tPos, 0, 0, 1, matches);
  }

  return filterNonOverlappingMatches(matches);
}

void match_array_free(GArray* matches) {
#if GLIB_CHECK_VERSION(2, 32, 0)
  g_array_set_clear_func(matches, match_destroyNotify);
#else
  for (unsigned int i=0; i< matches->len; ++i) {
    Match* tmp = g_array_index(matches, Match*, i);
    match_free(tmp);
  }
#endif
  g_array_free(matches, TRUE);
}

int matchFileWithLicenses(MonkState* state, const File* file, const Licenses* licenses, const MatchCallbacks* callbacks) {
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

  if (!pFileName) {
    printf("file '%s' not found\n", pFile);
  }

  free(pFile);

  return pFileName;
}

int matchPFileWithLicenses(MonkState* state, long pFileId, const Licenses* licenses, const MatchCallbacks* callbacks, char* delimiters) {
  File file;
  file.id = pFileId;
  int result = 0;

  file.fileName = getFileName(state, pFileId);

  if (file.fileName != NULL) {
    result = readTokensFromFile(file.fileName, &(file.tokens), delimiters);

    if (result) {
      result = matchFileWithLicenses(state, &file, licenses, callbacks);

      tokens_free(file.tokens);
    }

    free(file.fileName);
  }

  return result;
}

char* formatMatchArray(GArray* matchInfo) {
  GString* stringBuilder = g_string_new("");

  size_t len = matchInfo->len;
  for (size_t i = 0; i < len; i++) {
    DiffMatchInfo* current = &g_array_index(matchInfo, DiffMatchInfo, i);

    if (current->text.length > 0) {
      g_string_append_printf(stringBuilder,
              "t[%zu+%zu] %s ",
              current->text.start, current->text.length, current->diffType);
    }
    else {
      g_string_append_printf(stringBuilder,
              "t[%zu] %s ",
              current->text.start, current->diffType);
    }

    if (current->search.length > 0) {
      g_string_append_printf(stringBuilder,
              "s[%zu+%zu]",
              current->search.start, current->search.length);
    }
    else {
      g_string_append_printf(stringBuilder,
              "s[%zu]",
              current->search.start);
    }

    if (i < len - 1) {
      g_string_append_printf(stringBuilder, ", ");
    }
  }

  return g_string_free(stringBuilder, FALSE);
}

static unsigned short match_rank(Match* match) {
  if (match->type == MATCH_TYPE_FULL) {
    return 100;
  }
  else {
    DiffResult* diffResult = match->ptr.diff;
    const License* license = match->license;
    unsigned int licenseLength = license->tokens->len;
    size_t numberOfMatches = diffResult->matched;
    size_t numberOfAdditions = diffResult->added;

    // calculate result percentage as jaccard index
    double rank = (100.0 * numberOfMatches) / (licenseLength + numberOfAdditions);
    int result = (int) rank;

    result = MIN(result, 99);
    result = MAX(result, 1);

    diffResult->rank = rank;
    diffResult->percentual = (unsigned short) result;

    return (unsigned short) result;
  }
}

static int match_isFull(const Match* match) {
  return match->type == MATCH_TYPE_FULL;
}

size_t match_getStart(const Match* match) {
  if (match_isFull(match)) {
    return match->ptr.full->start;
  }
  else {
    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint firstDiff = g_array_index(matchedInfo, DiffMatchInfo, 0).text;

    return firstDiff.start;
  }
}

size_t match_getEnd(const Match* match) {
  if (match_isFull(match)) {
    return match->ptr.full->length + match->ptr.full->start;
  }
  else {
    GArray* matchedInfo = match->ptr.diff->matchedInfo;

    DiffPoint lastDiff = g_array_index(matchedInfo, DiffMatchInfo, matchedInfo->len - 1).text;

    return lastDiff.start + lastDiff.length;
  }
}

static int match_includes(const Match* big, const Match* small) {
  return (match_getStart(big) <= match_getStart(small)) && (match_getEnd(big) >= match_getEnd(small));
}

// make sure to call it after a match_rank or the result will be junk
double match_getRank(const Match* match) {
  if (match_isFull(match)) {
    return 100.0;
  }
  else {
    return match->ptr.diff->rank;
  }
}

static int compareMatchByRank(const Match* matchA, const Match* matchB) {
  double matchARank = match_getRank(matchA);
  double matchBRank = match_getRank(matchB);

  if (matchARank > matchBRank) {
    return 1;
  }
  if (matchARank < matchBRank) {
    return -1;
  }

  return 0;
}

/* profiling says there is no need to cache the comparison */
static int licenseIncludes(const License* big, const License* small) {
  const GArray* tokensBig = big->tokens;
  const GArray* tokensSmall = small->tokens;

  const guint bigLen = tokensBig->len;
  const guint smallLen = tokensSmall->len;

  if (smallLen == 0) {
    return 1;
  }

  if (smallLen > bigLen) {
    return 0;
  }

  for (guint i = 0; i < bigLen; i++) {
    unsigned n = smallLen;
    if (matchNTokens(tokensBig, i, bigLen, tokensSmall, 0, smallLen, n)) {
      return 1;
    }
  }

  return 0;
}

int licensesDiffer(const License *thisLicense, const License *otherLicense) {
	return (thisLicense->refId != otherLicense->refId);
}

/* N.B. this is only a partial order of matches
 *
 * =0   not comparable
 * >0   thisMatch >= otherMatch
 * <0   thisMatch < otherMatch
 *
 **/
int match_partialComparator(const Match* thisMatch, const Match* otherMatch) {
  const int thisIncludesOther = match_includes(thisMatch, otherMatch);
  const int otherIncludesThis = match_includes(otherMatch, thisMatch);
  const License *thisLicense = thisMatch->license;
  const License *otherLicense = otherMatch->license;

  //Verify if matches overlap
  if (thisIncludesOther || otherIncludesThis) {
    if (match_isFull(thisMatch) && thisIncludesOther) {
      return 1;
    }
    if (match_isFull(otherMatch) && otherIncludesThis) {
      return -1;
    }

    //Verify if licenses overlap
    if (licensesDiffer(thisLicense, otherLicense)) {
      if (licenseIncludes(thisLicense, otherLicense)) {
        return 1;
      }
      if (licenseIncludes(otherLicense, thisLicense)) {
        return -1;
      }
      if (match_isFull(otherMatch) && thisIncludesOther) {
        //a complete different license is included in this match
        return 0;
      }
    }

    return (compareMatchByRank(thisMatch, otherMatch) >= 0) ? 1 : -1;
  }
  return 0;
}

/*
 * finds the maximal matches according to match_partialComparator
 * destructively filter matches array: input array and discarded matches are automatically freed
 **/
GArray* filterNonOverlappingMatches(GArray* matches) {
  const guint len = matches->len;

  /* profiling says this is not time critical and worst case is O(n^2) with any algorithm */
  /* instead of removing elements from the array set them to NULL and create a new array at the end */
  for (guint i = 0; i < len; i++) {
    Match* thisMatch = match_array_index(matches, i);
    if (thisMatch == NULL) {
      continue;
    }

    for (guint j = i + 1; j < len; j++) {
      Match* otherMatch = match_array_index(matches, j);
      if (otherMatch == NULL) {
        continue;
      }

      gint comparison = match_partialComparator(thisMatch, otherMatch);

      if (comparison > 0) {
        match_free(otherMatch);
        match_array_index(matches, j) = NULL;
      }
      else if (comparison < 0) {
        match_free(thisMatch);
        match_array_index(matches, i) = NULL;
        break;
      }
    }
  }

  GArray* result = g_array_new(FALSE, FALSE, sizeof(Match*));
  for (guint i = 0; i < len; i++) {
    Match* thisMatch = match_array_index(matches, i);
    if (thisMatch) {
      g_array_append_val(result, thisMatch);
    }
  }

  g_array_free(matches, TRUE);

  return result;
}

int processMatch(MonkState* state, const File* file, const Match* match, const MatchCallbacks* callbacks) {
  const License* license = match->license;
  if (match->type == MATCH_TYPE_DIFF) {
    DiffResult* diffResult = match->ptr.diff;

    convertToAbsolutePositions(diffResult->matchedInfo, file->tokens, license->tokens);
    return callbacks->onDiff(state, file, license, diffResult);
  }
  else {
    DiffMatchInfo matchInfo;
    matchInfo.text = getFullHighlightFor(file->tokens, match->ptr.full->start, match->ptr.full->length);
    matchInfo.search = getFullHighlightFor(license->tokens, 0, license->tokens->len);
    matchInfo.diffType = FULL_MATCH;

    return callbacks->onFull(state, file, license, &matchInfo);
  }
}

int processMatches(MonkState* state, const File* file, const GArray* matches, const MatchCallbacks* callbacks) {
  if (callbacks->ignore && callbacks->ignore(state, file)) {
    return 1;
  }

  if (callbacks->onAll) {
    return callbacks->onAll(state, file, matches);
  }

  callbacks->onBeginOutput(state);

  const guint matchCount = matches->len;

  int result = 1;
  if (matchCount == 0) {
    result = callbacks->onNo(state, file);
  }

  for (guint matchIndex = 0; result && (matchIndex < matchCount); matchIndex++) {
    const Match* match = match_array_index(matches, matchIndex);
    result &= processMatch(state, file, match, callbacks);
    if (matchIndex != matchCount - 1) {
      callbacks->onBetweenIndividualOutputs(state);
    }
  }

  callbacks->onEndOutput(state);

  return result;
}

Match* diffResult2Match(DiffResult* diffResult, const License* license) {
  Match* newMatch = malloc(sizeof(Match));
  newMatch->license = license;

  /* it's full only if we have no diffs and the license was not truncated */
  if (diffResult->matchedInfo->len == 1 && (diffResult->matched == license->tokens->len)) {
    newMatch->type = MATCH_TYPE_FULL;
    newMatch->ptr.full = malloc(sizeof(DiffPoint));
    *(newMatch->ptr.full) = g_array_index(diffResult->matchedInfo, DiffMatchInfo, 0).text;
    diffResult_free(diffResult);
  }
  else {
    newMatch->type = MATCH_TYPE_DIFF;
    newMatch->ptr.diff = diffResult;

  }
  return newMatch;
}

void findDiffMatches(const File* file, const License* license,
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
    else {
      match_free(newMatch);
    }
  }
}

#if GLIB_CHECK_VERSION(2, 32, 0)

void match_destroyNotify(gpointer matchP) {
  match_free(*((Match**) matchP));
}

#endif

void match_free(Match* match) {
  if (match->type == MATCH_TYPE_DIFF) {
    diffResult_free(match->ptr.diff);
  }
  else {
    free(match->ptr.full);
  }
  free(match);
}
