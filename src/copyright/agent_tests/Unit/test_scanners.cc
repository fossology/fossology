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
const char testContent[] = "© 2007 Hugh Jackman\n\n"
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
  "/* Most of the following tests are stolen from RCS 5.7's src/conf.sh.  */";

class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (copyscannerTest);
  CPPUNIT_TEST (copyscannerDotPrefixedNameTest);
  CPPUNIT_TEST (copyscannerBareKeywordDiscardTest);
  CPPUNIT_TEST (copyscannerCopyrightedStatementTest);
  CPPUNIT_TEST (copyscannerBinaryNoiseTest);
  CPPUNIT_TEST (copyscannerSpdxFullLineTest);
  CPPUNIT_TEST (copyscannerSpdxArrayTest);
  CPPUNIT_TEST (copyscannerProseExceptionTest);
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
      "Copyright (c) 1989, 1993\n* The Regents of the University of California."
    });
  }

  /**
   * \brief Test copyright scanner with dot-prefixed names like .NET Foundation
   * \test
   */
  void copyscannerDotPrefixedNameTest()
  {
    hCopyrightScanner sc;
    // © followed by a dot-prefixed name (.NET) must be fully preserved
    const char* content1 = "Copyright \xc2\xa9 .NET Foundation and contributors\n";
    scannerTest(sc, content1, "statement",
      {"Copyright \xc2\xa9 .NET Foundation and contributors"});

    // Standalone © (no 'copyright' keyword) with dot-prefixed name
    const char* content2 = "\xc2\xa9 .NET Foundation and contributors\n";
    scannerTest(sc, content2, "statement",
      {"\xc2\xa9 .NET Foundation and contributors"});

    // Year-qualified form must still work
    const char* content3 = "Copyright \xc2\xa9 2021 .NET Foundation\n";
    scannerTest(sc, content3, "statement",
      {"Copyright \xc2\xa9 2021 .NET Foundation"});
  }

  /**
   * \brief Test that bare copyright keywords produce no matches
   * \test
   */
  void copyscannerBareKeywordDiscardTest()
  {
    hCopyrightScanner sc;

    // All bare-keyword variants must produce NO match
    const char* bare[] = {
      "copyright\n",
      "Copyright\n",
      "COPYRIGHT\n",
      "Copyrights\n",
      nullptr
    };
    for (int i = 0; bare[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(bare[i], matches);
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected no match for bare keyword: ") + bare[i],
        matches.empty());
    }

    // Strings with real content must still produce exactly one match each
    const char* valid[] = {
      "Copyright 2021 .NET Foundation\n",
      "Copyright (c) 2004 My Company\n",
      "Copyright \xc2\xa9 .NET Foundation and contributors\n",
      nullptr
    };
    for (int i = 0; valid[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(valid[i], matches);
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected one match for valid copyright: ") + valid[i],
        matches.size() == 1);
    }
  }

  /**
   * \brief Regression: "copyrighted" statements and names like "Tom" must not
   * be falsely deactivated by REG_EXCEPTION_COPY.
   * \test
   */
  void copyscannerCopyrightedStatementTest()
  {
    hCopyrightScanner sc;

    // All must produce exactly one active match
    const char* valid[] = {
      "Copyrighted (C) 1994 Normunds Saumanis (normunds@rx.tech.swh.lv)\n",
      "Copyrighted (C) 1994, 1995, 1996 Normunds Saumanis (normunds@fi.ibm.com)\n",
      "copyrighted (C) 1993 by Hartmut Schirmer\n",
      "copyrighted 1992 by Mark Adler version c10p1, 10 January 1993\n",
      "copyrighted 1990 Mark Adler\n",
      "Copyright (C) Tom Long Nguyen (tom.l.nguyen@intel.com)\n",
      "Copyright (C) Torres Martinez\n",
      "Copyright (C) Tobias Schmidt\n",
      "Copyright (C) Tomas Novak\n",
      nullptr
    };
    for (int i = 0; valid[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(valid[i], matches);
      // Must have at least one match and every match must be active
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected active match for: ") + valid[i],
        !matches.empty() && matches.front().is_enabled);
    }
  }

  /**
   * \brief Regression: binary-file content with a short ASCII prefix before
   * non-ASCII bytes must not be reported as active copyright statements.
   * \test
   */
  void copyscannerBinaryNoiseTest()
  {
    hCopyrightScanner sc;

    // Each string is © + short ASCII run + non-ASCII noise.
    // None must produce an active match.
    const char* noise[] = {
      // 3-char ASCII prefix then non-ASCII
      "\xc2\xa9 sjw\xc2\xa8noise\n",
      "\xc2\xa9 OMr\xc2\xa5more\n",
      "\xc2\xa9 tGa\xc3\x89garbage\n",
      // 4-char ASCII prefix then non-ASCII
      "\xc2\xa9 ZgU,\xc2\xb5garbage\n",
      "\xc2\xa9 VJs0\xc3\x93noise\n",
      // 7-char ASCII prefix then non-ASCII
      "\xc2\xa9 NuXnHl{\xc2\xa4" "noise\n",
      // 8-char ASCII prefix then non-ASCII
      "\xc2\xa9 KtCtdy\x22s\xc3\xa8noise\n",
      nullptr
    };

    for (int i = 0; noise[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(noise[i], matches);
      bool hasActive = !matches.empty() && matches.front().is_enabled;
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected no active match for binary noise string #") + to_string(i),
        !hasActive);
    }
  }

  /**
   * \brief Regression: SPDX-FileCopyrightText = [...] TOML array format must
   * yield one active match per quoted element, not a single merged string.
   * \test
   */
  void copyscannerSpdxArrayTest()
  {
    hCopyrightScanner sc;

    // Normal closed array: 1 deactivated header + 3 active elements
    {
      const char content[] =
        "SPDX-FileCopyrightText = [\n"
        "\"2026 Fraunhofer-Institut f\xC3\xBCr Produktionstechnik und Automatisierung IPA\",\n"
        "\"2026 Hilscher Gesellschaft f\xC3\xBCr Systemautomation mbH\",\n"
        "\"2026 Siemens AG\",\n"
        "]\n";

      list<match> matches;
      sc.ScanString(content, matches);

      CPPUNIT_ASSERT_EQUAL_MESSAGE("Expected 4 matches total", (size_t)4, matches.size());

      auto it = matches.begin();
      CPPUNIT_ASSERT_MESSAGE("Header must be inactive", !it->is_enabled);
      ++it;
      for (int i = 1; i <= 3; ++i, ++it)
        CPPUNIT_ASSERT_MESSAGE(
          string("Array element ") + to_string(i) + " must be active", it->is_enabled);
    }

    // Malformed array (no closing ]): scan must continue past the block and
    // still detect copyright statements that follow it.
    {
      const char content[] =
        "SPDX-FileCopyrightText = [\n"
        "\"2026 Company A\",\n"
        "\"2026 Company B\",\n"
        // no closing ]
        "Copyright 2021 Google LLC\n";

      list<match> matches;
      sc.ScanString(content, matches);

      bool foundGoogle = false;
      for (auto& m : matches) {
        int len = m.end - m.start;
        if (len > 0 && strncmp(content + m.start, "Copyright 2021 Google", 21) == 0)
          foundGoogle = true;
      }
      CPPUNIT_ASSERT_MESSAGE(
        "Copyright after unclosed SPDX array must still be detected", foundGoogle);
    }
  }

  /**
   * \brief Regression: SPDX-FileCopyrightText entries must be detected as
   * individual single-line statements and their match range must cover the
   * full line in the original content.
   * \test
   */
  void copyscannerSpdxFullLineTest()
  {
    hCopyrightScanner sc;

    // Two lines with German umlauts (ü = \xC3\xBC), one plain ASCII line.
    const char content[] =
      "// SPDX-FileCopyrightText: 2026 Fraunhofer-Institut f\xC3\xBCr Produktionstechnik und Automatisierung IPA\n"
      "// SPDX-FileCopyrightText: 2026 Hilscher Gesellschaft f\xC3\xBCr Systemautomation mbH\n"
      "// SPDX-FileCopyrightText: 2026 Siemens AG\n";

    list<match> matches;
    sc.ScanString(content, matches);

    CPPUNIT_ASSERT_EQUAL_MESSAGE("Expected 3 SPDX matches", (size_t)3, matches.size());

    // Verify each match is active and its end position reaches the line's '\n'
    const char* lineStarts[3];
    lineStarts[0] = strstr(content, "SPDX-FileCopyrightText: 2026 Fraunhofer");
    lineStarts[1] = strstr(content, "SPDX-FileCopyrightText: 2026 Hilscher");
    lineStarts[2] = strstr(content, "SPDX-FileCopyrightText: 2026 Siemens");

    int i = 0;
    for (auto& m : matches)
    {
      CPPUNIT_ASSERT_MESSAGE(
        string("Match ") + to_string(i) + " must be active",
        m.is_enabled);

      int expectedStart = lineStarts[i] - content;
      int expectedEnd   = (int)(strchr(lineStarts[i], '\n') - content);

      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        string("Match ") + to_string(i) + " start",
        expectedStart, m.start);
      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        string("Match ") + to_string(i) + " end must reach line end",
        expectedEnd, m.end);
      ++i;
    }
  }

  /**
   * \brief Regression: license-prose strings that contain "copyright" as a
   * common noun must be deactivated, not stored as active findings.
   * \test
   * Covers five false-positive categories found in the atarashi test corpus:
   * - "copyrights appearing in" (verb-follow — appear\w* fix)
   * - "COPYRIGHT TO DETECT"      (new keyword: to)
   * - "copyright work"           (new keyword: work)
   * - "copyright protection"     (new keyword: protection)
   * - "copyrighted interfaces"   (new keyword: interfaces?)
   * - "copyright in/of/on X"     (new keywords: in, of, on)
   * Legitimate copyright statements with year or symbol must still be KEPT.
   */
  void copyscannerProseExceptionTest()
  {
    hCopyrightScanner sc;

    // All must produce NO active match (either deactivated or discarded)
    const char* prose[] = {
      "copyrights appearing in this test file\n",
      "copyrights appears in the documentation\n",
      "COPYRIGHT TO DETECT This section uses the standard header\n",
      "copyright work that can be distributed\n",
      "copyright protection under the terms of this License\n",
      "copyrighted interfaces, the original copyright holder\n",
      "copyright in the work, if the License is applied\n",
      "copyright of this Package, but belong to whoever generated\n",
      "copyright on material distributed under this License\n",
      nullptr
    };
    for (int i = 0; prose[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(prose[i], matches);
      bool hasActive = !matches.empty() && matches.front().is_enabled;
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected no active match for prose: ") + prose[i],
        !hasActive);
    }

    // These must remain KEPT — year or (C)+year acts as holder separator
    const char* valid[] = {
      "Copyright (C) 2021 Toronto Inc.\n",
      "Copyright 2021 Workday Inc.\n",
      "Copyright (C) 2021 Interface Logic Inc.\n",
      "Copyright (C) 2021 In-N-Out Burgers\n",
      nullptr
    };
    for (int i = 0; valid[i]; ++i)
    {
      list<match> matches;
      sc.ScanString(valid[i], matches);
      CPPUNIT_ASSERT_MESSAGE(
        string("Expected active match for: ") + valid[i],
        !matches.empty() && matches.front().is_enabled);
    }
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
    string actualFileContent;
    ReadFileToString("../testdata/testdata142", actualFileContent);

    vector<string> binaryStrings;
    std::stringstream *ss = new std::stringstream(actualFileContent);
    string temp;

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
    string expectedFileContent;
    ReadFileToString("../testdata/testdata142_exp", expectedFileContent);

    delete(ss);
    ss = new std::stringstream(expectedFileContent);
    vector<string> expectedStrings;
    while (std::getline(*ss, temp)) {
      expectedStrings.push_back(temp);
    }

    vector<string> actualStrings;
    for (size_t i = 0; i < matches.size(); i ++)
    {
      actualStrings.push_back(cleanMatch(actualFileContent, matches[i]));
    }

    CPPUNIT_ASSERT(expectedStrings == actualStrings);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
