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
#include <glib.h>
#include <stdarg.h>

#include "hash.h"
#include "string_operations.h"
#include "diff.h"
#include "monk.h"

int token_search_diff(char * text, char* search,
        size_t expectedMatchCount, size_t expectedAdditionsCount, size_t expectedRemovalsCount, ...) {

  int result;
  result = 0;

  char* textCopy = g_strdup(text);
  char* searchCopy = g_strdup(search);

  GArray* tokenizedText = tokenize(textCopy, "^");
  GArray* tokenizedSearch = tokenize(searchCopy, "^");

  GArray * matched = g_array_new(TRUE, FALSE, sizeof (size_t));
  GArray * additions = g_array_new(TRUE, FALSE, sizeof (size_t));
  GArray * removals = g_array_new(TRUE, FALSE, sizeof (size_t));

  size_t textStartPosition = 0;
  DiffResult * diffResult = findMatchAsDiffs(tokenizedText, tokenizedSearch, &textStartPosition, 50, 1);
  if(expectedAdditionsCount + expectedMatchCount + expectedRemovalsCount == 0) {
    CU_ASSERT_PTR_NULL(diffResult);
    return diffResult != NULL;
  } else {
    CU_ASSERT_PTR_NOT_NULL(diffResult);
    if (!diffResult) {
      printf("null result unexpected\n");
      result = 0;
      goto end;
    }
  }
  GArray* matchedInfo = diffResult->matchedInfo;

  size_t matchedCount = 0;
  size_t additionCount = 0;
  size_t removalCount = 0;

  for(size_t i=0; i<matchedInfo->len; i++){
    DiffMatchInfo diffInfo = g_array_index(matchedInfo, DiffMatchInfo, i);
    if (strcmp(diffInfo.diffType, DIFF_TYPE_ADDITION)==0) {
      additionCount += diffInfo.text.length;
      for(size_t j=diffInfo.text.start;
          j<diffInfo.text.start + diffInfo.text.length;
      j++)
        g_array_append_val(additions, j);
    }
    if (strcmp(diffInfo.diffType, DIFF_TYPE_MATCH)==0) {
      for(size_t j=diffInfo.text.start;
          j<diffInfo.text.start + diffInfo.text.length; j++)
        g_array_append_val(matched, j);
       matchedCount += diffInfo.text.length;
    }
    if (strcmp(diffInfo.diffType, DIFF_TYPE_REMOVAL)==0) {
      g_array_append_val(removals, diffInfo.text.start);
      removalCount ++;
    }
    if (strcmp(diffInfo.diffType, DIFF_TYPE_REPLACE)==0) {
      additionCount += diffInfo.text.length;
      for(size_t j=diffInfo.text.start;
          j<diffInfo.text.start + diffInfo.text.length; j++)
        g_array_append_val(additions, j);
      removalCount++;
      g_array_append_val(removals, diffInfo.text.start);
    }
  }
  CU_ASSERT_EQUAL(matchedCount, expectedMatchCount);
  CU_ASSERT_EQUAL(additionCount, expectedAdditionsCount);
  CU_ASSERT_EQUAL(removalCount, expectedRemovalsCount);
  CU_ASSERT_EQUAL(matched->len, expectedMatchCount);
  CU_ASSERT_EQUAL(additions->len, expectedAdditionsCount);
  CU_ASSERT_EQUAL(removals->len, expectedRemovalsCount);

  va_list argptr;
  va_start(argptr, expectedRemovalsCount);

  if (expectedMatchCount == matchedCount) {
    if (expectedAdditionsCount == additionCount) {
      if (expectedRemovalsCount == removalCount) {
        size_t i;
        size_t actual;
        size_t expected;
        for (i = 0; i < expectedMatchCount; i++) {
          expected = va_arg(argptr, int);
          actual = g_array_index(matched, size_t, i);
          CU_ASSERT_EQUAL(actual, expected);
          if (actual != expected) {
            printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"):\n", text, search);
            printf("matched[%ld] == %ld != %ld\n", i, actual, expected);
            goto end;
          }
        }
        for (i = 0; i < expectedAdditionsCount; i++) {
          expected = va_arg(argptr, int);
          actual = g_array_index(additions, size_t, i);
          CU_ASSERT_EQUAL(actual, expected);
          if (actual != expected) {
            printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"):\n", text, search);
            printf("additions[%ld] == %ld != %ld\n", i, actual, expected);
            goto end;
          }
        }
        for (i = 0; i < expectedRemovalsCount; i++) {
          expected = va_arg(argptr, int);
          actual = g_array_index(removals, size_t, i);
          CU_ASSERT_EQUAL(actual, expected);
          if (actual != expected) {
            printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"):\n", text, search);
            printf("removals[%ld] == %ld != %ld\n", i, actual, expected);
            goto end;
          }
        }
      } else
        printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"): removals(%zu) != expected(%ld)\n",
              text, search, removalCount, expectedRemovalsCount);
    } else
      printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"): additions(%zu) != expected(%ld)\n",
            text, search, additionCount, expectedAdditionsCount);
  } else
    printf("ASSERT ERROR: findMatchAsDiffs(\"%s\", \"%s\"): matched(%zu) != expected(%ld)\n",
          text, search, matchedCount, expectedMatchCount);

  result = 1;
