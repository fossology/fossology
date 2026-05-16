/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * \file
 * \brief Unit test runner for Reuser agent
 */
#include <cppunit/CompilerOutputter.h>
#include <cppunit/TestResult.h>
#include <cppunit/TestResultCollector.h>
#include <cppunit/TestRunner.h>
#include <cppunit/XmlOutputter.h>
#include <cppunit/extensions/TestFactoryRegistry.h>

#ifdef WIN32
#include <cppunit/TextTestProgressListener.h>
#else
#include <cppunit/BriefTestProgressListener.h>
#endif
#include <fstream>
#include <stdexcept>


int
main( int argc, char* argv[] )
{
  std::string testPath = (argc > 1) ? std::string(argv[1]) : std::string("");

  CPPUNIT_NS::TestResult controller;

  CPPUNIT_NS::TestResultCollector result;
  controller.addListener( &result );

#ifdef WIN32
  CPPUNIT_NS::TextTestProgressListener progress;
#else
  CPPUNIT_NS::BriefTestProgressListener progress;
#endif
  controller.addListener( &progress );

  CPPUNIT_NS::TestRunner runner;
  runner.addTest( CPPUNIT_NS::TestFactoryRegistry::getRegistry().makeTest() );
  try
  {
    CPPUNIT_NS::stdCOut() << "Running "  <<  testPath;
    runner.run( controller, testPath );

    CPPUNIT_NS::stdCOut() << "\n";

    CPPUNIT_NS::CompilerOutputter outputter( &result, CPPUNIT_NS::stdCOut() );
    outputter.write();
  }
  catch ( std::invalid_argument &e )
  {
    CPPUNIT_NS::stdCOut()  <<  "\n" <<  "ERROR: "  <<  e.what() << "\n";
    return 1;
  }

  return result.wasSuccessful() ? 0 : 1;
}
