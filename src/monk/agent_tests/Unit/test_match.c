/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>

#include "match.h"
#include "license.h"

File* getFileWithText(const char* text) {
  char* fileText = g_strdup(text);

  File* result = malloc(sizeof(File));
  result->id = 42;
  result->tokens = tokenize(fileText, "^");
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
  CU_ASSERT_EQUAL(match_getStart(match), start);
  CU_ASSERT_EQUAL(match_getEnd(match), end);
  CU_ASSERT_EQUAL(match->license->refId, refId);

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
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 1, 2))
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 1, 2, 4))
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 2), 2, 4, 5))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findDiffsAtBeginning() {
  File* file = getFileWithText("^e^a^b^c^d^e");
  Licenses* licenses = getNLicensesWithText(2, "a", "e^b^c^d^e");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 2);

  CU_ASSERT_EQUAL(matches->len, 2);
  if (matches->len == 2){
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 1, 2))
    Match* expectedDiff = g_array_index(matches, Match*, 1);
    CU_ASSERT_TRUE(_matchEquals(expectedDiff, 1, 2, 6));
    CU_ASSERT_EQUAL_FATAL(expectedDiff->type, MATCH_TYPE_DIFF);
    CU_ASSERT_EQUAL(expectedDiff->ptr.diff->matchedInfo->len, 2);
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesWithDiff() {
  File* file = getFileWithText("a^b^c^d^e^f");
  Licenses* licenses = getNLicensesWithText(4, "a^c^d", "a^b^d^e", "d", "e^f");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  CU_ASSERT_EQUAL(matches->len, 1);
  if (matches->len == 1) {
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 2, 3, 4))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesTwoGroups() {
  File* file = getFileWithText("a^b^c^d^e^f^g");
  Licenses* licenses = getNLicensesWithText(6, "a^b", "a^b^c^d", "d", "e", "f", "e^f^g");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  CU_ASSERT_EQUAL(matches->len, 2);
  if (matches->len == 2) {
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 1, 0, 4))
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 5, 4, 7))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesTwoGroupsWithDiff() {
  File* file = getFileWithText("a^b^c^d^e^f^g");
  Licenses* licenses = getNLicensesWithText(6, "a^b", "a^b^c^e", "d", "e", "f", "e^f^g");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  CU_ASSERT_EQUAL(matches->len, 3);
  if (matches->len == 3) {
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 0, 2))
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 1), 2, 3, 4))
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 2), 5, 4, 7))
  }

  matchesArray_free(matches);
  file_free(file);
  licenses_free(licenses);
}

void test_findAllMatchesAllIncluded() {
  File* file = getFileWithText("a^b^c^d");
  Licenses* licenses = getNLicensesWithText(3, "a^b^c^d", "b^c", "d");
  GArray* matches = findAllMatchesBetween(file, licenses, 20, 1, 0);

  CU_ASSERT_EQUAL(matches->len, 1);
  if (matches->len == 1) {
    CU_ASSERT_TRUE(_matchEquals(g_array_index(matches, Match*, 0), 0, 0, 4))
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
  CU_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4]");
  free(result);

  g_array_append_val(matchInfo, diff2);
  result = formatMatchArray(matchInfo);
  CU_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4], t[1] b s[3+4]");
  free(result);

  g_array_append_val(matchInfo, diff3);
  g_array_append_val(matchInfo, diff4);
  result = formatMatchArray(matchInfo);
  CU_ASSERT_STRING_EQUAL(result, "t[1+2] a s[3+4], t[1] b s[3+4], t[2+2] b s[3], t[4] b s[3]");
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
Match* _matchWithARankStartAndEnd(int type, double rank, size_t start, size_t end) {
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
  return result;
}

void test_greatestMatchInGroup() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARank(MATCH_TYPE_DIFF, 80.0);
  Match* match2 = _matchWithARank(MATCH_TYPE_FULL, 0.0);
  Match* match3 = _matchWithARank(MATCH_TYPE_FULL, 0.0);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);
  g_array_append_val(matches, match3);

  Match* greatest = greatestMatchInGroup(matches, compareMatchByRank);

  CU_ASSERT_EQUAL(greatest, match2);

  matchesArray_free(matches);
}