end:

  va_end(argptr);

  if(diffResult)
    diffResult_free(diffResult);
  g_array_free(matched, TRUE);
  g_array_free(additions, TRUE);
  g_array_free(removals, TRUE);

  g_array_free(tokenizedText, TRUE);
  g_array_free(tokenizedSearch, TRUE);

  free(textCopy);
  free(searchCopy);

  return result;
}

void test_token_search_diffs() {
  // simple matches
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^foo^^bar^", "one",
          1, 0, 0,
          0));
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^foo^^bar^", "bar",
          1, 0, 0,
          4));
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^foo^^bar^", "two",
          1, 0, 0,
          1));
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^foo^^bar^", "3^foo",
          2, 0, 0,
          2, 3));
  CU_ASSERT_FALSE(token_search_diff("^one^^two^^3^^foo^^bar^", "",
          0, 0, 0));

  // not matches
  CU_ASSERT_FALSE(token_search_diff("^one^^two^^3^^foo^^bar^", "one^^foo^^bar^^3",
          0, 0, 0));
  CU_ASSERT_FALSE(token_search_diff("^one^^two^^3^^bar", "one^^3^3^^bar^^z",
          0, 0, 0));
  CU_ASSERT_FALSE(token_search_diff("one^two^^three^^bas", "one^^3^3^^bar^^z",
          0, 0, 0));

  // simple additions
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^foo^^bar^", "one^^foo^^bar",
          3, 2, 0,
          0, 3, 4, // matched
          1, 2 // additions
          ));

  // simple removals
  CU_ASSERT_TRUE(token_search_diff("^one^^3^^bar^z", "one^^3^3^^5^^bar^^y^^z",
          4, 0, 2,
          0, 1, 2, 3, // matched
          2, 3 // removals
          ));

  // mixed additions and removals
  CU_ASSERT_TRUE(token_search_diff("^one^^two^^3^^bar^z", "one^^3^3^^bar^^z",
          4, 1, 1,
          0, 2, 3, 4, // matched
          1, // additions
          3 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^one^^two^^1^2^3^4^5^z", "one^^1^2^3^4^5^4^z",
          7, 1, 1,
          0, 2, 3, 4, 5, 6, 7, // matched
          1, // additions
          7 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^one^^3^^bar^5^e", "one^^3^bar^^4^e",
          4, 1, 1,
          0, 1, 2, 4, // matched
          3, // additions
          3 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^one^^3^^bar^5^z", "one^^3^bar^^4^a^^z",
          4, 1, 1,
          0, 1, 2, 4, // matched
          3, // additions
          3 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^one^^3^^bar^5^6^z", "one^^3^bar^^4^^z",
          4, 2, 1,
          0, 1, 2, 5, // matched
          3, 4, // additions
          3 // removals
          ));

  // simple replace
  CU_ASSERT_TRUE(token_search_diff("^foo^^one^two^three^^bar", "foo^1^two^three^bar",
          4, 1, 1,
          0, 2, 3, 4, // matched
          1, 1, // additions
          1 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^foo^^one^bar^two^three^^bar", "foo^1^two^three^bar",
          4, 2, 1,
          0, 3, 4, 5, // matched
          1, 2, // additions
          1 // removals
          ));

  CU_ASSERT_TRUE(token_search_diff("^foo^^one^two^three^^bar", "foo^1^2^two^three^bar",
          4, 1, 1,
          0, 2, 3, 4, // matched
          1, // additions
          1 // removals
          ));
}

