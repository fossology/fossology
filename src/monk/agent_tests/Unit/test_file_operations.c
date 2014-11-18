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

#include "file_operations.h"
#include "string_operations.h"
#include "hash.h"

void test_read_file() {
  char* teststring = "vduinvdf\nfgbfg\n\t\rvfvfdß";
  char* testfile = "/tmp/monkftest";
  
  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  CU_ASSERT_STRING_EQUAL(teststring, readFile(testfile));
}

void test_read_mangling_binaries() {
  char teststring[] = "\0afdß";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fwrite(teststring, 1, sizeof (teststring), file);
  fclose(file);

  CU_ASSERT_STRING_EQUAL(" afdß ", readFile(testfile));
}

void test_read_file_tokens() {
  char* teststring = "a\n^b\n c";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  GArray* tokens;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));
  
  CU_ASSERT_EQUAL_FATAL(tokens->len, 3);
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
}

void test_read_file_tokens2() {
  char* teststring = " * a\n *\n * b";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  GArray* tokens;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));
  
  CU_ASSERT_EQUAL_FATAL(tokens->len, 2);
  Token token0 = g_array_index(tokens, Token, 0);
  Token token1 = g_array_index(tokens, Token, 1);
  CU_ASSERT_EQUAL(token0.hashedContent, hash("a"));
  CU_ASSERT_EQUAL(token1.hashedContent, hash("b"));
  CU_ASSERT_EQUAL(token0.length, 1);
  CU_ASSERT_EQUAL(token1.length, 1);
  CU_ASSERT_EQUAL(token0.removedBefore, 3);
  CU_ASSERT_EQUAL(token1.removedBefore, 7);
}

void test_read_file_tokens_error() {
  GArray* tokens;
  CU_ASSERT_FALSE(readTokensFromFile("not a file", &tokens, "\n\t\r^ "));
  CU_ASSERT_EQUAL(tokens->len, 0);
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

  CU_ASSERT_EQUAL_FATAL(tokens->len, 3);
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
}

CU_TestInfo file_operations_testcases[] = {
  {"Testing reading file:", test_read_file},
  {"Testing reading file tokens:", test_read_file_tokens},
  {"Testing reading file tokens2:", test_read_file_tokens2},
  {"Testing reading file tokens with a binary file:", test_read_file_tokens_binaries},
  {"Testing reading file tokens from wrong file:", test_read_file_tokens_error},
  {"Testing reading binary file and mangle it:", test_read_mangling_binaries},
  CU_TEST_INFO_NULL
};
