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

protected:
  void testDataCheck (void) {
    string baseFileName("../testdata/testdata");

    auto type = regCopyright::getType();
    vector<RegexMatcher> copyrights = { RegexMatcher(type, regCopyright::getRegex()) };

    vector<RegexMatcher> expectedMatcher = { RegexMatcher(type, "<s>(.*?)<\\/s>", 1) };

    for (unsigned int i = 0; i<139; ++i)
    {
      const string currentFileName = baseFileName + std::to_string(i);

      fo::File currentFile(i, currentFileName);
      fo::File expectedMatchesFile(i, currentFileName + "_raw");

      CPPUNIT_ASSERT(currentFile.isReadable());
      CPPUNIT_ASSERT(expectedMatchesFile.isReadable());

      vector<CopyrightMatch> matches = matchStringToRegexes(currentFile.getContent(), copyrights);
      vector<CopyrightMatch> expected = correctPositions(matchStringToRegexes(expectedMatchesFile.getContent(), expectedMatcher));

      CPPUNIT_ASSERT_EQUAL(expected, matches);
    }
  };

};

CPPUNIT_TEST_SUITE_REGISTRATION( TestDataCheck );