int token_search(char * text, char* search, size_t expectedStart) {
  int result;
  char* textCopy = g_strdup(text);
  char* searchCopy = g_strdup(search);

  GArray* tokenizedText = tokenize(textCopy, "^");
  GArray* tokenizedSearch = tokenize(searchCopy, "^");

  size_t matchStart = 0;
  result = findMatchFull(tokenizedText, tokenizedSearch, &matchStart);

  CU_ASSERT_EQUAL(expectedStart, matchStart);

  g_array_free(tokenizedText, TRUE);
  g_array_free(tokenizedSearch, TRUE);
  free(textCopy);
  free(searchCopy);

  return result;
}

void test_token_search() {
  CU_ASSERT_TRUE(token_search("^one^^two^^3^^foo^^bar^", "one", 0));
  CU_ASSERT_TRUE(token_search("^one^^two^^3^^foo^^bar^", "bar", 4));
  CU_ASSERT_TRUE(token_search("^one^^two^^3^^foo^^bar^", "two", 1));
  CU_ASSERT_TRUE(token_search("^one^^two^^3^^foo^^bar^", "3^foo", 2));

  CU_ASSERT_FALSE(token_search("^^", "one", 0));
  CU_ASSERT_FALSE(token_search("^one^", "^^", 0));

  CU_ASSERT_FALSE(token_search("^one^^two^^3^^foo^^bar^", "3^^foo^two", 0));

  CU_ASSERT_TRUE(token_search("^3^one^^two^^3^^foo^^bar^", "3^^foo", 3));
}

void test_matchNTokens(){
  char* text = g_strdup("a.b.c.d.e.f.g");
  char* search = g_strdup("a.b.c.d.E.E.f.g");

  GArray* textTokens = tokenize(text,".");
  GArray* searchTokens = tokenize(search,".");

  CU_ASSERT_TRUE(matchNTokens(textTokens, 1, textTokens->len,
                              searchTokens, 1, searchTokens->len,
                              2));

  CU_ASSERT_TRUE(matchNTokens(textTokens, 5, textTokens->len,
                              searchTokens, 6, searchTokens->len,
                              1));

  CU_ASSERT_FALSE(matchNTokens(textTokens, 1, textTokens->len,
                              searchTokens, 1, searchTokens->len,
                              7));
}

void _test_lookForAdditions(char * text, char * search,
        int textPosition, int searchPosition, int maxAllowedDiff, int minTrailingMatches,
        int expectedDiff, int expectedTextPosition, int expectedSearchPosition) {
  char * testText = g_strdup(text);
  char * testSearch = g_strdup(search);

  GArray * textTokens = tokenize(testText, "^");
  GArray * searchTokens = tokenize(testSearch, "^");

  DiffMatchInfo result = lookForDiff(textTokens, searchTokens,
          textPosition, searchPosition, maxAllowedDiff, minTrailingMatches);

  if (result.diffSize != expectedDiff) {
    printf("adds(%s,%s): result.diffSize == %ld != %d\n",
            text, search, result.diffSize, expectedDiff);
  }
  if (result.search.start != expectedSearchPosition) {
    printf("adds(%s,%s): result.search.start == %zu != %d\n", text, search,
            result.search.start, expectedSearchPosition);
  }
  if (result.text.start != expectedTextPosition) {
    printf("adds(%s,%s): result.text.start == %zu != %d\n", text, search,
            result.text.start, expectedTextPosition);
  }

  CU_ASSERT_TRUE(result.diffSize == expectedDiff);
  CU_ASSERT_TRUE(result.search.start == expectedSearchPosition);
  CU_ASSERT_TRUE(result.text.start == expectedTextPosition);

  free(testText);
  free(testSearch);

}

