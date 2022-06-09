/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <cppunit/BriefTestProgressListener.h>
#include <cppunit/CompilerOutputter.h>
#include <cppunit/TestResult.h>
#include <cppunit/TestResultCollector.h>
#include <cppunit/TestRunner.h>
#include <cppunit/XmlOutputter.h>
#include <cppunit/extensions/TestFactoryRegistry.h>
#include <fstream>
#include <stdexcept>

/**
 * \dir
 * \brief Unit test cases for CPP library
 * \file
 * \brief CPP unit test cases runner
 */

using namespace std;

/**
 * Main function to run the test cases
 */
int main(int argc, char* argv[])
{
  // Retrieve test path from command line first argument.
  // Default to "" which resolves to the top level suite.
  string testPath = (argc > 1) ? string(argv[1]) : string("");

  // Create the event manager and test controller.
  CPPUNIT_NS::TestResult controller;

  // Add a listener that colllects test results.
  CPPUNIT_NS::TestResultCollector result;
  controller.addListener(&result);

  // Add a listener that prints dots as tests run.
  CPPUNIT_NS::BriefTestProgressListener progress;
  controller.addListener(&progress);

  // Add the top suite to the test runner.
  CPPUNIT_NS::TestRunner runner;
  runner.addTest(CPPUNIT_NS::TestFactoryRegistry::getRegistry().makeTest());

  try
  {
    CPPUNIT_NS::stdCOut() << "Running " << (testPath.empty() ? "all tests" : testPath) << endl;
    runner.run(controller, testPath);
    CPPUNIT_NS::stdCOut() << endl;

    // Print test in a compiler compatible format.
    CPPUNIT_NS::CompilerOutputter outputter(&result, CPPUNIT_NS::stdCOut());
    outputter.write();

    // Generate XML output.
    ofstream file("libcpp-Tests-Results.xml");
    CPPUNIT_NS::XmlOutputter xml(&result, file);
    xml.write();
    file.close();
  } catch (invalid_argument& e)
  { // Test path not resolved.
    CPPUNIT_NS::stdCOut() << endl << "ERROR: " << e.what() << endl;
    return 1;
  }

  return result.wasSuccessful() ? 0 : 1;
}
