/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdarg.h>
#include <libfocunit.h>

#include "license.h"
#include "hash.h"

extern fo_dbManager* dbManager;

void test_ignoreLicense_withGoodLicense() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("this is a real license.");
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_FALSE(isIgnoredLicense(&notIgnored));

  tokens_free(notIgnored.tokens);
  g_free(text_ptr);
}

void test_ignoreLicense_withGoodLicenseBranch2() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("Licence by Me."); //same token length as an ignored
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_FALSE(isIgnoredLicense(&notIgnored));

  tokens_free(notIgnored.tokens);
  g_free(text_ptr);
}

void test_ignoreLicense_withNomosLicense() {
  License notIgnored;
  notIgnored.refId = 0;
  notIgnored.shortname = "testLicense";
  char* text_ptr = g_strdup("License by Nomos.");
  notIgnored.tokens = tokenize(text_ptr, DELIMITERS);

  CU_ASSERT_TRUE(isIgnoredLicense(&notIgnored));

  tokens_free(notIgnored.tokens);
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

  tokens_free(notIgnored.tokens);
  g_free(text_ptr);
}

void _assertLicIds(const GArray* lics, unsigned int n, ...) {
  CU_ASSERT_PTR_NOT_NULL_FATAL(lics);
  CU_ASSERT_EQUAL_FATAL(lics->len, n);
  va_list args;

  va_start(args, n);

  for (int i=0; i<n; i++) {
    int expectedLicId = va_arg(args, int);
    CU_ASSERT_EQUAL(license_index(lics, i)->refId, expectedLicId);
  }

  va_end(args);
}

void _addLic(GArray* lics, int id, const char* text) {
  License toAdd = (License) {
    .refId = id,
    .tokens = tokenize(text, "^")
  };
  g_array_append_val(lics, toAdd);
}

void test_indexLicenses() {
  GArray* licenseArray = g_array_new(FALSE, FALSE, sizeof(License));

  GArray* textTokens = tokenize("a^b^c^d^e^f", "^");

  _addLic(licenseArray, 17, "b^c");
  _addLic(licenseArray, 18, "b^c^d^e^f");
  _addLic(licenseArray, 19, "1^b^c^d^e^f");
  _addLic(licenseArray, 20, "2^b^c^d^e^f");

  Licenses* indexedLicenses = buildLicenseIndexes(licenseArray, 4, 2);

  CU_ASSERT_EQUAL(licenseArray, indexedLicenses->licenses);

  _assertLicIds(getShortLicenseArray(indexedLicenses), 1, 17); // lic 17 is a short lic

  CU_ASSERT_PTR_NULL(getLicenseArrayFor(indexedLicenses, 0, textTokens, 0)); // no lic matches the first 4 tokens of text

  _assertLicIds(getLicenseArrayFor(indexedLicenses, 0, textTokens, 1), 1, 18); // lic 18 matches tokens 1-5 of text
  _assertLicIds(getLicenseArrayFor(indexedLicenses, 1, textTokens, 1), 2, 19, 20); // lic 19 and 20 matche tokens 1-5 of text with a 1 token head diff

  licenses_free(indexedLicenses);

  tokens_free(textTokens);
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
  FO_ASSERT_PTR_NOT_NULL_FATAL(dbManager);

  char* noLic = "No_license_found";

  PGresult* licensesResult = fo_dbManager_Exec_printf(dbManager,
          "select rf_pk, rf_shortname from license_ref where rf_shortname = '%s'",
          noLic);

  CU_ASSERT_PTR_NOT_NULL_FATAL(licensesResult);

  Licenses* licenses = extractLicenses(dbManager, licensesResult, 0 , 0);
  CU_ASSERT_EQUAL(licenses->licenses->len, 0);

  licenses_free(licenses);
  PQclear(licensesResult);
}

