/*
 * Copyright (C) 2019-2020, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

using namespace std;

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
    ofstream file("atarashi-Tests-Results.xml");
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
