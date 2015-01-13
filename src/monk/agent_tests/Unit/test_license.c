/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <stdlib.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>

#include "license.h"
#include "utils.h"
#include "hash.h"

void test_ignoreLicense_withGoodLicense() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("this is a real license.");
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_FALSE(isIgnoredLicense(&notIgnored));

  g_array_free(notIgnored.tokens, TRUE);
  g_free(text_ptr);
}

void test_ignoreLicense_withGoodLicenseBranch2() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("Licence by Me."); //same token length as an ignored
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_FALSE(isIgnoredLicense(&notIgnored));

  g_array_free(notIgnored.tokens, TRUE);
  free(text_ptr);
}

void test_ignoreLicense_withNomosLicense() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("License by Nomos.");
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_TRUE(isIgnoredLicense(&notIgnored));

  g_array_free(notIgnored.tokens, TRUE);
  g_free(text_ptr);
}

void test_ignoreLicense_withIgnoredName() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "Void";
  char* text_ptr = g_strdup("a good license text");
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_TRUE(isIgnoredLicense(&notIgnored));

  notIgnored.shortname = "No_license_found";

  CU_ASSERT_TRUE(isIgnoredLicense(&notIgnored));

  g_array_free(notIgnored.tokens, TRUE);
  g_free(text_ptr);
}

void assertTokens(GArray* tokens, ...) {
  va_list expptr;
  va_start(expptr, tokens);

  char* expected = va_arg(expptr, char*);
  size_t i = 0;
  while (expected != NULL) {
    if (i >= tokens->len) {
      printf("ASSERT ERROR: tokens array has length %d, which is shorter that expected", tokens->len);
      CU_FAIL("tokens array shorter than expected");
      break;
    }

    Token token = g_array_index(tokens, Token, i);
    CU_ASSERT_EQUAL(token.hashedContent, hash(expected));
    if (token.hashedContent != hash(expected)) {
      printf("%u != hash(%s)\n", token.hashedContent, expected);
    }
    expected = va_arg(expptr, char*);
    i++;
  }

  va_end(expptr);
}

void test_extractLicenses_Ignored() {
  PGconn* dbConnection = dbRealConnect();
  if (!dbConnection) {
    CU_FAIL("no db connection");
    return;
  }
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);

  char* noLic = "No_license_found";

  PGresult* licensesResult = fo_dbManager_Exec_printf(dbManager,
          "select rf_pk, rf_shortname from license_ref where rf_shortname = '%s'",
          noLic);

  CU_ASSERT_PTR_NOT_NULL(licensesResult);

  Licenses* licenses = extractLicenses(dbManager, licensesResult, 0 , 0);
  CU_ASSERT_EQUAL(licenses->licenses->len, 0);

  licenses_free(licenses);
  PQclear(licensesResult);

  fo_dbManager_free(dbManager);
  dbRealDisconnect(dbConnection);
}

void test_extractLicenses_One() {
  PGconn* dbConnection = dbRealConnect();
  if (!dbConnection) {
    CU_FAIL("no db connection");
    return;
  }
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);

  char* gpl3 = "GPL-3.0";

  PGresult* licensesResult = fo_dbManager_Exec_printf(dbManager,
          "select rf_pk, rf_shortname from license_ref where rf_shortname = '%s'",
          gpl3);

  CU_ASSERT_PTR_NOT_NULL(licensesResult);

  Licenses* licenses = extractLicenses(dbManager, licensesResult, 0, 0);
  GArray* licenseArray = licenses->licenses;
  CU_ASSERT_EQUAL(licenseArray->len, 1);

  License license = g_array_index(licenseArray, License, 0);
  CU_ASSERT_STRING_EQUAL(license.shortname, gpl3);

  assertTokens(license.tokens,
          "gnu", "general", "public", "license", "version", "3,", NULL);

  licenses_free(licenses);
  PQclear(licensesResult);

  fo_dbManager_free(dbManager);
  dbRealDisconnect(dbConnection);
}

static gint lengthInverseComparator(const void* a, const void* b) {
  size_t aLen = ((License*) a)->tokens->len;
  size_t bLen = ((License*) b)->tokens->len;

  return (aLen < bLen) - (aLen > bLen);
}

void sortLicenses(GArray* licenses) {
    g_array_sort(licenses, lengthInverseComparator);
}

void test_extractLicenses_Two() {
  PGconn* dbConnection = dbRealConnect();
  if (!dbConnection) {
    CU_FAIL("no db connection");
    return;
  }
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);

  char* gpl3 = "GPL-3.0";
  char* gpl2 = "GPL-2.0";

  PGresult* licensesResult = fo_dbManager_Exec_printf(dbManager,
          "select rf_pk, rf_shortname from license_ref "
          "where rf_shortname = '%s' or rf_shortname = '%s'",
          gpl3, gpl2);

  CU_ASSERT_PTR_NOT_NULL(licensesResult);

  Licenses * licenses = extractLicenses(dbManager, licensesResult, 0, 0);
  GArray* licenseArray = licenses->licenses;
  CU_ASSERT_EQUAL(licenseArray->len, 2);

  sortLicenses(licenseArray);

  License license0 = g_array_index(licenseArray, License, 0);
  License license1 = g_array_index(licenseArray, License, 1);

  CU_ASSERT_TRUE(license0.tokens->len > license1.tokens->len);

  CU_ASSERT_STRING_EQUAL(license0.shortname, gpl3);
  CU_ASSERT_STRING_EQUAL(license1.shortname, gpl2);

  assertTokens(license0.tokens,
          "gnu", "general", "public", "license", "version", "3,", NULL);
  assertTokens(license1.tokens,
          "gnu", "general", "public", "license,", "version", "2", NULL);

  licenses_free(licenses);
  PQclear(licensesResult);

  fo_dbManager_free(dbManager);
  dbRealDisconnect(dbConnection);
}

CU_TestInfo license_testcases[] = {
  {"Testing not ignoring good license:", test_ignoreLicense_withGoodLicense},
  {"Testing not ignoring good license2:", test_ignoreLicense_withGoodLicenseBranch2},
  {"Testing ignoring nomos license text:", test_ignoreLicense_withNomosLicense},
  {"Testing ignoring license text by Name:", test_ignoreLicense_withIgnoredName},
  {"Testing extracting a license from DB:", test_extractLicenses_One},
  {"Testing extracting two licenses from DB:", test_extractLicenses_Two},
  {"Testing extracting an ignored license from DB:", test_extractLicenses_Ignored},
  CU_TEST_INFO_NULL
};
