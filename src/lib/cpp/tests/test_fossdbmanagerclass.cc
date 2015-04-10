/*
 * Copyright (C) 2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "testUtils.hpp"

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <vector>

extern "C" {
#include <libfodbreposysconf.h>
}

#include <iostream>

#include "libfossdbmanagerclass.hpp"

class FoLibCPPTest : public CPPUNIT_NS::TestFixture {
CPPUNIT_TEST_SUITE(FoLibCPPTest);
    CPPUNIT_TEST(test_runSimpleQuery);
    CPPUNIT_TEST(test_runCommandQueryCheckIfSuccess);
    CPPUNIT_TEST(test_tableExists);
    CPPUNIT_TEST(test_runPreparedStatement);
    CPPUNIT_TEST(test_transactions);
    CPPUNIT_TEST(test_runBadCommandQueryCheckIfError);
    CPPUNIT_TEST(test_runSchedulerConnectConstructor);
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

  void test_tableExists() {
    fo::DbManager manager = dbManager->spawn();

    CPPUNIT_ASSERT(manager.queryPrintf("CREATE TABLE tbl(col integer)"));

    CPPUNIT_ASSERT(manager.tableExists("tbl"));
    CPPUNIT_ASSERT(!manager.tableExists("tb"));
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
    std::vector<int> expected = {17};

    CPPUNIT_ASSERT_EQUAL(expected, results);
  };


  void test_runPreparedStatement() {
    fo::DbManager manager = dbManager->spawn();

    {
      CPPUNIT_ASSERT(manager.queryPrintf("CREATE TABLE tbl(col integer)"));

      fo_dbManager_PreparedStatement* preparedStatement = fo_dbManager_PrepareStamement(
        manager.getStruct_dbManager(),
        "test",
        "INSERT INTO tbl(col) VALUES ($1)",
        int
      );

      manager.begin();
      for (int i = 0; i < 5; ++i) {
        manager.execPrepared(preparedStatement, i * 2);
      }
      manager.commit();
    }

    fo::QueryResult result = manager.queryPrintf("SELECT * FROM tbl");

    CPPUNIT_ASSERT(result);

    std::vector<int> results = result.getSimpleResults(0, atoi);

    std::vector<int> expected = {0, 2, 4, 6, 8};

    CPPUNIT_ASSERT_EQUAL(expected, results);
  }

  void test_transactions() {
    fo::DbManager manager1 = dbManager->spawn();

    CPPUNIT_ASSERT(manager1.queryPrintf("CREATE TABLE tbl(col integer)"));
    {
      fo::DbManager manager2 = dbManager->spawn();

      fo_dbManager_PreparedStatement* preparedStatement1 = fo_dbManager_PrepareStamement(
        manager1.getStruct_dbManager(),
        "test",
        "INSERT INTO tbl(col) VALUES ($1)",
        int
      );

      fo_dbManager_PreparedStatement* preparedStatement2 = fo_dbManager_PrepareStamement(
        manager2.getStruct_dbManager(),
        "test",
        "INSERT INTO tbl(col) VALUES ($1)",
        int
      );

      CPPUNIT_ASSERT(manager1.begin());
      CPPUNIT_ASSERT(manager2.begin());
      for (int i = 0; i < 5; ++i) {
        CPPUNIT_ASSERT(manager1.execPrepared(preparedStatement1, (i + 1) * 2));
        CPPUNIT_ASSERT(manager2.execPrepared(preparedStatement2, i * 2));
      }
      CPPUNIT_ASSERT(manager1.commit());
      CPPUNIT_ASSERT(manager2.rollback());
    }

    fo::QueryResult result = manager1.queryPrintf("SELECT * FROM tbl");

    CPPUNIT_ASSERT(result);

    std::vector<int> results = result.getSimpleResults(0, atoi);

    std::vector<int> expected = {2, 4, 6, 8, 10};

    CPPUNIT_ASSERT_EQUAL(expected, results);
  }

  void test_runBadCommandQueryCheckIfError() {
    fo::DbManager manager = dbManager->spawn();

    // TODO implement fo::DbManager::setLogFile() and check that errors pass through
    std::cout << std::endl << "expecting errors" << std::endl << "-----" << std::endl;

    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl( integer");

    std::cout << std::endl << "-----" << std::endl;

    CPPUNIT_ASSERT(!result);
  }

  void test_runSchedulerConnectConstructor() {
    const char* sysConf = get_sysconfdir(); // [sic]

    // TODO make this correctly
    CPPUNIT_ASSERT(system((std::string("install -D ../../../../VERSION '") + sysConf + "/mods-enabled/an agent name/VERSION'").c_str()) >= 0);
    char const* argv[] = {"an agent name", "-c", sysConf};
    int argc = 3;

    fo::DbManager manager(&argc, (char**) argv); // [sic]

    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl()");

    CPPUNIT_ASSERT(result);
  }

};

CPPUNIT_TEST_SUITE_REGISTRATION(FoLibCPPTest);
