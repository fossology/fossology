/*********************************************************************
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*********************************************************************/

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "regex.hpp"
#include "regexMatcher.hpp"
#include "copyrightUtils.hpp"
#include "regTypes.hpp"
#include <vector>

using namespace std;

class regexRegMatcher : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (regexRegMatcher);
  CPPUNIT_TEST (regMatcherTest);
  CPPUNIT_TEST (regMatcherIpTest);
  CPPUNIT_TEST (regMatcherEccTest);
  CPPUNIT_TEST (regMatcherUrlTest);
  CPPUNIT_TEST (regMatcherEmailTest);

  CPPUNIT_TEST_SUITE_END ();

private:
  std::string content;

public:
  void setUp (void)  {cout << "Setup" <<endl;
    content = string(
                "© 2007 Hugh Jackman\n\
Copyright 2004 my company\n\
Copyrights by any strange people\n\
(C) copyright 2007-2011, 2013 my favourite company Google\n\
(C) 2007-2011, 2013 my favourite company Google\n\
if (c) return -1 \n\
Written by: me, myself and Irene \n\
Authors all the people at ABC\n\
GPL v2\n\
This file is protected unter US patents 1 , 2 ,3\n\
Do not modify this document\n\
the shuttle is a space vehicle designed by NASA\n\
visit http://mysite.org/FAQ or write to info@mysite.org"
            );
  };
  void tearDown (void) { cout << "Tear down" << endl;};

private:


protected:
  void regMatcherTest (void) {
    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regCopyright::getType(), regCopyright::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    CopyrightMatch match = matches[1];

    CPPUNIT_ASSERT_EQUAL(string("Copyright 2004 my company"), match.getContent());
    CPPUNIT_ASSERT_EQUAL((unsigned int) 21, match.getStart());
    CPPUNIT_ASSERT_EQUAL((unsigned int) 25, match.getLength());
    CPPUNIT_ASSERT_EQUAL(regCopyright::getType(), match.getType());

    CPPUNIT_ASSERT_EQUAL((size_t) 6, (size_t) matches.size());

    CPPUNIT_ASSERT_EQUAL(string("© 2007 Hugh Jackman"), matches[0].getContent());
    CPPUNIT_ASSERT_EQUAL(string("Copyright 2004 my company"), matches[1].getContent());
    CPPUNIT_ASSERT_EQUAL(string("Copyrights by any strange people"), matches[2].getContent());
    CPPUNIT_ASSERT_EQUAL(string("(C) copyright 2007-2011, 2013 my favourite company Google"), matches[3].getContent());
    CPPUNIT_ASSERT_EQUAL(string("Written by: me, myself and Irene"), matches[4].getContent());
    CPPUNIT_ASSERT_EQUAL(string("Authors all the people at ABC"), matches[5].getContent());
  };

  void regMatcherIpTest (void) {
    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regIp::getType(), regIp::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    CPPUNIT_ASSERT_EQUAL((size_t) 1, (size_t) matches.size());
    CopyrightMatch match = matches[0];

    CPPUNIT_ASSERT_EQUAL(string("US patents 1 , 2 ,3"), match.getContent());
  };

  void regMatcherEccTest (void) {
    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regEcc::getType(), regEcc::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    CPPUNIT_ASSERT_EQUAL((size_t) 1, (size_t) matches.size());
    CopyrightMatch match = matches[0];

    CPPUNIT_ASSERT_EQUAL(string("space vehicle designed by NASA"), match.getContent());
  };

  void regMatcherUrlTest (void) {
    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regURL::getType(), regURL::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    CPPUNIT_ASSERT_EQUAL((size_t) 1, (size_t) matches.size());
    CopyrightMatch match = matches[0];

    CPPUNIT_ASSERT_EQUAL(string("http://mysite.org/FAQ"), match.getContent());
  };

  void regMatcherEmailTest (void) {
    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regEmail::getType(), regEmail::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    CPPUNIT_ASSERT_EQUAL((size_t) 1, (size_t) matches.size());
    CopyrightMatch match = matches[0];

    CPPUNIT_ASSERT_EQUAL(string("info@mysite.org"), match.getContent());
  };
};

CPPUNIT_TEST_SUITE_REGISTRATION( regexRegMatcher );
