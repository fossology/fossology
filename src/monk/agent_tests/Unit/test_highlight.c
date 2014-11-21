/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <CUnit/CUnit.h>

#include "highlight.h"
#include "string_operations.h"
#include "diff.h"

void _callConvertToAbsolutePositions(char* text, char* search, GArray* diffMatchInfo) {
  char* testText = g_strdup(text);
  char* testSearch = g_strdup(search);

  GArray* textTokens = tokenize(testText, "." ); 
  GArray* searchTokens = tokenize(testSearch, "." ); 

  convertToAbsolutePositions(diffMatchInfo, textTokens, searchTokens);
}

void _appendToDiffMatchInfo(GArray* diffMatchInfo,
  size_t textPosition, size_t textCount, size_t searchPosition,
  size_t searchCount) {

  DiffMatchInfo toAppend;
  toAppend.diffType = "a";
  toAppend.search = (DiffPoint){.start = searchPosition, .length = searchCount};
  toAppend.text = (DiffPoint){.start = textPosition, .length = textCount};

  g_array_append_val(diffMatchInfo, toAppend);
}

//TODO move to utils and generalize
int _CU_ASSERT_EQUAL(size_t actual, size_t expected, char * error) {
  CU_ASSERT_EQUAL(actual, expected);
  if (actual != expected)
    printf(error, actual, expected);
  return actual == expected;
}

void _assertDiffMatchInfo(GArray* diffMatchInfo, GArray* expectedDiffMatchInfo) {
  CU_ASSERT_EQUAL(diffMatchInfo->len, expectedDiffMatchInfo->len );
  if (diffMatchInfo->len == expectedDiffMatchInfo->len) {
    for (size_t i = 0; i < diffMatchInfo->len; i++) {
      DiffMatchInfo extracted = g_array_index(diffMatchInfo, DiffMatchInfo, i);
      DiffMatchInfo expected = g_array_index(expectedDiffMatchInfo, DiffMatchInfo, i);
      _CU_ASSERT_EQUAL(extracted.search.start, expected.search.start, "ss %zu != %zu\n");
      _CU_ASSERT_EQUAL(extracted.search.length, expected.search.length, "sl %zu != %zu\n");
      _CU_ASSERT_EQUAL(extracted.text.start, expected.text.start, "ts %zu != %zu\n");
      _CU_ASSERT_EQUAL(extracted.text.length, expected.text.length, "tl %zu != %zu\n");
    }
  }
}

void test_convertToAbsolute() {
  GArray* diffMatchInfo = g_array_new(TRUE, FALSE, sizeof(DiffMatchInfo));
  GArray* expectedDiffMatchInfo = g_array_new(TRUE, FALSE, sizeof(DiffMatchInfo));

  char* text = "A.a.bcd.e.f.";
  char* search = "...a.bc.e.f.";

  _appendToDiffMatchInfo(diffMatchInfo, 1, 3, 1, 2);
  _appendToDiffMatchInfo(expectedDiffMatchInfo, 2, 7, 5, 4);

  _appendToDiffMatchInfo(diffMatchInfo, 4, 0, 0, 2);
  _appendToDiffMatchInfo(expectedDiffMatchInfo, 10, 0, 3, 4);

  _appendToDiffMatchInfo(diffMatchInfo, 0, 0, 0, 1);
  _appendToDiffMatchInfo(expectedDiffMatchInfo, 0, 0, 3, 1);

  _callConvertToAbsolutePositions(text, search, diffMatchInfo);

  _assertDiffMatchInfo(diffMatchInfo, expectedDiffMatchInfo);

  g_array_free(diffMatchInfo, TRUE);
  g_array_free(expectedDiffMatchInfo, TRUE);
}

void test_getFullHighlightFor() {
  char* text = g_strdup("...a.aa..b.a.c");

  GArray* tokens = tokenize(text, ".");

  DiffPoint fullHighlight = getFullHighlightFor(tokens, 1, 3);

  _CU_ASSERT_EQUAL(fullHighlight.start, 5, "start %zu!=%zu\n");
  _CU_ASSERT_EQUAL(fullHighlight.length, 7, "length %zu!=%zu\n");

  g_free(text);
  g_array_free(tokens, TRUE);
}

void test_getFullHighlightFor_2() {
  char* text = g_strdup("...a.aa..b.a.c");

  GArray* tokens = tokenize(text, ".");

  DiffPoint fullHighlight = getFullHighlightFor(tokens, 1, 0);

  _CU_ASSERT_EQUAL(fullHighlight.start, 5, "start %zu!=%zu\n");
  _CU_ASSERT_EQUAL(fullHighlight.length, 0, "length %zu!=%zu\n");

  g_free(text);
  g_array_free(tokens, TRUE);
}

CU_TestInfo highlight_testcases[] = {
  {"Testing conversion to absolute positions:", test_convertToAbsolute},
  {"Testing extracting full highlight:", test_getFullHighlightFor},
  {"Testing extracting full highlight with empty:", test_getFullHighlightFor_2},
  CU_TEST_INFO_NULL
};
