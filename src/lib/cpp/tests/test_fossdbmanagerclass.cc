/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <iostream>

extern "C" {
#include <libfodbreposysconf.h>
}

#include "libfossdbmanagerclass.hpp"

class FoLibCPPTest : public CPPUNIT_NS::TestFixture {
CPPUNIT_TEST_SUITE(FoLibCPPTest);
    CPPUNIT_TEST(test_runSimpleQuery);
    CPPUNIT_TEST(test_runCommandQueryCheckIfSuccess);
    CPPUNIT_TEST(test_runBadCommandQueryCheckIfError);
  CPPUNIT_TEST_SUITE_END();
private:
  fo::DbManager* dbManager;

public:
  void setUp() {
    dbManager = new fo::DbManager(createTestEnvironment("", NULL, 0));
  }

  void tearDown() {
    delete dbManager;
    // dbManager connection is already closed by destructor
    dropTestEnvironment(NULL, "");
  }

  void test_runCommandQueryCheckIfSuccess() {
    fo::DbManager manager = dbManager->spawn();
    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl(%s integer)", "col");

    CPPUNIT_ASSERT(result);
  }

  void test_runSimpleQuery() {
    fo::DbManager manager = dbManager->spawn();

    int val = 17;
    {
      CPPUNIT_ASSERT(manager.queryPrintf("CREATE TABLE tbl(col integer)"));
      CPPUNIT_ASSERT(manager.queryPrintf("INSERT INTO tbl(col) VALUES (%d)", val));
    }

    fo::QueryResult result = manager.queryPrintf("SELECT * FROM tbl");

    CPPUNIT_ASSERT(result);

    CPPUNIT_ASSERT_EQUAL(1, result.getRowCount());

    std::vector<std::string> row = result.getRow(0);

    CPPUNIT_ASSERT_EQUAL(1, (int) row.size());
    CPPUNIT_ASSERT_EQUAL(std::string("17"), row[0]);

    std::vector<int> results = result.getSimpleResults(0, atoi);

    CPPUNIT_ASSERT_EQUAL(1, (int) results.size());
    CPPUNIT_ASSERT_EQUAL(17, results[0]);
  }

  void test_runBadCommandQueryCheckIfError() {
    fo::DbManager manager = dbManager->spawn();

    std::cout << std::endl << "expecting errors" << std::endl << "-----" << std::endl;

    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl( integer");

    std::cout << std::endl << "-----" << std::endl;

    CPPUNIT_ASSERT(!result);
  }

};

CPPUNIT_TEST_SUITE_REGISTRATION(FoLibCPPTest);