void test_extractLicenses_One() {
  FO_ASSERT_PTR_NOT_NULL_FATAL(dbManager);

  char* gpl3 = "GPL-3.0";

  PGresult* licensesResult = fo_dbManager_Exec_printf(dbManager,
          "select rf_pk, rf_shortname from license_ref where rf_shortname = '%s'",
          gpl3);

  CU_ASSERT_PTR_NOT_NULL_FATAL(licensesResult);

  Licenses* licenses = extractLicenses(dbManager, licensesResult, 0, 0);
  GArray* licenseArray = licenses->licenses;
  CU_ASSERT_EQUAL_FATAL(licenseArray->len, 1);

  License license = g_array_index(licenseArray, License, 0);
  CU_ASSERT_STRING_EQUAL(license.shortname, gpl3);

  assertTokens(license.tokens,
          "gnu", "general", "public", "license", "version", "3", NULL);

  licenses_free(licenses);
  PQclear(licensesResult);
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
  FO_ASSERT_PTR_NOT_NULL_FATAL(dbManager);

  char* gpl3 = "GPL-3.0";
  char* gpl2 = "GPL-2.0";

  PGresult* licensesResult = queryAllLicenses(dbManager);

  CU_ASSERT_PTR_NOT_NULL_FATAL(licensesResult);
  Licenses * licenses = extractLicenses(dbManager, licensesResult, 0, 0);
  PQclear(licensesResult);

  GArray* licenseArray = licenses->licenses;
  CU_ASSERT_EQUAL_FATAL(licenseArray->len, 2);

  sortLicenses(licenseArray);

  License license0 = g_array_index(licenseArray, License, 0);
  License license1 = g_array_index(licenseArray, License, 1);

  CU_ASSERT_STRING_EQUAL(license0.shortname, gpl3);
  CU_ASSERT_STRING_EQUAL(license1.shortname, gpl2);

  assertTokens(license0.tokens,
          "gnu", "general", "public", "license", "version", "3", NULL);
  assertTokens(license1.tokens,
          "gnu", "general", "public", "license", "version", "2", NULL);

  licenses_free(licenses);
}

#define doOrReturnError(fmt, ...) do {\
  PGresult* copy = fo_dbManager_Exec_printf(dbManager, fmt, #__VA_ARGS__); \
  if (!copy) {\
    return 1; \
  } else {\
    PQclear(copy);\
  }\
} while(0)

int license_setUpFunc() {
  if (!dbManager) {
    return 1;
  }

  if (!fo_dbManager_tableExists(dbManager, "license_ref")) {
    doOrReturnError("CREATE TABLE license_ref(rf_pk int, rf_shortname text, rf_text text, rf_active bool, rf_detector_type int)",);
  }

  doOrReturnError("INSERT INTO license_ref(rf_pk, rf_shortname, rf_text, rf_active ,rf_detector_type) "
                    "VALUES (1, 'GPL-3.0', 'gnu general public license version 3,', true, 1)",);
  doOrReturnError("INSERT INTO license_ref(rf_pk, rf_shortname, rf_text, rf_active ,rf_detector_type) "
                    "VALUES (2, 'GPL-2.0', 'gnu general public license, version 2', true, 1)",);

  return 0;
}

int license_tearDownFunc() {
  if (!dbManager) {
    return 1;
  }

  doOrReturnError("DROP TABLE license_ref",);

  return 0;
}

CU_TestInfo license_testcases[] = {
  {"Testing not ignoring good license:", test_ignoreLicense_withGoodLicense},
  {"Testing not ignoring good license2:", test_ignoreLicense_withGoodLicenseBranch2},
  {"Testing ignoring nomos license text:", test_ignoreLicense_withNomosLicense},
  {"Testing ignoring license text by Name:", test_ignoreLicense_withIgnoredName},
  {"Testing extracting a license from DB:", test_extractLicenses_One},
  {"Testing extracting two licenses from DB:", test_extractLicenses_Two},
  {"Testing extracting an ignored license from DB:", test_extractLicenses_Ignored},
  {"Testing indexing of licenses:", test_indexLicenses},
  CU_TEST_INFO_NULL
};
