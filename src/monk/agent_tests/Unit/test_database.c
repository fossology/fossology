/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <CUnit/CUnit.h>
#include <libfocunit.h>

#include "database.h"

extern fo_dbManager* dbManager;

void test_queryAllLicenses() {
  PGresult* licenses = queryAllLicenses(dbManager);

  CU_ASSERT_PTR_NOT_NULL_FATAL(licenses);

  FO_ASSERT_EQUAL_FATAL(PQntuples(licenses), 2);

  PQclear(licenses);
}

void test_getTextFromId() {
  char* lic1Text = getLicenseTextForLicenseRefId(dbManager, 1);
  CU_ASSERT_STRING_EQUAL(lic1Text, "gnu general public license version 3,");
  g_free(lic1Text);
}

void test_getTextFromBadId() {
  printf("test: expecting a warning: \n--\n");
  char* notExistingText = getLicenseTextForLicenseRefId(dbManager, LONG_MAX);
  printf("\n--\n");
  CU_ASSERT_STRING_EQUAL(notExistingText, "");
  g_free(notExistingText);
}

#define doOrReturnError(fmt, ...) do {\
  PGresult* copy = fo_dbManager_Exec_printf(dbManager, fmt, #__VA_ARGS__); \
  if (!copy) {\
    return 1; \
  } else {\
    PQclear(copy);\
  }\
} while(0)

int database_setUpFunc() {
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

int database_tearDownFunc() {
  if (!dbManager) {
    return 1;
  }

  doOrReturnError("DROP TABLE license_ref",);

  return 0;
}

CU_TestInfo database_testcases[] = {
  {"Testing get lla licenses:", test_queryAllLicenses},
  {"Testing get text from id:", test_getTextFromId},
  {"Testing get text from bad id:", test_getTextFromBadId},
  CU_TEST_INFO_NULL
};
