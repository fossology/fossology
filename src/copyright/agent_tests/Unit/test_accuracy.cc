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
#include <algorithm>
#include <files.hpp>

#define TESTFILES_BEGIN 0
#define TESTFILES_END 140

//#define SHOWALLDIFFS

using namespace std;

bool compMatchByStart(const CopyrightMatch& a, const CopyrightMatch& b)
{
  return a.getStart() < b.getStart();
}

class StatsAccumulator
{

public:
  StatsAccumulator() : matched(0), falsePositives(), falseNegatives() {}

  size_t getMatched() const { return matched; }
  vector<CopyrightMatch> getFalsePositives() const { return falsePositives; }
  vector<CopyrightMatch> getFalseNegatives() const { return falseNegatives; }

  void incrementMatched(size_t matched = 1) { StatsAccumulator::matched += matched; }
  void incrementFalsePositives(const CopyrightMatch& match) { insertOrdered(falsePositives, match); }
  void incrementFalseNegatives(const CopyrightMatch& match) { insertOrdered(falseNegatives, match); }

  void incrementFalsePositives(const vector<CopyrightMatch>& matches) {
    for (auto it = matches.begin(); it != matches.end(); ++it) incrementFalsePositives(*it);
  }

  void incrementFalseNegatives(const vector<CopyrightMatch>& matches)
  {
    for (auto it = matches.begin(); it != matches.end(); ++it) incrementFalseNegatives(*it);
  }

  StatsAccumulator& operator+=(const StatsAccumulator& rhs) {
    if (this != &rhs) {
      this->incrementFalsePositives(rhs.getFalsePositives());
      this->incrementFalseNegatives(rhs.getFalseNegatives());
      this->incrementMatched(rhs.getMatched());
    }
    return *this;
  }

private:

  void insertOrdered(vector<CopyrightMatch>& dest, const CopyrightMatch& element)
  {
    auto position = std::lower_bound(dest.begin(), dest.end(), element, compMatchByStart);
    dest.insert(position, element);
  }

  size_t matched;
  vector<CopyrightMatch> falsePositives;
  vector<CopyrightMatch> falseNegatives;

};

ostream& operator<<(ostream& os, const StatsAccumulator& accumulator) {
  os << "Total:   " << accumulator.getMatched() + accumulator.getFalsePositives().size() << endl;
  os << "Matches: " << accumulator.getMatched() << endl;
  os << "False +: " << accumulator.getFalsePositives().size() << endl;
  os << "False -: " << accumulator.getFalseNegatives().size() << endl;
  return os;
}

class TestDataCheck : public CPPUNIT_NS :: TestFixture
{
  CPPUNIT_TEST_SUITE (TestDataCheck);
    CPPUNIT_TEST (testDataCheck);
    //CPPUNIT_TEST (testChanges);

  CPPUNIT_TEST_SUITE_END ();

private:


  std::string getNewRegex()
  {
#define EMAILRGX  "[\\<\\(]?([\\w\\-\\.\\+]{1,100}@[\\w\\-\\.\\+]{1,100}\\.[a-z]{1,4})[\\>\\)]?"
#define NAME              "(([[:alpha:]]{1,3}\\.)|([[:alpha:]]+)|(" EMAILRGX "))"
#define SPACECLS          "[\\t ]"
#define SPACES            SPACECLS "+"
#define SPACESALL         "[[:space:]]*"
#define PUNCTORSPACE      "[[:punct:][:space:]]"
#define NAMESLIST         NAME "(([-, &]+)" NAME ")*"
#define DATE              "([[:digit:]]{4,4}|[[:digit:]]{1,2})"
#define DATESLIST DATE    "(([[:punct:][:space:]-]+)" DATE ")*"
#define COPYR_SYM_ALONE   "©|\xA9|\xC2\xA9" "|\\$\xB8|\xED\x92\xB8|\\$\xD2|\xE2\x93\x92" "|\\$\x9E|\xE2\x92\x9E"
#define COPYR_SYM         "(\\(c\\)|" COPYR_SYM_ALONE ")"
#define COPYR_TXT         "copyright(s)?"

    return std::string(
      "("
      "("
        "("
          COPYR_SYM "(" SPACESALL COPYR_TXT "|" SPACES "(19|20)[[:digit:]]{2,2}" ")"
          "|" COPYR_TXT ":?" SPACESALL COPYR_SYM
          "|" COPYR_TXT
          "|" COPYR_SYM_ALONE
        ")"
        "("
          SPACES
          "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp|@author)*)"
        ")?"
        "("
          PUNCTORSPACE "?"
          SPACESALL
          DATESLIST
        ")?"
        "("
          PUNCTORSPACE "?"
          SPACESALL
          NAMESLIST
        ")"
        "(" PUNCTORSPACE"*" "all" SPACES "rights" SPACES "reserved)?"
      ")|("
        "("
          "((author|contributor|maintainer)s?)"
          "|((written|contribut(ed|ions?)|maintained|modifi(?:ed|cations?)|put" SPACES "together)" SPACES "by)"
        ")"
        "[:]?"
        SPACESALL
        NAMESLIST
      ")"
      ")"
      "[.]?"
    );
  }

  vector<CopyrightMatch> correctPositions(const vector<CopyrightMatch>& input)
  {
    vector<CopyrightMatch> output;

    for (size_t i=0; i<input.size(); ++i)
    {
      const CopyrightMatch& inputEl = input[i];

      const size_t newStart = inputEl.getStart() - (strlen("<s>") + strlen("<s></s>") * i);

      output.push_back(
        CopyrightMatch(inputEl.getContent(), inputEl.getType(),
          newStart, inputEl.getLength())
      );
    }

    return output;
  }

