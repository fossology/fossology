/*********************************************************************
Copyright (C) 2014-2015, 2018 Siemens AG

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
#include <list>
#include <cstring>
#include <ostream>

using namespace std;

ostream& operator<<(ostream& out, const list<match>& l)
{
  for (auto m = l.begin(); m != l.end(); ++m)
    out << '[' << m->start << ':' << m->end << ':' << m->type << ']';
  return out;
}

const char testContent[] = "© 2007 Hugh Jackman\n\n"
  "Copyright 2004 my company\n\n"
  "Copyrights by any strange people\n\n"
  "(C) copyright 2007-2011, 2013 my favourite company Google\n\n"
  "(C) 2007-2011, 2013 my favourite company Google\n\n"
  "if (c) { return -1 } \n\n"
  "Written by: me, myself and Irene.\n\n"
  "Authors all the people at ABC\n\n"
  "Apache\n\n"
  "This file is protected under pants 1 , 2 ,3\n\n"
  "Do not modify this document\n\n"
  "the shuttle is a space vehicle designed by NASA\n\n"
  "visit http://mysite.org/FAQ or write to info@mysite.org\n\n"
  "maintained by benjamin drieu <benj@debian.org>\n\n"
  "* Copyright (c) 1989, 1993\n" // Really just one newline here!
  "* The Regents of the University of California. All rights reserved.\n\n"
  "to be licensed as a whole";
  
class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (copyscannerTest);
  CPPUNIT_TEST (regAuthorTest);
  CPPUNIT_TEST (regEccTest);
  CPPUNIT_TEST (regUrlTest);
  CPPUNIT_TEST (regEmailTest);
  CPPUNIT_TEST (regKeywordTest);

  CPPUNIT_TEST_SUITE_END ();

private:
  void scannerTest (const scanner& sc, const char* content, const string& type, list<const char*> expectedStrings)
  {
    list<match> matches;
    list<match> expected;
    sc.ScanString(content, matches);

    for (auto s = expectedStrings.begin(); s != expectedStrings.end(); ++s)
    {
      const char * p = strstr(content, *s);
      if (p)
      {
        int pos = p - content;
        expected.push_back(match(pos, pos+strlen(*s), type));
      }
      // else: expected string is not contained in original string
    }
    CPPUNIT_ASSERT_EQUAL(expected, matches);
  }

protected:
  void copyscannerTest()
  {
    // Test copyright matcher
    hCopyrightScanner sc;

    scannerTest(sc, testContent, "statement", { "© 2007 Hugh Jackman",
      "Copyright 2004 my company",
      "Copyrights by any strange people",
      "(C) copyright 2007-2011, 2013 my favourite company Google",
      "(C) 2007-2011, 2013 my favourite company Google",
      "Copyright (c) 1989, 1993\n* The Regents of the University of California. All rights reserved."
    });
  }
  
  void regAuthorTest()
  {
    regexScanner sc("author", "copyright");
    scannerTest(sc, testContent, "author", {
      "Written by: me, myself and Irene.",
      "Authors all the people at ABC",
      "maintained by benjamin drieu <benj@debian.org>"
    });
  }

  void regEccTest () {
    regexScanner sc("ecc", "ecc");
    scannerTest(sc, testContent, "ecc", { "space vehicle designed by NASA" });
  }

  void regUrlTest () {
    regexScanner sc("url", "copyright");
    scannerTest(sc, testContent, "url", { "http://mysite.org/FAQ" });
  }

  void regEmailTest () {
    regexScanner sc("email", "copyright",1);
    scannerTest(sc, testContent, "email", { "info@mysite.org", "benj@debian.org" });
  }
  void regKeywordTest () {
    regexScanner sc("keyword", "keyword");
    scannerTest(sc, testContent, "keyword", {"patent", "licensed as"});
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
