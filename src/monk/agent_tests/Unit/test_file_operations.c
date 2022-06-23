/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <string_operations.h>

#include "file_operations.h"
#include "string_operations.h"
#include "hash.h"
#include "libfocunit.h"

void test_read_file_tokens() {
  char* teststring = "a\n^b\n c";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  GArray* tokens;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));

  FO_ASSERT_EQUAL_FATAL(tokens->len, 3);
  Token token0 = g_array_index(tokens, Token, 0);
  Token token1 = g_array_index(tokens, Token, 1);
  Token token2 = g_array_index(tokens, Token, 2);
  CU_ASSERT_EQUAL(token0.length, 1);
  CU_ASSERT_EQUAL(token1.length, 1);
  CU_ASSERT_EQUAL(token2.length, 1);
  CU_ASSERT_EQUAL(token0.removedBefore, 0);
  CU_ASSERT_EQUAL(token1.removedBefore, 2);
  CU_ASSERT_EQUAL(token2.removedBefore, 2);
  CU_ASSERT_EQUAL(token0.hashedContent, hash("a"));
  CU_ASSERT_EQUAL(token1.hashedContent, hash("b"));
  CU_ASSERT_EQUAL(token2.hashedContent, hash("c"));

  g_array_free(tokens, TRUE);
}

void test_read_file_tokens2() {
  char* teststring = " * a\n *\n * b";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  GArray* tokens;
  FO_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));

  FO_ASSERT_EQUAL_FATAL(tokens->len, 2);
  Token token0 = g_array_index(tokens, Token, 0);
  Token token1 = g_array_index(tokens, Token, 1);
  CU_ASSERT_EQUAL(token0.hashedContent, hash("a"));
  CU_ASSERT_EQUAL(token1.hashedContent, hash("b"));
  CU_ASSERT_EQUAL(token0.length, 1);
  CU_ASSERT_EQUAL(token1.length, 1);
  CU_ASSERT_EQUAL(token0.removedBefore, 3);
  CU_ASSERT_EQUAL(token1.removedBefore, 7);

  g_array_free(tokens, TRUE);
}

void test_read_file_tokens_error() {
  GArray* tokens = (GArray*)0x17;
  CU_ASSERT_FALSE(readTokensFromFile("not a file", &tokens, "\n\t\r^ "));
  CU_ASSERT_EQUAL((GArray*)0x17, tokens);
}

void test_read_file_tokens_binaries() {
  char teststring[] = "a\n^b\0 c";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fwrite(teststring, 1, sizeof (teststring), file);
  fclose(file);

  GArray* tokens;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));

  FO_ASSERT_EQUAL_FATAL(tokens->len, 3);
  Token token0 = g_array_index(tokens, Token, 0);
  Token token1 = g_array_index(tokens, Token, 1);
  Token token2 = g_array_index(tokens, Token, 2);
  CU_ASSERT_EQUAL(token0.length, 1);
  CU_ASSERT_EQUAL(token1.length, 1);
  CU_ASSERT_EQUAL(token2.length, 1);
  CU_ASSERT_EQUAL(token0.removedBefore, 0);
  CU_ASSERT_EQUAL(token1.removedBefore, 2);
  CU_ASSERT_EQUAL(token2.removedBefore, 2);
  CU_ASSERT_EQUAL(token0.hashedContent, hash("a"));
  CU_ASSERT_EQUAL(token1.hashedContent, hash("b"));
  CU_ASSERT_EQUAL(token2.hashedContent, hash("c"));

  g_array_free(tokens, TRUE);
}

void binaryWrite(const char* testfile, const char* teststring)
{
  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fwrite(teststring, 1, strlen(teststring), file);
  fclose(file);
}

void test_read_file_tokens_encodingConversion() {
  char* testfile = "/tmp/monkftest";

  GArray* tokens;
  binaryWrite(testfile, "a\n ß é c");
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));

  GArray* tokens1;
  binaryWrite(testfile, "a\n \xdf\x0a \xe9\x0a c");
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens1, "\n\t\r^ "));

  FO_ASSERT_FATAL(tokens->len > 0);

  FO_ASSERT_EQUAL_FATAL(tokens->len, tokens1->len);

  CU_ASSERT_EQUAL(
    g_array_index(tokens, Token, 0).hashedContent,
    g_array_index(tokens1, Token, 0).hashedContent
  );
  CU_ASSERT_EQUAL(
    g_array_index(tokens, Token, 1).hashedContent,
    g_array_index(tokens1, Token, 1).hashedContent
  );
  CU_ASSERT_EQUAL(
    g_array_index(tokens, Token, 2).hashedContent,
    g_array_index(tokens1, Token, 2).hashedContent
  );

  g_array_free(tokens, TRUE);
  g_array_free(tokens1, TRUE);

}

CU_TestInfo file_operations_testcases[] = {
  {"Testing reading file tokens:", test_read_file_tokens},
  {"Testing reading file tokens2:", test_read_file_tokens2},
  {"Testing reading file tokens with a binary file:", test_read_file_tokens_binaries},
  {"Testing reading file tokens with two different encodings return same token contents:", test_read_file_tokens_encodingConversion},
  {"Testing reading file tokens from wrong file:", test_read_file_tokens_error},
  CU_TEST_INFO_NULL
};