void test_greatestMatchInGroup2() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARank(MATCH_TYPE_DIFF, 80.0);
  Match* match2 = _matchWithARank(MATCH_TYPE_DIFF, 60.0);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  Match* greatest = greatestMatchInGroup(matches, compareMatchByRank);

  CU_ASSERT_EQUAL(greatest, match1);

  matchesArray_free(matches);
}

void test_greatestMatchInGroup3() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARank(MATCH_TYPE_DIFF, 50.0);
  Match* match2 = _matchWithARank(MATCH_TYPE_DIFF, 60.0);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  Match* greatest = greatestMatchInGroup(matches, compareMatchByRank);

  CU_ASSERT_EQUAL(greatest, match2);

  matchesArray_free(matches);
}

void test_filterMatches() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 60.0, 0, 6);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  CU_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }
}

void test_filterMatchesEmpty() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  CU_ASSERT_EQUAL(filteredMatches->len, 0);

  matchesArray_free(filteredMatches);
}

void test_filterMatches2() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_FULL, 0.0, 0, 6);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  CU_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }
}

void test_filterMatchesWithTwoGroups() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 50.0, 0, 4);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 60.0, 0, 6);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 70.0, 4, 6);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);
  g_array_append_val(matches, match3);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  // TODO we probably want to return all 3 in this case
  CU_ASSERT_EQUAL(filteredMatches->len, 2);
  if (filteredMatches->len == 2) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match1);
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 1), match3);
    matchesArray_free(filteredMatches);
  }
}

void test_filterMatchesWithBadGroupingAtFirstPass() {
  GArray* matches = g_array_new(TRUE, FALSE, sizeof(Match*));
  Match* match1 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 0, 10);
  Match* match2 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 99.0, 5, 10);
  Match* match3 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 5, 9);
  Match* match4 = _matchWithARankStartAndEnd(MATCH_TYPE_DIFF, 95.0, 5, 14);

  g_array_append_val(matches, match1);
  g_array_append_val(matches, match2);
  g_array_append_val(matches, match3);
  g_array_append_val(matches, match4);

  GArray* filteredMatches = filterNonOverlappingMatches(matches);

  CU_ASSERT_EQUAL(filteredMatches->len, 1);
  if (filteredMatches->len == 1) {
    CU_ASSERT_EQUAL(g_array_index(filteredMatches, Match*, 0), match2);
    matchesArray_free(filteredMatches);
  }
}

CU_TestInfo match_testcases[] = {
  {"Testing match of all licenses with disjoint full matches:", test_findAllMatchesDisjoint},
  {"Testing match of all licenses with diff at beginning", test_findDiffsAtBeginning},
  {"Testing match of all licenses with included full matches:", test_findAllMatchesAllIncluded},
  {"Testing match of all licenses with two included group:", test_findAllMatchesTwoGroups},
  {"Testing match of all licenses with two included group and diffs:", test_findAllMatchesTwoGroupsWithDiff},
  {"Testing formatting the diff information output:", test_formatMatchArray},
  {"Testing finding best match in a group:", test_greatestMatchInGroup},
  {"Testing finding best match in a group, 2:", test_greatestMatchInGroup2},
  {"Testing finding best match in a group, 3:", test_greatestMatchInGroup3},
  {"Testing filtering matches:", test_filterMatches},
  {"Testing filtering matches empty:", test_filterMatchesEmpty},
  {"Testing filtering matches with a full match:", test_filterMatches2},
  {"Testing filtering matches with two groups:", test_filterMatchesWithTwoGroups},
  {"Testing filtering matches with bad grouping at first pass:", test_filterMatchesWithBadGroupingAtFirstPass},
  CU_TEST_INFO_NULL
};
