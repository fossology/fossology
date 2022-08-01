/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

/**
 * \file
 * \brief Test case for DB Manager
 */

/**
 * \class FoLibCPPTest
 * \brief Test cases for CPP DB Manager
 */
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
  fo::DbManager* dbManager;       ///< Object for DbManager

public:
  /**
   * One time setup to create test environment and get new DbManager
   */
  void setUp() {
    dbManager = new fo::DbManager(createTestEnvironment("", NULL, 0));
  }

  /**
   * Tear down to destroy DbManager object and test environment
   */
  void tearDown() {
    delete dbManager;
    // dbManager connection is already closed by destructor
    dropTestEnvironment(NULL, "", NULL);
  }

  /**
   * Test to check if simple query executes successfully
   * \test
   * -# Spawn a new DbManager.
   * -# Call fo::DbManager::queryPrintf() with simple statement.
   * -# Check for the result.
   */
  void test_runCommandQueryCheckIfSuccess() {
    fo::DbManager manager = dbManager->spawn();
    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl(%s integer)", "col");

    CPPUNIT_ASSERT(result);
  }

  /**
   * Test to check fo::DbManager::tableExists() function
   * \test
   * -# Spawn a new DbManager.
   * -# Create a new table.
   * -# Call fo::DbManager::tableExists() with newly created table.
   * -# Check for the result to be true.
   * -# Call fo::DbManager::tableExists() with non existing table name.
   * -# Check for the result to be false.
   */
  void test_tableExists() {
    fo::DbManager manager = dbManager->spawn();

    CPPUNIT_ASSERT(manager.queryPrintf("CREATE TABLE tbl(col integer)"));

    CPPUNIT_ASSERT(manager.tableExists("tbl"));
    CPPUNIT_ASSERT(!manager.tableExists("tb"));
  }

  /**
   * Test to check fo::QueryResult::getSimpleResults() function
   * \test
   * -# Spawn a new DbManager.
   * -# Create a new table and insert some values in it.
   * -# Call fo::DbManager::queryPrintf() to select new result.
   * -# Check the fo::QueryResult object for row count.
   * -# Check the value of fo::QueryResult::getSimpleResults().
   */
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

  /**
   * Test to check fo::DbManager::execPrepared() function
   * \test
   * -# Spawn a new DbManager.
   * -# Create a prepared statement.
   * -# Call fo::DbManager::execPrepared() with the newly created statement.
   * -# Check for the result.
   */
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

  /**
   * Test to check transaction functions
   * \test
   * -# Spawn a new DbManager.
   * -# Create a test table.
   * -# Spawn two new DbManager s.
   * -# Call fo::DbManager::begin() on new managers.
   * -# Insert some data using new managers.
   * -# Call fo::DbManager::commit() on one manager.
   * -# Call fo::DbManager::rollback() on another manager.
   * -# Inserts from other manager should not be there in the table.
   */
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

  /**
   * Test to check bad query
   * \test
   * -# Spawn a new DbManager.
   * -# Call fo::DbManager::queryPrintf() with a corrupted statement.
   * -# Check if result is a failure.
   * \todo implement fo::DbManager::setLogFile() and check that errors pass through
   */
  void test_runBadCommandQueryCheckIfError() {
    fo::DbManager manager = dbManager->spawn();

    // TODO implement fo::DbManager::setLogFile() and check that errors pass through
    std::cout << std::endl << "expecting errors" << std::endl << "-----" << std::endl;

    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl( integer");

    std::cout << std::endl << "-----" << std::endl;

    CPPUNIT_ASSERT(!result);
  }

  /**
   * Test to check if DbManager can create connection with scheduler connect
   * \test
   * -# Create a test sysconf dir with a VERSION file.
   * -# Set the `argv`
   * -# Call fo::DbManager::DbManager() with the `argv`.
   * -# Execute a statement and check the result.
   * \todo make this correctly
   */
  void test_runSchedulerConnectConstructor() {
    const char* sysConf = get_sysconfdir(); // [sic]

    // TODO make this correctly
    CPPUNIT_ASSERT(system((std::string("install -D ../../../../VERSION '") + sysConf + "/mods-enabled/an agent name/VERSION'").c_str()) >= 0);
    char const* argv[] = {"an agent name", "-c", sysConf};
    int argc = 3;

    fo::DbManager manager(&argc, (char**) argv); // [sic]

    fo::QueryResult result = manager.queryPrintf("CREATE TABLE tbl()");

    CPPUNIT_ASSERT(result);

    // TODO make this correctly
    CPPUNIT_ASSERT(system((std::string("rm -rf '") + sysConf + "/mods-enabled'").c_str()) >= 0);
  }

};

CPPUNIT_TEST_SUITE_REGISTRATION(FoLibCPPTest);
