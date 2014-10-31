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
#include <stdint.h>

#include "string_operations.h"
#include "hash.h"

void test_tokenize() {
  char* test = g_strdup("^foo^^ba^");

  GArray* token = tokenize(test, "^");

  CU_ASSERT_EQUAL(token->len, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).hashedContent, hash("foo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).removedBefore, 1);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).hashedContent, hash("ba"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).length, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).removedBefore, 2);

  g_array_free(token, TRUE);
  free(test);
}

void test_streamTokenize() {
  char* test = g_strdup("^foo^^ba");
  const char* delimiters = "^";

  GArray* token = tokens_new();

  Token* remainder = NULL;

  char* endPtr = test + strlen(test);

  int chunkSize = 2;
  char* ptr = test;
  while (*ptr && ptr <= endPtr) {
    unsigned int tokenCount = token->len;
    int thisChunkSize = MIN(chunkSize, endPtr - ptr);

    int addedTokens = streamTokenize(ptr, thisChunkSize, delimiters, &token, &remainder);

    CU_ASSERT_EQUAL(addedTokens, token->len - tokenCount);

    ptr += chunkSize;
  }
  if ((remainder) && (remainder->length > 0))
    g_array_append_val(token, *remainder);

  CU_ASSERT_EQUAL(token->len, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).hashedContent, hash("foo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).removedBefore, 1);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).hashedContent, hash("ba"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).length, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).removedBefore, 2);

  if (remainder)
    free(remainder);

  g_array_free(token, TRUE);
  free(test);
}

void test_streamTokenizeEventuallyGivesUp() {
  char* test = g_strdup("^foo^^ba");
  const char* delimiters = "^";

  GArray* token = tokens_new();

  Token* remainder = NULL;

  char* endPtr = test + strlen(test);

  printf("test: expecting a warning: ");
  int chunkSize = 5;
  char* ptr = test;
  int addedTokens = 0;
  uint32_t i = 0;
  while ((i < 1 << 27) && (*ptr) && (ptr <= endPtr)) {
    unsigned int tokenCount = token->len;
    int thisChunkSize = MIN(chunkSize, endPtr - ptr);

    addedTokens = streamTokenize(ptr, thisChunkSize, delimiters, &token, &remainder);

    if (addedTokens == -1) {
      break;
    } else
      if (addedTokens != token->len - tokenCount)
      CU_FAIL("wrong return value from streamTokenize()");

    i++;
  }

  CU_ASSERT_EQUAL(addedTokens, -1);

  CU_ASSERT_TRUE(token->len > 0);

  if (remainder)
    free(remainder);

  g_array_free(token, TRUE);
  free(test);
}

void assertTokenPosition(char* string, int count, ...) {
  char* test = g_strdup(string);

  GArray* tokens = tokenize(test, "^");

  CU_ASSERT_EQUAL(tokens->len, count);
  if (tokens->len == count) {

    va_list argptr;
    va_start(argptr, count);
    for (size_t i = 0; i < tokens->len; i++) {
      int expected = va_arg(argptr, int);
      size_t current = token_position_of(i, tokens);
      if (current != expected) {
        printf("ASSERT tokenizing '%s': posof(token[%ld]) == %ld != %d\n", string, i, current, expected);
        CU_FAIL("see output");
        break;
      }
      CU_ASSERT_EQUAL(current, token_position_of(i, tokens));
    }
    va_end(argptr);
  } else {
    printf("ASSERT tokenizing '%s': token count %d != %d\n", string, tokens->len, count);
  }

  g_array_free(tokens, TRUE);
  free(test);
}

void test_tokenPosition() {
  assertTokenPosition("foo", 1, 0);
  assertTokenPosition("^foo", 1, 1);
  assertTokenPosition("^foo^^bar", 2, 1, 6);
  assertTokenPosition("foo^^bar", 2, 0, 5);
  assertTokenPosition("^foo^^bar^^^^^baz", 3, 1, 6, 14);
}

void test_token_equal() {
  char* text = g_strdup("^foo^^bar^ba^barr");
  char* search = g_strdup("bar^^foo^");

  GArray* tokenizedText = tokenize(text, "^");
  GArray* tokenizedSearch = tokenize(search, "^");

  Token* t0 = &g_array_index(tokenizedText, Token, 0);
  Token* t1 = &g_array_index(tokenizedText, Token, 1);
  Token* t2 = &g_array_index(tokenizedText, Token, 2);
  Token* t3 = &g_array_index(tokenizedText, Token, 3);
  Token* s0 = &g_array_index(tokenizedSearch, Token, 0);
  Token* s1 = &g_array_index(tokenizedSearch, Token, 1);

  CU_ASSERT_TRUE(tokenEquals(t0, s1)); // foo == foo
  CU_ASSERT_TRUE(tokenEquals(t1, s0)); // bar == bar
  CU_ASSERT_FALSE(tokenEquals(t2, s0)); // ba != bar
  CU_ASSERT_FALSE(tokenEquals(t3, s0)); // barr != bar

  g_array_free(tokenizedText, TRUE);
  g_array_free(tokenizedSearch, TRUE);
  free(text);
  free(search);
}

CU_TestInfo string_operations_testcases[] = {
  {"Testing tokenize:", test_tokenize},
  {"Testing stream tokenize:", test_streamTokenize},
  {"Testing stream tokenize with too long stream:",test_streamTokenizeEventuallyGivesUp},
  {"Testing find token position in string:", test_tokenPosition},
  {"Testing token equals:", test_token_equal},
  CU_TEST_INFO_NULL
};
