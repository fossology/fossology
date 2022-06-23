/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>
#include <stdint.h>

#include "string_operations.h"
#include "hash.h"
#include "monk.h"

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
  g_free(test);
}

void test_tokenizeWithSpecialDelims() {
  char* test = g_strdup("/*foo \n * bar \n *baz*/ ***booo \n:: qoo \ndnl zit ");

  GArray* token = tokenize(test, " \n");
  CU_ASSERT_EQUAL(token->len, 6);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).hashedContent, hash("foo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).removedBefore, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).hashedContent, hash("bar"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).removedBefore, 5);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).hashedContent, hash("baz"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).removedBefore, 4);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 3).hashedContent, hash("booo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 3).length, 4);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 3).removedBefore, 6);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 4).hashedContent, hash("qoo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 4).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 4).removedBefore, 5);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 5).hashedContent, hash("zit"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 5).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 5).removedBefore, 6);
  g_array_free(token, TRUE);
  g_free(test);
}

void test_streamTokenize() {
  char* test = g_strdup("^foo^^ba^REM^boooREM^REM^");
  const char* delimiters = "^";

  GArray* token = tokens_new();

  Token* remainder = NULL;

  size_t len = strlen(test);

  int chunkSize = 2;
  char* ptr = test;
  size_t rea = 0;
  while (rea < len) {
    unsigned int tokenCount = token->len;
    int thisChunkSize = MIN(chunkSize, len - rea);

    int addedTokens = streamTokenize(ptr, thisChunkSize, delimiters, &token, &remainder);

    CU_ASSERT_EQUAL(addedTokens, token->len - tokenCount);

    ptr += chunkSize;
    rea += chunkSize;
  }
  streamTokenize(NULL, 0, NULL, &token, &remainder);

  CU_ASSERT_EQUAL_FATAL(token->len, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).hashedContent, hash("foo"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).length, 3);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 0).removedBefore, 1);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).hashedContent, hash("ba"));
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).length, 2);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 1).removedBefore, 2);
#ifndef MONK_CASE_INSENSITIVE
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).hashedContent, hash("boooREM"));
#else
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).hashedContent, hash("booorem"));
#endif
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).length, 7);
  CU_ASSERT_EQUAL(g_array_index(token, Token, 2).removedBefore, 5);

  CU_ASSERT_PTR_NULL(remainder);

  CU_ASSERT_EQUAL(token_position_of(3, token), 20);

  tokens_free(token);
  g_free(test);
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
  streamTokenize(NULL, 0, NULL, &token, &remainder);

  CU_ASSERT_EQUAL(addedTokens, -1);

  CU_ASSERT_TRUE(token->len > 0);

  g_array_free(token, TRUE);
  g_free(test);
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
  g_free(test);
}

void test_tokenPosition() {
  assertTokenPosition("foo", 1, 0);
  assertTokenPosition("^foo", 1, 1);
  assertTokenPosition("^foo^^bar", 2, 1, 6);
  assertTokenPosition("foo^^bar", 2, 0, 5);
  assertTokenPosition("^foo^^bar^^^^^baz", 3, 1, 6, 14);
}

void test_tokenPositionAtEnd() {
  char* test = g_strdup("^^23^5^7");
  GArray* tokens = tokenize(test, "^");

  CU_ASSERT_EQUAL(token_position_of(0, tokens), 2);
  CU_ASSERT_EQUAL(token_position_of(1, tokens), 5);
  CU_ASSERT_EQUAL(token_position_of(2, tokens), 7);
  CU_ASSERT_EQUAL(token_position_of(3, tokens), 8);

  g_array_free(tokens, TRUE);
  g_free(test);
}

void test_token_equal() {
  char* text = g_strdup("^foo^^bar^ba^barr");
  char* search = g_strdup("bar^^foo^");

  GArray* tokenizedText = tokenize(text, "^");
  GArray* tokenizedSearch = tokenize(search, "^");

  Token* t0 = tokens_index(tokenizedText, 0);
  Token* t1 = tokens_index(tokenizedText, 1);
  Token* t2 = tokens_index(tokenizedText, 2);
  Token* t3 = tokens_index(tokenizedText, 3);
  Token* s0 = tokens_index(tokenizedSearch, 0);
  Token* s1 = tokens_index(tokenizedSearch, 1);

  CU_ASSERT_TRUE(tokenEquals(t0, s1)); // foo == foo
  CU_ASSERT_TRUE(tokenEquals(t1, s0)); // bar == bar
  CU_ASSERT_FALSE(tokenEquals(t2, s0)); // ba != bar
  CU_ASSERT_FALSE(tokenEquals(t3, s0)); // barr != bar

  g_array_free(tokenizedText, TRUE);
  g_array_free(tokenizedSearch, TRUE);
  g_free(text);
  g_free(search);
}

CU_TestInfo string_operations_testcases[] = {
  {"Testing tokenize:", test_tokenize},
  {"Testing tokenize with special delimiters:", test_tokenizeWithSpecialDelims},
  {"Testing stream tokenize:", test_streamTokenize},
  {"Testing stream tokenize with too long stream:",test_streamTokenizeEventuallyGivesUp},
  {"Testing find token position in string:", test_tokenPosition},
  {"Testing find token position at end:", test_tokenPositionAtEnd},
  {"Testing token equals:", test_token_equal},
  CU_TEST_INFO_NULL
};
