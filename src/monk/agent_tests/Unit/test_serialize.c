/*
 Author: Maximilian Huber
 SPDX-FileCopyrightText: © 2018 TNG Technology Consulting GmbH

 SPDX-License-Identifier: GPL-2.0-only
*/

#define _GNU_SOURCE
#include <stdlib.h>
#include <stdio.h>
#include <libfocunit.h>

#include "serialize.h"
#include "monk.h"
#include "match.h"
#include "license.h"
#include "libfocunit.h"

Licenses* getNLicensesWithText2(int count, ...) {
  GArray* licenseArray = g_array_new(TRUE, FALSE, sizeof(License));
  va_list texts;
  va_start(texts, count);
  for (int i = 0; i < count; i++) {
    char* text = g_strdup(va_arg(texts, char*));
    License license;
    license.refId = i;
    license.shortname = g_strdup_printf("%d-testLic", i);
    license.tokens = tokenize(text, "^" );

    g_array_append_val(licenseArray, license);
    g_free(text);
  }
  va_end(texts);

  return buildLicenseIndexes(licenseArray, 1, 0);
}

Licenses* roundtrip(Licenses* licenses) {
  FILE *out, *in;
  size_t size;
  char *ptr;
  out = open_memstream(&ptr, &size);
  if (out == NULL){
    return NULL;
  }

  serialize(licenses, out);
  fclose(out);

  in = fmemopen(ptr, size, "r");
  Licenses* lics = deserialize(in, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);

  free(ptr);

  return lics;
}

void assert_Token(Token* token1, Token* token2) {
  CU_ASSERT_EQUAL(token1->length, token2->length);
  CU_ASSERT_EQUAL(token1->removedBefore, token2->removedBefore);
  CU_ASSERT_EQUAL(token1->hashedContent, token2->hashedContent);
}

void assert_License(License* lic1, License* lic2) {
  CU_ASSERT_EQUAL(lic1->refId, lic2->refId);
  CU_ASSERT_STRING_EQUAL(lic1->shortname, lic2->shortname);
  CU_ASSERT_EQUAL(lic1->tokens->len, lic2->tokens->len);

  for (guint i = 0; i < lic1->tokens->len; i++) {
    Token* tokenFrom1 = tokens_index(lic1->tokens, i);
    Token* tokenFrom2 = tokens_index(lic2->tokens, i);

    assert_Token(tokenFrom1, tokenFrom2);
  }
}

void assert_Licenses(Licenses* lics1, Licenses* lics2) {
  CU_ASSERT_EQUAL(lics1->licenses->len, lics2->licenses->len);

  for (guint i = 0; i < lics1->licenses->len; i++) {
    License* licFrom1 = license_index(lics1->licenses, i);
    License* licFrom2 = license_index(lics2->licenses, i);

    assert_License(licFrom1, licFrom2);
  }
}

void test_roundtrip_one() {
  Licenses* licenses = getNLicensesWithText2(1,"a b cde f");
  Licenses* returnedLicenses = roundtrip(licenses);

  assert_Licenses(licenses, returnedLicenses);
}

void test_roundtrip() {
  Licenses* licenses = getNLicensesWithText2(6, "a^b", "a^b^c^d", "d", "e", "f", "e^f^g");
  Licenses* returnedLicenses = roundtrip(licenses);

  assert_Licenses(licenses, returnedLicenses);
}

CU_TestInfo serialize_testcases[] = {
  {"Test roundtrip with empty:", test_roundtrip_one},
  {"Test roundtrip with some licenses with tokens:", test_roundtrip},
  CU_TEST_INFO_NULL
};
