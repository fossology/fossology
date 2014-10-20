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
GPL v2\n\
This file is protected unter US patents 1 , 2 ,3\n\
Do not modify this document\n"
            );
  };
  void tearDown (void) { cout << "Tear down" << endl;};

protected:
  void regMatcherTest (void) {



    vector<RegexMatcher> copyrights;
    copyrights.push_back(RegexMatcher(regCopyright::getType(), regCopyright::getRegex()));

    vector<CopyrightMatch> matches = matchStringToRegexes(content,copyrights);


    CopyrightMatch match = matches[0];

    CPPUNIT_ASSERT_EQUAL(string("Copyright 2004 my company"), match.getContent());
  };





};

CPPUNIT_TEST_SUITE_REGISTRATION( regexRegMatcher );
