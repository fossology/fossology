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
  void setUp (void)  {
    content = string(
                "© 2007 Hugh Jackman\n"
                "Copyright 2004 my company\n"
                "Copyrights by any strange people\n"
                "(C) copyright 2007-2011, 2013 my favourite company Google\n"
                "(C) 2007-2011, 2013 my favourite company Google\n"
                "if (c) return -1 \n"
                "Written by: me, myself and Irene.\n"
                "Authors all the people at ABC\n"
                "Apache\n"
                "This file is protected unter US patents 1 , 2 ,3\n"
                "Do not modify this document\n"
                "the shuttle is a space vehicle designed by NASA\n"
                "visit http://mysite.org/FAQ or write to info@mysite.org\n"
                "maintained by benjamin drieu <benj@debian.org>\n"
                "* Copyright (c) 1989, 1993\n"
                "* The Regents of the University of California. All rights reserved."
            );
  };

protected:
  void regMatcherTest (void) {
    vector<RegexMatcher> copyrights;
    auto type = regCopyright::getType();
    copyrights.push_back(RegexMatcher(type, regCopyright::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    vector<CopyrightMatch> expected;
    expected.push_back(CopyrightMatch("© 2007 Hugh Jackman", type, 0));
    expected.push_back(CopyrightMatch("Copyright 2004 my company", type, 21));
    expected.push_back(CopyrightMatch("Copyrights by any strange people", type, 47));
    expected.push_back(CopyrightMatch("(C) copyright 2007-2011, 2013 my favourite company Google", type, 80));
    expected.push_back(CopyrightMatch("Written by: me, myself and Irene.", type, 204));
    expected.push_back(CopyrightMatch("Authors all the people at ABC", type, 238));
    expected.push_back(CopyrightMatch("maintained by benjamin drieu <benj@debian.org>", type, 456));
    expected.push_back(CopyrightMatch("Copyright (c) 1989, 1993\n* The Regents of the University of California. All rights reserved.", type, 505));

    CPPUNIT_ASSERT_EQUAL(expected, matches);
  };

  void regMatcherIpTest (void) {
    vector<RegexMatcher> copyrights;
    auto type = regIp::getType();
    copyrights.push_back(RegexMatcher(type, regIp::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    vector<CopyrightMatch> expected;
    expected.push_back(CopyrightMatch("US patents 1 , 2 ,3", type, 304));

    CPPUNIT_ASSERT_EQUAL(expected, matches);
  };

  void regMatcherEccTest (void) {
    vector<RegexMatcher> copyrights;
    auto type = regEcc::getType();
    copyrights.push_back(RegexMatcher(type, regEcc::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    vector<CopyrightMatch> expected;
    expected.push_back(CopyrightMatch("space vehicle designed by NASA", type, 369));

    CPPUNIT_ASSERT_EQUAL(expected, matches);
  };

  void regMatcherUrlTest (void) {
    vector<RegexMatcher> copyrights;
    auto type = regURL::getType();
    copyrights.push_back(RegexMatcher(type, regURL::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    vector<CopyrightMatch> expected;
    expected.push_back(CopyrightMatch("http://mysite.org/FAQ", type, 406));

    CPPUNIT_ASSERT_EQUAL(expected, matches);
  };

  void regMatcherEmailTest (void) {
    vector<RegexMatcher> copyrights;
    auto type = regEmail::getType();
    copyrights.push_back(RegexMatcher(type, regEmail::getRegex(), 1)); //TODO get 1 from regex definition

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);

    vector<CopyrightMatch> expected;
    expected.push_back(CopyrightMatch("info@mysite.org", type, 440));
    expected.push_back(CopyrightMatch("benj@debian.org", type, 486));

    CPPUNIT_ASSERT_EQUAL(expected, matches);
  };
};

CPPUNIT_TEST_SUITE_REGISTRATION( regexRegMatcher );