  StatsAccumulator checkMatches(const vector<CopyrightMatch>& matches, const vector<CopyrightMatch>& expected)
  {
    StatsAccumulator accumulator;

    const unsigned long matchesSize = matches.size();
    const unsigned long expectedSize = expected.size();

    size_t iMatches = 0;
    size_t iExpected = 0;

    while(iMatches < matchesSize || iExpected < expectedSize)
    {
      if (iMatches == matchesSize)
      {
        while (iExpected < expectedSize)
        {
          accumulator.incrementFalseNegatives(expected[iExpected]);
          ++iExpected;
        }
        continue;
      }

      if (iExpected == expectedSize)
      {
        while (iMatches < matchesSize)
        {
          accumulator.incrementFalsePositives(matches[iMatches]);
          ++iMatches;
        }
        continue;
      }

      const CopyrightMatch& currentExpected = expected[iExpected];
      const CopyrightMatch& currentMatched = matches[iMatches];

      if ((currentMatched <= currentExpected) || (currentMatched >= currentExpected)) {
        accumulator.incrementMatched();
        ++iMatches;
        ++iExpected;
      }
      else if (currentExpected.getStart() > currentMatched.getStart())
      {
        accumulator.incrementFalsePositives(currentMatched);
        ++iMatches;
      }
      else //if (currentExpected.getStart() < currentMatched.getStart())
      {
        accumulator.incrementFalseNegatives(currentExpected);
        ++iExpected;
      }
    }

    return accumulator;
  }

  StatsAccumulator runtestWith(string baseFileName, vector<RegexMatcher> copyrights, vector<RegexMatcher> untested, vector<RegexMatcher> expectedMatcher)
  {
    StatsAccumulator accumulator;

    for (unsigned int i = TESTFILES_BEGIN; i < TESTFILES_END; ++i)
    {
      const string currentFileName = baseFileName + to_string(i);

      fo::File currentFile(i, currentFileName);
      fo::File expectedMatchesFile(i, currentFileName + "_raw");

      CPPUNIT_ASSERT(currentFile.isReadable());
      CPPUNIT_ASSERT(expectedMatchesFile.isReadable());

      vector<CopyrightMatch> matches = matchStringToRegexes(currentFile.getContent(), copyrights);
      vector<CopyrightMatch> expected = correctPositions(matchStringToRegexes(expectedMatchesFile.getContent(), expectedMatcher));

      vector<CopyrightMatch> cheatMatches = matchStringToRegexes(currentFile.getContent(), untested);
      accumulator.incrementMatched(untested.size());

      const StatsAccumulator fileStats = checkMatches(matches, expected);

      const vector<CopyrightMatch> falseNegatives = fileStats.getFalseNegatives();
      const vector<CopyrightMatch> falsePositives = fileStats.getFalsePositives();

#ifdef SHOWALLDIFFS
      cout << currentFileName << ": " << fileStats << endl;
      if (!falseNegatives.empty())
      {
        cout << "negatives: " << falseNegatives << endl;
      }
      if (!falsePositives.empty())
      {
        cout << "positives: " << falsePositives << endl;
      }
#endif

      accumulator += fileStats;
    }

    return accumulator;
  }

protected:
  void testChanges (void) {
    string baseFileName("../testdata/testdata");

    auto type = regCopyright::getType();
    vector<RegexMatcher> copyrights = { RegexMatcher(type, regCopyright::getRegex()) };
    vector<RegexMatcher> cheat = {
      RegexMatcher(type, regEmail::getRegex()),
      RegexMatcher(type, regURL::getRegex())
    };

    vector<RegexMatcher> expectedMatcher = { RegexMatcher(type, "<s>(.*?)<\\/s>", 1) };

    const StatsAccumulator accumulator  = runtestWith(baseFileName, copyrights, cheat, expectedMatcher);

    const vector<RegexMatcher> changing = { RegexMatcher(type, getNewRegex()) };
    const StatsAccumulator accumulator2 = runtestWith(baseFileName, changing, cheat, expectedMatcher);

    cout << endl
     << "Current Regex:" << endl
     << accumulator << endl
     << endl
     << "New     Regex:" << endl
     << accumulator2 << endl;

    StatsAccumulator diffPositives = checkMatches(accumulator2.getFalsePositives(), accumulator.getFalsePositives());
    StatsAccumulator diffNegatives = checkMatches(accumulator2.getFalseNegatives(), accumulator.getFalseNegatives());

    cout << endl
    << "removed    False Positives: " << diffPositives.getFalseNegatives() << endl
    << "removed    False Negatives: " << diffNegatives.getFalseNegatives() << endl
    << "introduced False Positives: " << diffPositives.getFalsePositives() << endl
    << "introduced False Negatives: " << diffNegatives.getFalsePositives() << endl
    << endl;

  };

  void testDataCheck (void) {
    string baseFileName("../testdata/testdata");

    auto type = regCopyright::getType();
    vector<RegexMatcher> copyrights = { RegexMatcher(type, regCopyright::getRegex()) };
    vector<RegexMatcher> cheat = {
      RegexMatcher(type, regEmail::getRegex()),
      RegexMatcher(type, regURL::getRegex())
    };

    vector<RegexMatcher> expectedMatcher = { RegexMatcher(type, "<s>(.*?)<\\/s>", 1) };

    const StatsAccumulator accumulator = runtestWith(baseFileName, copyrights, cheat, expectedMatcher);

    cout << endl << accumulator << endl;
  }

};

CPPUNIT_TEST_SUITE_REGISTRATION( TestDataCheck );
