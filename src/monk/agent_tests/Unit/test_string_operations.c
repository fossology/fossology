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

  size_t chunkSize = 2;
  char* ptr = test;
  size_t rea = 0;
  while (rea < len) {
    unsigned int tokenCount = token->len;
    size_t thisChunkSize = MIN(chunkSize, len - rea);

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
  guint addedTokens = 0;
  uint32_t i = 0;
  while ((i < 1 << 27) && (*ptr) && (ptr <= endPtr)) {
    unsigned int tokenCount = token->len;
    int thisChunkSize = MIN(chunkSize, endPtr - ptr);

    addedTokens = streamTokenize(ptr, thisChunkSize, delimiters, &token, &remainder);

    if (addedTokens == (guint)-1) {
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

void assertTokenPosition(char* string, guint count, ...) {
  char* test = g_strdup(string);

  GArray* tokens = tokenize(test, "^");

  CU_ASSERT_EQUAL(tokens->len, count);
  if (tokens->len == count) {

    va_list argptr;
    va_start(argptr, count);
    for (size_t i = 0; i < tokens->len; i++) {
      size_t expected = va_arg(argptr, size_t);
      size_t current = token_position_of(i, tokens);
      if (current != expected) {
        printf("ASSERT tokenizing '%s': posof(token[%ld]) == %ld != %ld\n", string, i, current, expected);
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

/* -------------------------------------------------------------------------
 * Tests for TOKEN_YEAR classification and year-sequence merging
 * ---------------------------------------------------------------------- */

/** Helper: tokenize a string and return token at index i */
static Token getToken(const char* str, const char* delim, guint i)
{
  GArray* tokens = tokenize(str, delim);
  CU_ASSERT_FATAL(tokens->len > i);
  Token t = g_array_index(tokens, Token, i);
  g_array_free(tokens, TRUE);
  return t;
}

void test_yearTokenClassification()
{
  /* Only 4-digit numbers (and 4-digit-led ranges) are years */
  Token t;

  t = getToken("2024", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_YEAR);
  CU_ASSERT_EQUAL(t.hashedContent, YEAR_CANONICAL_HASH);
  CU_ASSERT_EQUAL(t.length, 4);

  t = getToken("1998", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_YEAR);

  /* Short digit runs are NOT years (section/list/version numbers) */
  t = getToken("5", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  t = getToken("34", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  t = getToken("10", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);
  CU_ASSERT_NOT_EQUAL(t.hashedContent, YEAR_CANONICAL_HASH);

  t = getToken("100", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  /* Long digit runs (>4) are NOT years */
  t = getToken("20000", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  t = getToken("12345", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  /* Year range with hyphen -> TOKEN_YEAR */
  t = getToken("2000-2009", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_YEAR);
  CU_ASSERT_EQUAL(t.hashedContent, YEAR_CANONICAL_HASH);
  CU_ASSERT_EQUAL(t.length, 9);

  t = getToken("2024-23", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_YEAR);

  /* Non-year ranges (leading part is not a 4-digit year) -> TOKEN_NORMAL */
  t = getToken("10-99", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);
  CU_ASSERT_NOT_EQUAL(t.hashedContent, YEAR_CANONICAL_HASH);

  t = getToken("1-2", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  /* Mixed letter+digit -> TOKEN_NORMAL */
  t = getToken("v2", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);
  CU_ASSERT_NOT_EQUAL(t.hashedContent, YEAR_CANONICAL_HASH);

  t = getToken("GPL-2.0", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);

  /* Trailing hyphen only -> TOKEN_NORMAL (no digit after hyphen) */
  t = getToken("2000-", " ", 0);
  CU_ASSERT_EQUAL(t.tokenType, TOKEN_NORMAL);
}

void test_punctTokenClassification()
{
  GArray* tokens;

  /* Standalone all-punctuation tokens must be invisible (skipped) */
  tokens = tokenize("foo @ bar", " ");
  CU_ASSERT_EQUAL(tokens->len, 2); /* '@' eaten, only foo and bar */
  g_array_free(tokens, TRUE);

  tokens = tokenize("foo $& bar", " ");
  CU_ASSERT_EQUAL(tokens->len, 2); /* '$&' eaten */
  g_array_free(tokens, TRUE);

  tokens = tokenize("foo --- bar", " ");
  CU_ASSERT_EQUAL(tokens->len, 2); /* '---' eaten */
  g_array_free(tokens, TRUE);

  /* Mixed alnum+punct -> TOKEN_NORMAL, kept */
  tokens = tokenize("(c)", " ");
  CU_ASSERT_EQUAL(tokens->len, 1);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).tokenType, TOKEN_NORMAL);
  g_array_free(tokens, TRUE);

  /* Non-ASCII (UTF-8 multibyte) words are real content, NOT punctuation.
     "\xc3\xa9" = e-acute, "\xc3\x9f" = sharp-s. They must be kept. */
  tokens = tokenize("foo \xc3\xa9 bar", " ");
  CU_ASSERT_EQUAL(tokens->len, 3);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 1).tokenType, TOKEN_NORMAL);
  g_array_free(tokens, TRUE);

  tokens = tokenize("\xc3\x9f", " ");
  CU_ASSERT_EQUAL(tokens->len, 1);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).tokenType, TOKEN_NORMAL);
  g_array_free(tokens, TRUE);
}

void test_yearTokensEqual()
{
  /* Two year tokens of different content must match each other */
  GArray* a = tokenize("2000-2009", " ");
  GArray* b = tokenize("2024", " ");

  CU_ASSERT_EQUAL(a->len, 1);
  CU_ASSERT_EQUAL(b->len, 1);

  Token* ta = tokens_index(a, 0);
  Token* tb = tokens_index(b, 0);

  CU_ASSERT_EQUAL(ta->tokenType, TOKEN_YEAR);
  CU_ASSERT_EQUAL(tb->tokenType, TOKEN_YEAR);
  CU_ASSERT_TRUE(tokenEquals(ta, tb));

  /* Year token must NOT match a plain word */
  GArray* c = tokenize("Copyright", " ");
  Token* tc = tokens_index(c, 0);
  CU_ASSERT_EQUAL(tc->tokenType, TOKEN_NORMAL);
  CU_ASSERT_FALSE(tokenEquals(ta, tc));

  g_array_free(a, TRUE);
  g_array_free(b, TRUE);
  g_array_free(c, TRUE);
}

void test_mergeYearTokenSequences()
{
  GArray* tokens;

  /* Comma-separated years (comma is a delimiter) become multiple TOKEN_YEAR;
   * after merge: one TOKEN_YEAR whose length spans the whole group */
  tokens = mergeYearTokenSequences(tokenize("2000,2001,2002", ","));
  CU_ASSERT_EQUAL(tokens->len, 1);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).tokenType, TOKEN_YEAR);
  /* length = 4+1+4+1+4 = 14 (each year=4, each comma=1 absorbed) */
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).length, 14);
  g_array_free(tokens, TRUE);

  /* Non-year tokens are kept intact; year group between them is merged */
  tokens = mergeYearTokenSequences(tokenize("Copyright^2000^2001^2002^Inc", "^"));
  CU_ASSERT_EQUAL(tokens->len, 3); /* Copyright | YEAR_SEQ | Inc */
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).tokenType, TOKEN_NORMAL);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 1).tokenType, TOKEN_YEAR);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 2).tokenType, TOKEN_NORMAL);
  g_array_free(tokens, TRUE);

  /* A single year range token (no merging needed) passes through unchanged */
  tokens = mergeYearTokenSequences(tokenize("2000-2009", " "));
  CU_ASSERT_EQUAL(tokens->len, 1);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).tokenType, TOKEN_YEAR);
  CU_ASSERT_EQUAL(g_array_index(tokens, Token, 0).length, 9);
  g_array_free(tokens, TRUE);

  /* Empty input -> empty output */
  tokens = mergeYearTokenSequences(tokens_new());
  CU_ASSERT_EQUAL(tokens->len, 0);
  g_array_free(tokens, TRUE);
}

