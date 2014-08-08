/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
#include <stdlib.h>
#include <CUnit/CUnit.h>

#include "database.h"
#include "utils.h"
#include <limits.h>

void test_database() {
  PGconn * dbConnection = dbRealConnect();
  if (!dbConnection) {
    CU_FAIL("no db connection");
    return;
  }
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);

  printf("test: expecting a warning: ");
  char* probablyNotExistingText = getLicenseTextForLicenseRefId(dbManager, LONG_MAX);
  CU_ASSERT_STRING_EQUAL(probablyNotExistingText, "");

  fo_dbManager_free(dbManager);
  dbRealDisconnect(dbConnection);
}

CU_TestInfo database_testcases[] = {
  {"Testing database:", test_database},
  CU_TEST_INFO_NULL
};
