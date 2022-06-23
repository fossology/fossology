/*
 Authors: Daniele Fognini, Andreas Wuerl, Marion Deveaud
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>
#include <match.h>
#include <monk.h>

#include "libfocunit.h"

#include "match.h"
#include "license.h"

static char* const testFileName = (char*) 0x34;

File* getFileWithText(const char* text) {
  char* fileText = g_strdup(text);

  File* result = malloc(sizeof(File));
  result->id = 42;
  result->tokens = tokenize(fileText, "^");
  result->fileName = testFileName;
  g_free(fileText);

  return result;
}

Licenses* getNLicensesWithText(int count, ...) {
  GArray* licenseArray = g_array_new(TRUE, FALSE, sizeof(License));
  va_list texts;
  va_start(texts, count);
  for (int i = 0; i < count; i++) {
    char* text = g_strdup(va_arg(texts, char*));
    License license;
    license.refId = i;
    license.shortname = g_strdup_printf("%d-testLic", i);
    license.tokens = tokenize(text, "^" );

    g_array_append_val(licenseArray, license);
    g_free(text);
  }
  va_end(texts);

  return buildLicenseIndexes(licenseArray, 1, 0);
}

void file_free(File* file) {
  g_array_free(file->tokens, TRUE);
  free(file);
}

void matchesArray_free(GArray* matches) {
  for (guint i = 0; i < matches->len; i++) {
    Match* match = g_array_index(matches, Match*, i);
    match_free(match);
  }
  g_array_free(matches, TRUE);
}

int _matchEquals(Match* match, long refId, size_t start, size_t end) {
  FO_ASSERT_EQUAL((int) match->license->refId, (int) refId);
  FO_ASSERT_EQUAL((int) match_getStart(match), (int) start);
  FO_ASSERT_EQUAL((int) match_getEnd(match), (int) end);

  return ( (match_getStart(match) == start) &&
           (match_getEnd(match) == end) &&
           (match->license->refId == refId) );
}

void test_findAllMatchesDisjoint() {
  File* file = getFileWithText("^e^a^b^c^d^e");
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");

  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  CU_ASSERT_EQUAL(matches->len, 3);
  if (matches->len == 3) {
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 1, 2))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 1, 2, 4))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 2), 2, 4, 5))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findDiffsAtBeginning() {
  File* file = getFileWithText("^e^a^b^c^d^e");
  Licenses* licenses = getNLicensesWithText(2, "a", "e^b^c^d^e");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 2);

  FO_ASSERT_EQUAL(matches->len, 2);
  if (matches->len == 2) {
    Match* expectedDiff = g_array_index(matches, Match*, 0);
    FO_ASSERT_TRUE(_matchEquals(expectedDiff, 1, 0, 6));
    FO_ASSERT_EQUAL_FATAL(expectedDiff->type, MATCH_TYPE_DIFF);
    CU_ASSERT_EQUAL(expectedDiff->ptr.diff->matchedInfo->len, 3);
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 0, 1, 2))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesWithDiff() {
  File* file = getFileWithText("a^b^c^d^e^f");
  Licenses* licenses = getNLicensesWithText(4, "a^c^d", "a^b^d^e", "d", "e^f");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  FO_ASSERT_EQUAL(matches->len, 2);
  if (matches->len == 2) {
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 1, 0, 5))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 3, 4, 6))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesTwoGroups() {
  File* file = getFileWithText("a^b^c^d^e^f^g");
  Licenses* licenses = getNLicensesWithText(6, "a^b", "a^b^c^d", "d", "e", "f", "e^f^g");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  FO_ASSERT_EQUAL(matches->len, 2);
  if (matches->len == 2) {
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 1, 0, 4))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 5, 4, 7))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesTwoGroupsWithDiff() {
  File* file = getFileWithText("a^b^c^d^e^f^g");
  Licenses* licenses = getNLicensesWithText(6, "a^b", "a^b^c^e", "d", "e", "f", "e^f^g");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  FO_ASSERT_EQUAL(matches->len, 3);
  if (matches->len == 3) {
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 1, 0, 5))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 2, 3, 4))
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 2), 5, 4, 7))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesAllIncluded() {
  File* file = getFileWithText("a^b^c^d");
  Licenses* licenses = getNLicensesWithText(3, "a^b^c^d", "b^c", "d");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  FO_ASSERT_EQUAL(matches->len, 1);
  if (matches->len == 1) {
    FO_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 0, 4))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_formatMatchArray() {
  DiffMatchInfo diff1 = (DiffMatchInfo){
    .diffType = "a",
    .text = (DiffPoint) { .start = 1, .length = 2 },
    .search = (DiffPoint) { .start = 3, .length = 4 },
  };
  DiffMatchInfo diff2 = (DiffMatchInfo){
    .diffType = "b",
    .text = (DiffPoint) { .start = 1, .length = 0 },
    .search = (DiffPoint) { .start = 3, .length = 4 },
  };
  DiffMatchInfo diff3 = (DiffMatchInfo){
    .diffType = "b",
    .text = (DiffPoint) { .start = 2, .length = 2 },
    .search = (DiffPoint) { .start = 3, .length = 0 },
  };
  DiffMatchInfo diff4 = (DiffMatchInfo){
    .diffType = "b",
    .text = (DiffPoint) { .start = 4, .length = 0 },
    .search = (DiffPoint) { .start = 3, .length = 0 },
  };

  char* result;
  GArray* matchInfo = g_array_new(TRUE, FALSE, sizeof(DiffMatchInfo));

  g_array_append_val(matchInfo, diff1);
  result = formatMatchArray(matchInfo);
  FO_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4]");
  free(result);

  g_array_append_val(matchInfo, diff2);
  result = formatMatchArray(matchInfo);
  FO_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4], t[1] b s[3+4]");
  free(result);

  g_array_append_val(matchInfo, diff3);
  g_array_append_val(matchInfo, diff4);
  result = formatMatchArray(matchInfo);
  FO_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4], t[1] b s[3+4], t[2+2] b s[3], t[4] b s[3]");
  free(result);

  g_array_free(matchInfo, TRUE);
}

// match initialized with just enough to run _getRank() and _free()
// if type == MATCH_TYPE_FULL then _getRank() == 100 irrespective of the rank variable
Match* _matchWithARank(int type, double rank) {
  Match* result = malloc(sizeof(Match));

  result->type = type;
  if (type == MATCH_TYPE_DIFF) {
    result->ptr.diff = malloc(sizeof(DiffResult));
    result->ptr.diff->rank = rank;
    result->ptr.diff->matchedInfo = g_array_new(TRUE, FALSE, sizeof(DiffMatchInfo));
  } else {
    result->ptr.full = malloc(sizeof(DiffPoint));
  }
  return result;
}

// match initialized with just enough to have
// _getRank() == rank, _getStart() == start, _getEnd() == end and working _free()
Match* _matchWithARankStartAndEnd(int type, double rank, size_t start, size_t end, License* license) {
  Match* result = _matchWithARank(type, rank);
  if (type == MATCH_TYPE_FULL) {
    result->ptr.full->start = start;
    result->ptr.full->length = end - start;
  } else {
    DiffMatchInfo matchInfo;
    matchInfo.diffType = NULL;
    matchInfo.text.start = start;
    matchInfo.text.length = end - start;
    matchInfo.search = (DiffPoint){0,0};
    g_array_append_val(result->ptr.diff->matchedInfo, matchInfo);
  }
  result->license = license;
  return result;
}

void test_filterMatches() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");
  License* license = &g_array_index(licenses->licenses, License, 0);
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4, license);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 60.0, 0, 6, license);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  FO_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }

  licenses_free(licenses);
}

void test_filterMatchesEmpty() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  FO_ASSERT_EQUAL(filteredMatches->len, 0);

  matchesArray_free(filteredMatches);
}

void test_filterMatches2() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");
  License* license = &g_array_index(licenses->licenses, License, 0);
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4, license);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 0.0, 0, 6, license);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  FO_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }

  licenses_free(licenses);
}

void test_filterMatchesWithTwoGroups() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");
  License* license = &g_array_index(licenses->licenses, License, 0);
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4, license);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 60.0, 0, 6, license);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 70.0, 4, 6, license);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);
  g_array_append_val(matches, match3);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  FO_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match3);
    matchesArray_free(filteredMatches);
  }

  licenses_free(licenses);
}

void test_filterMatchesWithBadGroupingAtFirstPass() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");
  License* license = &g_array_index(licenses->licenses, License, 0);
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 0, 10, license);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 99.0, 5, 10, license);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 5, 9, license);
  Match* match4 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 5, 14, license);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);
  g_array_append_val(matches, match3);
  g_array_append_val(matches, match4);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  FO_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }

  licenses_free(licenses);
}

MonkState* testState = (MonkState*) 0x17;

int expectOnAll;
int onAll(MonkState* state, const File* file, const GArray* matches) {
  FO_ASSERT_PTR_NOT_NULL(matches);
  CU_ASSERT_EQUAL(state, testState);
  CU_ASSERT_EQUAL(file->fileName, testFileName);
  FO_ASSERT_TRUE(expectOnAll);
  return 1;
}

int expectOnNo;
int onNo(MonkState* state, const File* file) {
  CU_ASSERT_EQUAL(state, testState);
  CU_ASSERT_EQUAL(file->fileName, testFileName);
  FO_ASSERT_TRUE(expectOnNo);
  return 1;
}

int expectOnFull;
int onFull(MonkState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo) {
  FO_ASSERT_PTR_NOT_NULL(license);
  FO_ASSERT_PTR_NOT_NULL(matchInfo);
  CU_ASSERT_EQUAL(state, testState);
  CU_ASSERT_EQUAL(file->fileName, testFileName);
  FO_ASSERT_TRUE(expectOnFull);
  return 1;
}

int expectOnDiff;
int onDiff(MonkState* state, const File* file, const License* license, const DiffResult* diffResult) {
  FO_ASSERT_PTR_NOT_NULL(license);
  FO_ASSERT_PTR_NOT_NULL(diffResult);
  CU_ASSERT_EQUAL(state, testState);
  CU_ASSERT_EQUAL(file->fileName, testFileName);
  FO_ASSERT_TRUE(expectOnDiff);
  return 1;
}

int doIgnore;

int ignore(MonkState* state, const File* file) {
  CU_ASSERT_EQUAL(state, testState);
  CU_ASSERT_EQUAL(file->fileName, testFileName);
  return doIgnore;
}

int noop(MonkState* state) {
  return 1;
}

void doProcessTest(MatchCallbacks* expectedCallbacks)
{
  File* file = getFileWithText("^e^a^b^c^d^e");
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "d");

  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  processMatches(testState, file, matches, expectedCallbacks);

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_processMatchesIgnores() {
  doIgnore = 1;
  expectOnAll = 0;
  expectOnDiff = 0;
  expectOnFull = 0;
  expectOnNo = 0;
  MatchCallbacks expectedCallbacks =
    { .ignore = ignore,
      .onAll = onAll,
      .onDiff = onDiff,
      .onFull = onFull,
      .onNo = onNo,
      .onBeginOutput = noop,
      .onBetweenIndividualOutputs = noop,
      .onEndOutput = noop
    };

  doProcessTest(&expectedCallbacks);
}

void test_processMatchesUsesOnAllIfDefined() {
  doIgnore = 0;
  expectOnAll = 1;
  expectOnDiff = 0;
  expectOnFull = 0;
  expectOnNo = 0;
  MatchCallbacks expectedCallbacks =
    { .ignore = ignore,
      .onAll = onAll,
      .onDiff = onDiff,
      .onFull = onFull,
      .onNo = onNo,
      .onBeginOutput = noop,
      .onBetweenIndividualOutputs = noop,
      .onEndOutput = noop
    };

  doProcessTest(&expectedCallbacks);
}

void test_processMatchesUsesOnFullIfOnAllNotDefined() {
  doIgnore = 0;
  expectOnAll = 0;
  expectOnDiff = 0;
  expectOnFull = 1;
  expectOnNo = 0;
  MatchCallbacks expectedCallbacks =
    { .ignore = ignore,
      .onDiff = onDiff,
      .onFull = onFull,
      .onNo = onNo,
      .onBeginOutput = noop,
      .onBetweenIndividualOutputs = noop,
      .onEndOutput = noop
    };

  doProcessTest(&expectedCallbacks);
}

void test_processMatchesUsesOnNoOnNoMatches() {
  doIgnore = 0;
  expectOnAll = 0;
  expectOnDiff = 0;
  expectOnFull = 0;
  expectOnNo = 1;
  MatchCallbacks expectedCallbacks =
    { .ignore = ignore,
      .onDiff = onDiff,
      .onFull = onFull,
      .onNo = onNo,
      .onBeginOutput = noop,
      .onBetweenIndividualOutputs = noop,
      .onEndOutput = noop
    };

  GArray* matches = g_array_new(FALSE, FALSE, 1);

  File* file = getFileWithText("^e^a^b^c^d^e");
  processMatches(testState, file, matches, &expectedCallbacks);

  file_free(file);
  g_array_free(matches, TRUE);
}

void test_processMatchesUsesOnAllForNoMatches() {
  doIgnore = 0;
  expectOnAll = 1;
  expectOnDiff = 0;
  expectOnFull = 0;
  expectOnNo = 0;
  MatchCallbacks expectedCallbacks =
    { .ignore = ignore,
      .onAll = onAll,
      .onDiff = onDiff,
      .onFull = onFull,
      .onNo = onNo,
      .onBeginOutput = noop,
      .onBetweenIndividualOutputs = noop,
      .onEndOutput = noop
    };

  GArray* matches = g_array_new(FALSE, FALSE, 1);

  File* file = getFileWithText("^e^a^b^c^d^e");
  processMatches(testState, file, matches, &expectedCallbacks);

  file_free(file);
  g_array_free(matches, TRUE);
}

void test_matchComparatorSameLicenseFullVsDiff() {
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 100.0, 1, 2, licensePtr);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) > 0); // full > diff
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) < 0); // diff < full

  match_free(match1);
  match_free(match2);
  licenses_free(licenses);
}

void test_matchComparatorDifferentLicensesNonIncluded() {
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr+1);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr+2);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) > 0); // match1 >= match2
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) > 0); // match2 >= match1

  match_free(match1);
  match_free(match2);
  licenses_free(licenses);
}

void test_matchComparatorDifferentLicensesIncluded() {
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 100.0, 1, 3, licensePtr+2);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 100.0, 1, 8, licensePtr+2);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) < 0); // match1.license < match2.license
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) > 0); // match2.license > match1.license
  CU_ASSERT_TRUE(match_partialComparator(match1, match3) < 0); // match1.license < match3.license
  CU_ASSERT_TRUE(match_partialComparator(match3, match1) > 0); // match3.license > match1.license
  CU_ASSERT_TRUE(match_partialComparator(match2, match3) > 0); // match2 rank >= match3 rank
  CU_ASSERT_TRUE(match_partialComparator(match3, match2) > 0); // match3 rank >= match2 rank

  match_free(match1);
  match_free(match2);
  match_free(match3);
  licenses_free(licenses);
}

void test_matchComparatorIncludedSameLicenseNotComparable() {
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 100.0, 4, 8, licensePtr+2);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) == 0); // start(match2) > end(match1)
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) == 0); // start(match2) > end(match1)

  match_free(match1);
  match_free(match2);
  licenses_free(licenses);
}

void test_matchComparatorIncludedSameLicenseComparedByRank() {
  Licenses* licenses = getNLicensesWithText(3, "a", "b^c", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 1, 2, licensePtr);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 90.0, 1, 8, licensePtr+2);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 99.0, 1, 8, licensePtr+2);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) < 0); //match2.license > match1.license
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) > 0);
  CU_ASSERT_TRUE(match_partialComparator(match3, match1) > 0); //match3.license > match1.license
  CU_ASSERT_TRUE(match_partialComparator(match1, match3) < 0);
  CU_ASSERT_TRUE(match_partialComparator(match3, match2) > 0); //match3 > match2
  CU_ASSERT_TRUE(match_partialComparator(match2, match3) < 0);

  match_free(match1);
  match_free(match2);
  match_free(match3);
  licenses_free(licenses);
}

void test_matchComparatorIncludedSameLicenseBiggerMatch() {
  Licenses* licenses = getNLicensesWithText(3, "a^b^c^d", "b^c^d", "a^b");
  License* licensePtr = (License*) licenses->licenses->data;
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 0, 4, licensePtr);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 0, 3, licensePtr+1);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 100.0, 0, 2, licensePtr+2);

  CU_ASSERT_TRUE(match_partialComparator(match1, match2) > 0); //license2 included in license1
  CU_ASSERT_TRUE(match_partialComparator(match2, match1) < 0); 
  CU_ASSERT_TRUE(match_partialComparator(match1, match3) > 0); //license3 included in license1
  CU_ASSERT_TRUE(match_partialComparator(match3, match1) < 0);  
  CU_ASSERT_TRUE(match_partialComparator(match3, match2) < 0); //full match2 overlap match3
  CU_ASSERT_TRUE(match_partialComparator(match2, match3) > 0);

  match_free(match1);
  match_free(match2);
  match_free(match3);
  licenses_free(licenses);
}

CU_TestInfo match_testcases[] = {
  {"Testing match of all licenses with disjoint full matches:", test_findAllMatchesDisjoint},
  {"Testing match of all licenses with diff at beginning", test_findDiffsAtBeginning},
  {"Testing match of all licenses with diffs:", test_findAllMatchesWithDiff},
  {"Testing match of all licenses with included full matches:", test_findAllMatchesAllIncluded},
  {"Testing match of all licenses with two included group:", test_findAllMatchesTwoGroups},
  {"Testing match of all licenses with two included group and diffs:", test_findAllMatchesTwoGroupsWithDiff},
  {"Testing formatting the diff information output:", test_formatMatchArray},
  {"Testing filtering matches:", test_filterMatches},
  {"Testing filtering matches empty:", test_filterMatchesEmpty},
  {"Testing filtering matches with a full match:", test_filterMatches2},
  {"Testing filtering matches with two groups:", test_filterMatchesWithTwoGroups},
  {"Testing filtering matches with bad grouping at first pass:", test_filterMatchesWithBadGroupingAtFirstPass},
  {"Testing matches processor does nothing if ignore callback is true:", test_processMatchesIgnores},
  {"Testing matches processor uses on all if defined:", test_processMatchesUsesOnAllIfDefined},
  {"Testing matches processor uses on full if on all not defined:", test_processMatchesUsesOnFullIfOnAllNotDefined},
  {"Testing matches processor uses on no if no matches:", test_processMatchesUsesOnNoOnNoMatches},
  {"Testing matches processor uses on all if defined and no matches:", test_processMatchesUsesOnAllForNoMatches},
  {"Testing matches comparator:", test_matchComparatorSameLicenseFullVsDiff},
  {"Testing matches comparator different licenses not included one in the other:", test_matchComparatorDifferentLicensesNonIncluded},
  {"Testing matches comparator different licenses included one in the other:", test_matchComparatorDifferentLicensesIncluded},
  {"Testing matches comparator included same licence not comparable:", test_matchComparatorIncludedSameLicenseNotComparable},
  {"Testing matches comparator included same license compared by rank:", test_matchComparatorIncludedSameLicenseComparedByRank},
  {"Testing matches comparator included same license bigger match:", test_matchComparatorIncludedSameLicenseBiggerMatch},
  CU_TEST_INFO_NULL
};