void _test_lookForRemovals(char * text, char * search,
        int textPosition, int searchPosition, int maxAllowedDiff, int minTrailingMatches,
        int expectedDiff, int expectedTextPosition, int expectedSearchPosition) {
  char * testText = g_strdup(text);
  char * testSearch = g_strdup(search);

  GArray * textTokens = tokenize(testText, "^");
  GArray * searchTokens = tokenize(testSearch, "^");

  DiffMatchInfo result = lookForDiff(textTokens, searchTokens,
          textPosition, searchPosition, maxAllowedDiff, minTrailingMatches);

  if (result.diffSize != expectedDiff) {
    printf("rems(%s,%s): result.diffSize == %ld != %d\n",
            text, search, result.diffSize, expectedDiff);
  }
  if (result.search.start != expectedSearchPosition) {
    printf("rems(%s,%s): result.search.start == %zu != %d\n", text, search,
            result.search.start, expectedSearchPosition);
  }
  if (result.text.start != expectedTextPosition) {
    printf("rems(%s,%s): result.text.start == %zu != %d\n", text, search,
            result.text.start, expectedTextPosition);
  }

  CU_ASSERT_TRUE(result.diffSize == expectedDiff);
  CU_ASSERT_TRUE(result.search.start == expectedSearchPosition);
  CU_ASSERT_TRUE(result.text.start == expectedTextPosition);

  free(testText);
  free(testSearch);
}

void test_lookForReplacesNotOverflowing() {
  int max = MAX_ALLOWED_DIFF_LENGTH+1;
  char * testText = malloc((max)*3+1);
  char * testSearch = malloc((max)*3+1);

  char * ptr1 =testSearch;
  char * ptr2 =testText;
  for(int i = 0; i<max; i++) {
    *ptr1='A'+(i%26);
    *ptr2='a'+(i%26);
    ptr1++;
    ptr2++;
    *ptr1='a'+((i/26)%26);
    *ptr2='A'+((i/26)%26);
    ptr1++;
    ptr2++;
    *ptr1='^';
    *ptr2='^';
    ptr1++;
    ptr2++;
  }
  int matchPosition = max+1;
  *(testSearch + 3*(matchPosition-1))='m';
  *(testSearch + 3*(matchPosition-1)+1)='m';
  *(testText + 3)='m';
  *(testText+ 3+1)='m';
  *ptr1 = '\0';
  *ptr2 = '\0';

  GArray * textTokens = tokenize(testText, "^");
  GArray * searchTokens = tokenize(testSearch, "^");

  DiffMatchInfo result = lookForDiff(textTokens, searchTokens,
                                           0, 0, max, 1);

  CU_ASSERT_TRUE(result.diffSize == -1);
  CU_ASSERT_TRUE(result.search.start == 0);
  CU_ASSERT_TRUE(result.text.start == 0);

  free(testText);
  free(testSearch);
}

void _test_lookForReplaces(char * text, char * search,
        int textPosition, int searchPosition, int maxAllowedDiff, int minTrailingMatches,
        int expectedDiff, int expectedTextPosition, int expectedSearchPosition) {
  char * testText = g_strdup(text);
  char * testSearch = g_strdup(search);

  GArray * textTokens = tokenize(testText, "^");
  GArray * searchTokens = tokenize(testSearch, "^");

  DiffMatchInfo result = lookForDiff(textTokens, searchTokens,
          textPosition, searchPosition, maxAllowedDiff, minTrailingMatches);

  if (result.diffSize != expectedDiff) {
    printf("replS(%s,%s): result.diffSize == %ld != %d\n",
            text, search, result.diffSize, expectedDiff);
  }
  if (result.search.start != expectedSearchPosition) {
    printf("replS(%s,%s): result.search.start == %zu != %d\n", text, search,
            result.search.start, expectedSearchPosition);
  }
  if (result.text.start != expectedTextPosition) {
    printf("replS(%s,%s): result.text.start == %zu != %d\n", text, search,
            result.text.start, expectedTextPosition);
  }

  CU_ASSERT_TRUE(result.diffSize == expectedDiff);
  CU_ASSERT_TRUE(result.search.start == expectedSearchPosition);
  CU_ASSERT_TRUE(result.text.start == expectedTextPosition);

  free(testText);
  free(testSearch);
}

