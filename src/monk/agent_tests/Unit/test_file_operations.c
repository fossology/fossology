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
}

void test_read_file_tokens_encodingConversion() {
  char* teststring = "a\n^bß c";
  char* testfile = "/tmp/monkftest";

  FILE* file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring);
  fclose(file);

  GArray* tokens;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens, "\n\t\r^ "));
  FO_ASSERT_EQUAL_FATAL(tokens->len, 3);

  char* teststring1 = "a\n^b\xdf\x0a c";
  file = fopen(testfile, "w");
  CU_ASSERT_PTR_NOT_NULL(file);
  fprintf(file, "%s", teststring1);
  fclose(file);

  GArray* tokens1;
  CU_ASSERT_TRUE_FATAL(readTokensFromFile(testfile, &tokens1, "\n\t\r^ "));

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


}

void test_guess_encoding() {
  char* buffer = "an ascii text";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  CU_ASSERT_STRING_EQUAL(guessedEncoding, "us-ascii");

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

void test_guess_encodingUtf8() {
  char* buffer = "an utf8 ß";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  FO_ASSERT_STRING_EQUAL(guessedEncoding, "utf-8");

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

void test_guess_encodingLatin1() {
  char* buffer = "a latin1 \xdf\x0a";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  FO_ASSERT_STRING_EQUAL(guessedEncoding, "iso-8859-1");

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

void test_guess_encodingMixedIsBinary() {
  char* buffer = "a latin1 \xdf\x0a and then an utf8 ß";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  FO_ASSERT_STRING_EQUAL(guessedEncoding, "unknown-8bit");

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

CU_TestInfo file_operations_testcases[] = {
  {"Testing reading file tokens:", test_read_file_tokens},
  {"Testing reading file tokens2:", test_read_file_tokens2},
  {"Testing reading file tokens with a binary file:", test_read_file_tokens_binaries},
  {"Testing reading file tokens with two different encodings return same token contents:", test_read_file_tokens_encodingConversion},
  {"Testing reading file tokens from wrong file:", test_read_file_tokens_error},
  {"Testing guessing encoding of buffer:", test_guess_encoding},
  {"Testing guessing encoding of buffer utf8:", test_guess_encodingUtf8},
  {"Testing guessing encoding of buffer Latin1:", test_guess_encodingLatin1},
  {"Testing guessing encoding of buffer with mixed encoding:", test_guess_encodingMixedIsBinary},
  CU_TEST_INFO_NULL
};