void test_yearMergeProducesFullMatch()
{
  /* Simulate the core scenario from issue #647:
   * reference "Copyright 2000-2009 Foo" (1 year token)
   * file "Copyright 2000,2001,...,2009 Foo" (many year tokens, 1 after merge)
   * After merging both sides, tokenEquals on the year tokens must be true
   * and the token counts must match, enabling MATCH_TYPE_FULL. */
  GArray* ref  = mergeYearTokenSequences(tokenize("Copyright^2000-2009^Foo", "^"));
  GArray* file = mergeYearTokenSequences(tokenize("Copyright^2000^2001^2002^2003^2004^2005^2006^2007^2008^2009^Foo", "^"));

  CU_ASSERT_EQUAL(ref->len, 3); /* Copyright | YEAR | Foo */
  CU_ASSERT_EQUAL(file->len, 3); /* same count after merge */

  /* All three token positions must compare equal */
  for (guint i = 0; i < 3; i++) {
    Token* tr = tokens_index(ref, i);
    Token* tf = tokens_index(file, i);
    CU_ASSERT_TRUE_FATAL(tokenEquals(tr, tf));
  }

  g_array_free(ref,  TRUE);
  g_array_free(file, TRUE);
}

CU_TestInfo string_operations_testcases[] = {
  {"Testing tokenize:", test_tokenize},
  {"Testing tokenize with special delimiters:", test_tokenizeWithSpecialDelims},
  {"Testing stream tokenize:", test_streamTokenize},
  {"Testing stream tokenize with too long stream:",test_streamTokenizeEventuallyGivesUp},
  {"Testing find token position in string:", test_tokenPosition},
  {"Testing find token position at end:", test_tokenPositionAtEnd},
  {"Testing token equals:", test_token_equal},
  {"Testing year token classification:", test_yearTokenClassification},
  {"Testing punct token classification:", test_punctTokenClassification},
  {"Testing year tokens equal each other:", test_yearTokensEqual},
  {"Testing mergeYearTokenSequences:", test_mergeYearTokenSequences},
  {"Testing year merge enables full match:", test_yearMergeProducesFullMatch},
  CU_TEST_INFO_NULL
};
