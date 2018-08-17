/*
 * Copyright (C) 2015, Siemens AG
 * Author: Maximilian Huber
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
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

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "regexConfProvider.hpp"
#include <sstream>

using namespace std;

class regexConfProviderTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (regexConfProviderTestSuite);
  CPPUNIT_TEST (simpleTest);
  CPPUNIT_TEST (simpleReplacementTest);
  CPPUNIT_TEST (multipleReplacementTest);

  CPPUNIT_TEST_SUITE_END ();

private:
  void regexConfProviderTest (istringstream& testStream,
                    const string& testString,
                    const string& testKey)
  {
    string testIdentity("testIdentity");
    
    RegexConfProvider rcp;

    // load parse test-stream
    rcp.maybeLoad(testIdentity,testStream);
    
    // test RegexConfProvider
    CPPUNIT_ASSERT_MESSAGE("The generated string schould match the expected string",
                           0 == strcmp(testString.c_str(),
                                       rcp.getRegexValue(testIdentity,testKey)));
  }

protected:
  void simpleTest()
  {
    string testString = "Lorem Ipsum";
    string testKey = "TEST";
    string testLine = testKey + "=" + testString + "\n";
    istringstream testStream(testLine);

    regexConfProviderTest(testStream,testString,testKey);
  }

  void simpleReplacementTest()
  {
    string testString = "Lorem Ipsum";
    string testKey = "TEST";
    string testLine =
      testKey + "=" + "Lorem \n" +
      testKey + "=__" + testKey + "__Ipsum\n";
    istringstream testStream(testLine);

    regexConfProviderTest(testStream,testString,testKey);
  }

  void multipleReplacementTest()
  {
    string testString = "Lorem Ipsum";
    string testKey = "TEST";
    string testLine =
      string("SPACE= \n") +
      "INFIX2=su\n" +
      "INFIX1=rem__SPACE__I\n" +
      testKey + "=Lo__INFIX1__p__INFIX2__m\n";
    istringstream testStream(testLine);

    regexConfProviderTest(testStream,testString,testKey);
  }

  void testForInfiniteRecursion()
  {
    string testString = "Lorem Ipsum";
    string testKey = "TEST";
    string testLine =
      string("LOREM=Lorem__LOREM__ \n") +
      testKey + "=__LOREM__Ipsum\n";
    istringstream testStream(testLine);

    string testIdentity("testIdentity");
    
    RegexConfProvider rcp;

    // load parse test-stream
    rcp.maybeLoad(testIdentity,testStream);

    // evaluate and verify, that recursion does not appear
    CPPUNIT_ASSERT_MESSAGE("This should just terminate (the return value is not specified)",
                           rcp.getRegexValue(testIdentity,testKey));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( regexConfProviderTestSuite );
