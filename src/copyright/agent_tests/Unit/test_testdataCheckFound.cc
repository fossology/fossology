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
#include <files.hpp>

using namespace std;

class StatsAccumulator
{

public:
  StatsAccumulator() : matched(0), falsePositives(0), falseNegatives(0) {}

  unsigned int getMatched() const { return matched; }
  unsigned int getFalsePositives() const { return falsePositives; }
  unsigned int getFalseNegatives() const { return falseNegatives; }

  void incrementMatched(unsigned int matched = 1) { StatsAccumulator::matched += matched; }
  void incrementFalsePositives(unsigned int falsePositives = 1) { StatsAccumulator::falsePositives += falsePositives; }
  void incrementFalseNegatives(unsigned int falseNegatives = 1) { StatsAccumulator::falseNegatives += falseNegatives; }

private:
  unsigned matched;
  unsigned falsePositives;
  unsigned falseNegatives;
};

ostream& operator<<(ostream& os, const StatsAccumulator& accumulator) {
  os << "Total:   " << accumulator.getMatched() + accumulator.getFalsePositives() << endl;
  os << "Matches: " << accumulator.getMatched() << endl;
  os << "False +: " << accumulator.getFalsePositives() << endl;
  os << "False -: " << accumulator.getFalseNegatives() << endl;
  return os;
}

class TestDataCheck : public CPPUNIT_NS :: TestFixture
{
  CPPUNIT_TEST_SUITE (TestDataCheck);
    CPPUNIT_TEST (testDataCheck);

  CPPUNIT_TEST_SUITE_END ();

private:
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

  void checkMatches(const vector<CopyrightMatch>& matches, const vector<CopyrightMatch>& expected, StatsAccumulator& accumulator, const string& logText, ostream& log)
  {
    const unsigned long legalMatchesSize = matches.size();
    const unsigned long expectedMatchesSize = expected.size();

    if (legalMatchesSize == 0)
    {
      log << logText << ": not found: " << expected << endl;
      accumulator.incrementFalseNegatives(expectedMatchesSize);
      return;
    }

    if (expectedMatchesSize == 0) {
      log << logText << ": bad found: " << matches << endl;
      accumulator.incrementFalsePositives(legalMatchesSize);
      return;
    }

    size_t iMatches = 0;
    size_t iExpected = 0;

    while(iMatches < legalMatchesSize && iExpected < expectedMatchesSize)
    {
      const CopyrightMatch& currentExpected = expected[iExpected];
      const CopyrightMatch& currentMatched = matches[iMatches];

      if ((currentMatched <= currentExpected) || (currentExpected <= currentMatched)) {
        accumulator.incrementMatched();
        ++iMatches;
        ++iExpected;
      }
      else if (currentExpected.getStart() > currentMatched.getStart())
      {
        log << logText << ": bad found: " << currentMatched << endl;
        accumulator.incrementFalsePositives();
        ++iMatches;
      }
      else //if (currentExpected.getStart() < currentMatched.getStart())
      {
        log << logText << ": not found: " << currentExpected << endl;
        accumulator.incrementFalseNegatives();
        ++iExpected;
      }
    }

    while (iMatches < legalMatchesSize)
    {
      log << logText << ": bad found: " << matches[iMatches]<< endl;
      accumulator.incrementFalsePositives();
      ++iMatches;
    }

    while (iExpected < expectedMatchesSize)
    {
      log << logText << ": not found: " << expected[iExpected]<< endl;
      accumulator.incrementFalseNegatives();
      ++iExpected;
    }

  }

protected:
  void testDataCheck (void) {
    string baseFileName("../testdata/testdata");

    auto type = regCopyright::getType();
    vector<RegexMatcher> copyrights = { RegexMatcher(type, regCopyright::getRegex()) };
    vector<RegexMatcher> cheat = {
      RegexMatcher(type, regEmail::getRegex()),
      RegexMatcher(type, regURL::getRegex())
    };

    vector<RegexMatcher> expectedMatcher = { RegexMatcher(type, "<s>(.*?)<\\/s>", 1) };

    StatsAccumulator accumulator;

    for (unsigned int i = 0; i<140; ++i)
    {
      const string currentFileName = baseFileName + std::to_string(i);

      fo::File currentFile(i, currentFileName);
      fo::File expectedMatchesFile(i, currentFileName + "_raw");

      CPPUNIT_ASSERT(currentFile.isReadable());
      CPPUNIT_ASSERT(expectedMatchesFile.isReadable());

      vector<CopyrightMatch> matches = matchStringToRegexes(currentFile.getContent(), copyrights);
      vector<CopyrightMatch> expected = correctPositions(matchStringToRegexes(expectedMatchesFile.getContent(), expectedMatcher));

      vector<CopyrightMatch> cheatMatches = matchStringToRegexes(currentFile.getContent(), cheat);
      accumulator.incrementMatched(cheat.size());

#if 1
      std::ostream& os = cout;
#else
      std::ostream os(0);
#endif
      checkMatches(matches, expected, accumulator, currentFileName, os);
    }

    cout << endl;
    cout << accumulator << endl;
  };

};

CPPUNIT_TEST_SUITE_REGISTRATION( TestDataCheck );
