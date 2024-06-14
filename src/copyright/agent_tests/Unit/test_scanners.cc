/*
 SPDX-FileCopyrightText: © 2014-15, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "regex.hpp"
#include "regscan.hpp"
#include "copyrightUtils.hpp"
#include "cleanEntries.hpp"
#include <list>
#include <cstring>
#include <ostream>

using namespace std;

/**
 * \brief Create stream which follows agent output format
 * \param[out] out Stream to load data into
 * \param[in]  l   List of matches to create output stream
 */
ostream& operator<<(ostream& out, const list<match>& l)
{
  for (auto m = l.begin(); m != l.end(); ++m)
    out << '[' << m->start << ':' << m->end << ':' << m->type << ']';
  return out;
}

/**
 * \brief test data
 */
const icu::UnicodeString testContent(u"© 2007 Hugh Jackman\n\n"
  "Copyright 2004 my company\n\n"
  "Copyrights by any strange people\n\n"
  "(C) copyright 2007-2011, 2013 my favourite company Google\n\n"
  "(C) 2007-2011, 2013 my favourite company Google\n\n"
  "if (c) { return -1 } \n\n"
  "Written by: me, myself and Irene.\n\n"
  "Authors all the people at ABC\n\n"
  "<author>Author1</author>"
  "<head>All the people</head>"
  "<author>Author1 Author2 Author3</author>"
  "<author>Author4</author><b>example</b>"
  "Apache\n\n"
  "This file is protected under pants 1 , 2 ,3\n\n"
  "Do not modify this document\n\n"
  "the shuttle is a space vehicle designed by NASA\n\n"
  "visit http://mysite.org/FAQ or write to info@mysite.org\n\n"
  "maintained by benjamin drieu <benj@debian.org>\n\n"
  "* Copyright (c) 1989, 1993\n" // Really just one newline here!
  "* The Regents of the University of California. All rights reserved.\n\n"
  "to be licensed as a whole"
  "/* Most of the following tests are stolen from RCS 5.7's src/conf.sh.  */");

class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (copyscannerTest);
  CPPUNIT_TEST (regAuthorTest);
  CPPUNIT_TEST (regIpraTest);
  CPPUNIT_TEST (regEccTest);
  CPPUNIT_TEST (regUrlTest);
  CPPUNIT_TEST (regEmailTest);
  CPPUNIT_TEST (regKeywordTest);
  CPPUNIT_TEST (cleanEntries);

  CPPUNIT_TEST_SUITE_END ();

private:
  /**
   * \brief Runs scanner on content and check matches against expectedStrings
   * \param sc              Scanner to use
   * \param content         Content to scan
   * \param type            Match type
   * \param expectedStrings Expected strings from scanner result
   */
  void scannerTest (const scanner& sc, const icu::UnicodeString& content,
    const string& type, const list<icu::UnicodeString>& expectedStrings)
  {
    list<match> matches;
    list<match> expected;
    sc.ScanString(content, matches);

    for (auto s = expectedStrings.begin(); s != expectedStrings.end(); ++s)
    {
      auto const begin = content.indexOf(*s);
      auto const end = begin + s->countChar32();
      if (begin > -1)
        expected.emplace_back(begin, end, type);
      // else: expected string is not contained in original string
    }
    CPPUNIT_ASSERT_EQUAL(expected, matches);
  }

protected:
  /**
   * \brief Test copyright scanner
   * \test
   * -# Create a copyright scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
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

  /**
   * \brief Test copyright scanner for author
   * \test
   * -# Create a author scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regAuthorTest()
  {
    regexScanner sc("author", "copyright");
    scannerTest(sc, testContent, "author", {
      "Written by: me, myself and Irene.",
      "Authors all the people at ABC",
      "Author1",
      "Author1 Author2 Author3",
      "Author4",
      "maintained by benjamin drieu <benj@debian.org>"
    });
  }

  /**
   * \brief Test Ipra scanner
   * \test
   * -# Create a Ipra scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regIpraTest () {
    regexScanner sc("ipra", "ipra");
    scannerTest(sc, testContent, "ipra", { "US patents 1 , 2 ,3" });
  }

  /**
   * \brief Test ECC scanner
   * \test
   * -# Create a ECC scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regEccTest () {
    regexScanner sc("ecc", "ecc");
    scannerTest(sc, testContent, "ecc", { "space vehicle designed by NASA" });
  }

  /**
   * \brief Test copyright scanner for URL
   * \test
   * -# Create a URL scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regUrlTest () {
    regexScanner sc("url", "copyright");
    scannerTest(sc, testContent, "url", { "http://mysite.org/FAQ" });
  }

  /**
   * \brief Test copyright scanner for email
   * \test
   * -# Create a email scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regEmailTest () {
    regexScanner sc("email", "copyright",1);
    scannerTest(sc, testContent, "email", { "info@mysite.org", "benj@debian.org" });
  }

  /**
   * \brief Test copyright scanner for keywords
   * \test
   * -# Create a keyword scanner
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void regKeywordTest () {
    regexScanner sc("keyword", "keyword");
    scannerTest(sc, testContent, "keyword", {"patent", "licensed as", "stolen from"});
  }

  /**
   * \brief Test cleanMatch() to remove non-UTF8 text and extra spaces
   * \test
   * -# Load test data and expected data
   * -# Generate matches to clean each line in the file
   * -# Call cleanMatch() to clean each line
   * -# Check if cleaned test data matches expected data
   */
  void cleanEntries () {
    // Binary content
    icu::UnicodeString actualFileContent;
    ReadFileToString("../testdata/testdata142", actualFileContent);

    string temp_string;
    actualFileContent.toUTF8String(temp_string);
    wstring actualFileContentW(temp_string.begin(), temp_string.end());

    vector<wstring> binaryStrings;
    auto *ss = new std::wstringstream(actualFileContentW);
    wstring temp;

    while (std::getline(*ss, temp)) {
      binaryStrings.push_back(temp);
    }

    // Simulate matches. Each line is a match
    vector<match> matches;
    int pos = 0;
    int size = binaryStrings.size();
    for (int i = 0; i < size; i++)
    {
      int length = binaryStrings[i].length();
      matches.push_back(
        match(pos, pos + length, "statement"));
      pos += length + 1;
    }

    // Expected data
    icu::UnicodeString expectedFileContent;
    ReadFileToString("../testdata/testdata142_exp", expectedFileContent);

    expectedFileContent.toUTF8String(temp_string);
    wstring expectedFileContentW(temp_string.begin(), temp_string.end());

    delete(ss);
    ss = new std::wstringstream(expectedFileContentW);
    vector<icu::UnicodeString> expectedStrings;
    while (std::getline(*ss, temp)) {
      expectedStrings.push_back(icu::UnicodeString::fromUTF32(
        reinterpret_cast<const UChar32*>(temp.c_str()),
        temp.length())
      );
    }

    vector<icu::UnicodeString> actualStrings;
    for (size_t i = 0; i < matches.size(); i ++)
    {
      actualStrings.push_back(cleanMatch(actualFileContent, matches[i]));
    }

    CPPUNIT_ASSERT(expectedStrings == actualStrings);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
