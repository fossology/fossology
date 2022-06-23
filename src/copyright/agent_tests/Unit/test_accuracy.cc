/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file test_accuracy.cc
 * \brief Checks the accuracy of agent results
 */
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <iostream>
#include <fstream>
#include <sstream>
#include <algorithm>
#include <string>

#include "copyscan.hpp"
#include "regTypes.hpp"
#include "regscan.hpp"

/** Number of test cases (numbered from 0 to NUMTESTS-1) */
#define NUMTESTS 142

using namespace std;

/**
 * \class TestDataCheck
 * \brief Unit test driver
 */
class TestDataCheck : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(TestDataCheck);
  CPPUNIT_TEST(testDataCheck);
  CPPUNIT_TEST_SUITE_END();
protected:
  void testDataCheck();
} ;

/**
 * \brief Escape HTML special characters
 * \param[out] out Output stream
 * \param[in]  s   String to escape
 */
void HtmlEscapedOutput(ostream& out, const char* s)
{
  char c = *s;
  while (c)
  {
    switch (c)
    {
      case '<': out << "&lt;"; break;
      case '>': out << "&gt;"; break;
      case '&': out << "&amp;"; break;
      default: out << (isprint(c) ? c : ' ');
    }
    s++;
    c = *s;
  }
}

void GetReferenceResults(const string& fileName, list<match>& results)
{
  ifstream t(fileName);
  stringstream tstream;
  tstream << t.rdbuf();
  string s = tstream.str();
  string::size_type pos = 0;
  string::size_type cpos = 0;
  for (;;)
  {
    string::size_type tpos = s.find("<s>", pos);
    if (tpos == string::npos) break;
    cpos += tpos - pos;
    int start = cpos;
    tpos += 3;
    pos = s.find("</s>", tpos);
    if (pos == string::npos) break;
    cpos += pos - tpos;
    pos += 4;
    results.push_back(match(start, cpos, "r"));
  }
}

/**
 * \class overlappingMatch
 * \brief Helper to check overlapping results
 */
class overlappingMatch {
  // Criterion if match m2 is sufficiently similar to a given match m
  const match& m;
public:
  overlappingMatch(const match& mm) : m(mm) { }
  bool operator()(const match& m2) const
  {
    return !(m.end <= m2.start || m2.end <= m.start);
  }
} ;

/**
 * \brief Print results to out
 */
void Display(ostream& out, ifstream& data, list<match>& l, list<match>& lcmp, const char*prein, const char*postin, const char*prenn, const char*postnn)
{
  // Print results
  data.clear();
  for (match& m : l)
  {
    // Find in lcmp
    bool in = find_if(lcmp.begin(), lcmp.end(), overlappingMatch(m)) != lcmp.end();
    // Print match
    int len = m.end - m.start;
    char* str = new char[len+1];
    data.seekg(m.start);
    data.read(str,len);
    str[len]=0;
    out << "<p><em>[" << m.start << ":" << m.end << "]</em>" << (in ? prein : prenn);
    HtmlEscapedOutput(out, str);
    delete[] str;
    out << (in ? postin : postnn) << "</p>" << endl;
  }
}

/**
 * \brief Compare matches
 * \param a
 * \param b
 * \return True if a \< b, false otherwise
 */
bool cmpMatches(const match &a, const match &b)
{
  if (a.start < b.start)
    return true;
  if (a.start == b.start && a.end < b.end)
    return true;
  return false;
}

/**
 * \brief Test agent on every file in ../testdata/ folder
 * \test
 * -# Load test files from ../testdata/ directory and run copyright
 * and author scanners on them.
 * -# Merger the results from both scanners
 * -# Check the results against \a "_raw" results of each input file
 */
void TestDataCheck::testDataCheck()
{
  // Test all instances
  string fileNameBase = "../testdata/testdata";
  // Create a copyright scanner and an author scanner
  hCopyrightScanner hsc;
  regexScanner hauth(regAuthor::getRegex(), regAuthor::getType());

  ofstream out("results.html");

  out << "<html><head><style type=\"text/css\">"
    "body { font-family: sans-serif; } h1 { font-size: 14pt; } h2 { font-size: 10pt; } p { font-size: 10pt; } .falsepos { background-color: #FFC080; } .falseneg { background-color: #FF8080; }"
    "</style></head><body>" << endl;

  // Scan files
  for (int i = 0; i < NUMTESTS; i++)
  {
    string fileName = fileNameBase + to_string(i);
    ifstream tstream(fileName);
    list<match> lng, lauth, lrefs;
    hsc.ScanFile(fileName, lng);
    hauth.ScanFile(fileName, lauth);
    // Merge lists lng and lauth
    lng.merge(lauth, cmpMatches);
    GetReferenceResults(fileName + "_raw", lrefs);

    out << "<h1>testdata" << i << "</h1>" << endl;
    out << "<h2>HScanner</h2>" << endl;
    Display(out, tstream, lng, lrefs, "<code>", "</code>", "<code class=\"falsepos\">", "</code>");
    out << "<h2>Reference</h2>" << endl;
    Display(out, tstream, lrefs, lng, "<code>", "</code>", "<code class=\"falseneg\">", "</code>");
  }
  out << "</body></html>" << endl;
  cout << endl << "----- Test results written to results.html -----" << endl;
}

CPPUNIT_TEST_SUITE_REGISTRATION( TestDataCheck );
