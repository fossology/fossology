/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <string_operations.h>

#include "encoding.h"
#include "hash.h"
#include "libfocunit.h"


void test_guess_encoding() {
  char* buffer = "an ascii text";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

#ifdef HAVE_CHARDET
  CU_ASSERT_PTR_NULL(guessedEncoding);
#else
  CU_ASSERT_PTR_NOT_NULL_FATAL(guessedEncoding);
  FO_ASSERT_STRING_EQUAL(guessedEncoding, "us-ascii");
#endif

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

void test_guess_encodingUtf8() {
  char* buffer = "an utf8 ß";
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  CU_ASSERT_PTR_NOT_NULL_FATAL(guessedEncoding);

#ifdef HAVE_CHARDET
  FO_ASSERT_STRING_EQUAL(guessedEncoding, "UTF-8");
#else
  FO_ASSERT_STRING_EQUAL(guessedEncoding, "utf-8");
#endif

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

void test_guess_encodingLatin1() {
  char* buffer = "a latin1 \xdf\x0a"; // ß
  gchar* guessedEncoding = guessEncoding(buffer, strlen(buffer));

  CU_ASSERT_PTR_NOT_NULL_FATAL(guessedEncoding);

#ifdef HAVE_CHARDET
  FO_ASSERT_STRING_EQUAL(guessedEncoding, "windows-1252");
#else
  FO_ASSERT_STRING_EQUAL(guessedEncoding, "iso-8859-1");
#endif

  if (guessedEncoding) {
    g_free(guessedEncoding);
  }
}

CU_TestInfo encoding_testcases[] = {
  {"Testing guessing encoding of buffer:", test_guess_encoding},
  {"Testing guessing encoding of buffer utf8:", test_guess_encodingUtf8},
  {"Testing guessing encoding of buffer Latin1:", test_guess_encodingLatin1},
  CU_TEST_INFO_NULL
};
