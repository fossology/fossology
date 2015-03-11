/*********************************************************************
Copyright (C) 2014-2015, Siemens AG

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
#include "regscan.hpp"
#include "copyrightUtils.hpp"
#include "regTypes.hpp"
#include <list>
#include <cstring>
#include <ostream>

using namespace std;

bool operator==(const match& m1, const match& m2)
{
  return m1.start == m2.start && m1.end == m2.end && strcmp(m1.type, m2.type) == 0;
}
bool operator!=(const match& m1, const match& m2)
{
  return !(m1 == m2);
}
ostream& operator<<(ostream& out, const list<match>& l)
{
  for (const match& m : l)
    out << '[' << m.start << ':' << m.end << ':' << m.type << ']';
  return out;
}

const char testContent[] = "© 2007 Hugh Jackman\n"
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
  "* The Regents of the University of California. All rights reserved.";
  
class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (copyscannerTest);
  CPPUNIT_TEST (regIPTest);
  CPPUNIT_TEST (regEccTest);
  CPPUNIT_TEST (regUrlTest);
  CPPUNIT_TEST (regEmailTest);

  CPPUNIT_TEST_SUITE_END ();

private:
  void scannerTest (const scanner* sc, const char* content, const char* type, list<const char*> expectedStrings)
  {
    list<match> matches;
    list<match> expected;
    sc->ScanString(content, matches);
    
    for (const char * s : expectedStrings)
    {
      int pos = strstr(content, s) - content;
      expected.push_back(match(pos, pos+strlen(s), type));
    }
    CPPUNIT_ASSERT_EQUAL(expected, matches);
  }
  
protected:
  void copyscannerTest()
  {
    // Test copyright matcher
    hCopyrightScanner sc;

    scannerTest(&sc, testContent, "statement", { "© 2007 Hugh Jackman",
      "Copyright 2004 my company",
      "Copyrights by any strange people",
      "(C) copyright 2007-2011, 2013 my favourite company Google",
      "(C) 2007-2011, 2013 my favourite company Google",
      "Written by: me, myself and Irene.",
      "Authors all the people at ABC",
      "maintained by benjamin drieu <benj@debian.org>",
      "Copyright (c) 1989, 1993\n* The Regents of the University of California. All rights reserved."
    });
  }

  void regIPTest () {
    regexScanner sc(regIp::getRegex(), regIp::getType());
    scannerTest(&sc, testContent, regIp::getType(), { "US patents 1 , 2 ,3" });
  }

  void regEccTest () {
    regexScanner sc(regEcc::getRegex(), regEcc::getType());
    scannerTest(&sc, testContent, regEcc::getType(), { "space vehicle designed by NASA" });
  }

  void regUrlTest () {
    regexScanner sc(regURL::getRegex(), regURL::getType());
    scannerTest(&sc, testContent, regURL::getType(), { "http://mysite.org/FAQ" });
  }

  void regEmailTest () {
    regexScanner sc(regEmail::getRegex(), regEmail::getType());
    scannerTest(&sc, testContent, regEmail::getType(), { "info@mysite.org", "<benj@debian.org>" });
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
