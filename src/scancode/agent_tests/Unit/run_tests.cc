/*
 SPDX-FileCopyrightText: © 2026 Nakshatra Sharma <nakshatrasharma2002@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <cppunit/BriefTestProgressListener.h>
#include <cppunit/CompilerOutputter.h>
#include <cppunit/TestResult.h>
#include <cppunit/TestResultCollector.h>
#include <cppunit/TestRunner.h>
#include <cppunit/XmlOutputter.h>
#include <cppunit/extensions/TestFactoryRegistry.h>

/**
 * @brief Main test runner for SCANCODE agent unit tests
 * 
 * This file initializes the CppUnit test framework and runs all
 * registered test suites for the SCANCODE agent.
 * 
 * @param argc Number of command line arguments
 * @param argv Array of command line arguments
 * @return 0 if all tests pass, 1 otherwise
 */
int main(int argc, char* argv[])
{
  std::string testPath = (argc > 1) ? std::string(argv[1]) : "";

  // Create test runner and result collector
  CPPUNIT_NS::TestResult testresult;
  CPPUNIT_NS::TestResultCollector collectedresults;
  testresult.addListener(&collectedresults);

  // Add progress listener to show test execution
  CPPUNIT_NS::BriefTestProgressListener progress;
  testresult.addListener(&progress);

  // Get test registry and run tests
  CPPUNIT_NS::TestRunner testrunner;
  testrunner.addTest(CPPUNIT_NS::TestFactoryRegistry::getRegistry().makeTest());
  testrunner.run(testresult);

  // Output results to console
  CPPUNIT_NS::CompilerOutputter compileroutputter(&collectedresults, std::cerr);
  compileroutputter.write();

  // Return 0 if successful, 1 if there were failures
  return collectedresults.wasSuccessful() ? 0 : 1;
}
