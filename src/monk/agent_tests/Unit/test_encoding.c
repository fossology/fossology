/*
Author: Daniele Fognini
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