void test_lookForAdditions() {
  _test_lookForAdditions(
          "one^two",
          "two",
          0, 0, 5, 1,
          1,
          1, 0);

  _test_lookForAdditions(
          "one^two^three^four^five",
          "five",
          0, 0, 2, 1,
          -1,
          0, 0);

  _test_lookForAdditions(
          "one^two^three^four",
          "one",
          1, 0, 6, 1,
          -1,
          1, 0);

  _test_lookForAdditions(
          "1^d^a^test_starts_here^two^three",
          "v^test_starts_here^^three",
          4, 2, 5, 1,
          1,
          5, 2);

  _test_lookForAdditions(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^eight",
          4, 2, 10, 1,
          -1,
          4, 2);

  _test_lookForAdditions(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^seven",
          4, 2, 2, 1,
          -1,
          4, 2);
}

void test_lookForRemovals() {
  _test_lookForRemovals(
          "two",
          "one^two",
          0, 0, 5, 1,
          1,
          0, 1);

  _test_lookForRemovals(
          "five",
          "one^two^three^four^five",
          0, 0, 2, 1,
          -1,
          0, 0);

  _test_lookForRemovals(
          "five",
          "five^two^three^four^five",
          0, 1, 2, 1,
          -1,
          0, 1);

  _test_lookForRemovals(
          "1^d^a^test_starts_here^three",
          "v^test_starts_here^two^three",
          4, 2, 5, 1,
          1,
          4, 3);

  _test_lookForRemovals(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^eight",
          4, 2, 10, 1,
          -1,
          4, 2);

  _test_lookForRemovals(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^seven",
          4, 2, 2, 1,
          -1,
          4, 2);
}

void test_lookForReplaces1() {
  _test_lookForReplaces(
          "one^two",
          "eins^two",
          0, 0, 5, 1,
          1,
          1, 1);

  _test_lookForReplaces(
          "one^two^three",
          "eins^three",
          0, 0, 5, 1,
          2,
          2, 1);

  _test_lookForReplaces(
          "one^two^three^four^five",
          "eins^five",
          0, 0, 2, 1,
          -1,
          0, 0);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^one^three",
          "v^test_starts_here^two^three",
          4, 2, 5, 1,
          1,
          5, 3);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^eight",
          4, 2, 10, 1,
          -1,
          4, 2);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^seven",
          4, 2, 2, 1,
          -1,
          4, 2);

  _test_lookForReplaces(
          "one^two",
          "eins^two",
          0, 0, 5, 1,
          1,
          1, 1);

  _test_lookForReplaces(
          "one^three",
          "eins^zwei^three",
          0, 0, 5, 1,
          2,
          1, 2);

  _test_lookForReplaces(
          "one^five",
          "eins^zwei^drei^vier^five",
          0, 0, 2, 1,
          -1,
          0, 0);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^one^three",
          "v^test_starts_here^two^three",
          4, 2, 5, 1,
          1,
          5, 3);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^eight",
          4, 2, 10, 1,
          -1,
          4, 2);

  _test_lookForReplaces(
          "1^d^a^test_starts_here^two^three^four^five^six^seven^",
          "v^test_starts_here^^seven",
          4, 2, 2, 1,
          -1,
          4, 2);
}

void test_lookForReplaces2() {
  //some tests in which replace search order is important
  _test_lookForReplaces(
          "0^a^a^a^1^2^3^4^1^5",
          "0^b^b^b^4^1^5",
          3, 3, 5, 1,
          2,
          4, 5); // match token is "1"
  CU_ASSERT_FALSE(token_search_diff(
          "0^a^a^a^1^2^3^4^1^5",
          "0^b^b^b^4^1^5",
          0, 0, 0));
}

CU_TestInfo diff_testcases[] = {
  {"Testing token search:", test_token_search},
  {"Testing token diff functions, additions:", test_lookForAdditions},
  {"Testing token diff functions, removals:", test_lookForRemovals},
  {"Testing token diff functions, replaces:", test_lookForReplaces1},
  {"Testing token diff functions, replaces complex cases:", test_lookForReplaces2},
  {"Testing token diff functions, replaces correctly handles max diff: ", test_lookForReplacesNotOverflowing},
  {"Testing token diff functions, matchNTokens:", test_matchNTokens},
  {"Testing token search_diffs:", test_token_search_diffs},
  CU_TEST_INFO_NULL
};
