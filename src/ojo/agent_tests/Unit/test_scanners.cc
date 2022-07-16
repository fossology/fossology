/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <vector>
#include <cstring>
#include <ostream>
#include <algorithm>

#include "OjoAgent.hpp"

using namespace std;

ostream& operator<<(ostream& out, const vector<ojomatch>& l)
{
  for (auto m : l)
    out << '[' << m.start << ':' << m.end << "]: '" << m.content << "'";
  return out;
}

/**
 * \brief test data
 */
// REUSE-IgnoreStart
const std::string testContent = "!/usr/bin/env python3\n"
    "# -*- coding: utf-8 -*-\n"
    "\n"
    "\"\"\"\n"
    "\n"
    "SPDX-License-Identifier: GPL-2.0\n"
    "\n"
    "This program is free software; you can redistribute it and/or\n"
    "modify it under the terms of the GNU General Public License\n"
    "version 2 as published by the Free Software Foundation.\n"
    "This program is distributed in the hope that it will be useful,\n"
    "but WITHOUT ANY WARRANTY; without even the implied warranty of\n"
    "MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n"
    "GNU General Public License for more details.\n"
    "\n"
    "You should have received a copy of the GNU General Public License along\n"
    "with this program; if not, write to the Free Software Foundation, Inc.,\n"
    "51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.\n"
    "\"\"\"";
// REUSE-IgnoreEnd

const std::string testContentWithoutIdentifier = "!/usr/bin/env python3\n"
    "# -*- coding: utf-8 -*-\n"
    "\n"
    "\"\"\"\n"
    "This program is free software; you can redistribute it and/or\n"
    "modify it under the terms of the GNU General Public License\n"
    "version 2 as published by the Free Software Foundation.\n"
    "This program is distributed in the hope that it will be useful,\n"
    "but WITHOUT ANY WARRANTY; without even the implied warranty of\n"
    "MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n"
    "GNU General Public License for more details.\n"
    "\n"
    "You should have received a copy of the GNU General Public License along\n"
    "with this program; if not, write to the Free Software Foundation, Inc.,\n"
    "51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.\n"
    "\"\"\"";

// REUSE-IgnoreStart
const std::string multipleSpdxLicense = "!/usr/bin/env python3\n"
    "# -*- coding: utf-8 -*-\n"
    "\n"
    "\"\"\"\n"
    "\n"
    "SPDX-License-Identifier: GPL-2.0 AND LGPL-2.1+ WITH Classpath-exception-2.0\n"
    "\n"
    "This program is free software; you can redistribute it and/or\n"
    "modify it under the terms of the GNU General Public License\n"
    "version 2 as published by the Free Software Foundation.\n"
    "This program is distributed in the hope that it will be useful,\n"
    "but WITHOUT ANY WARRANTY; without even the implied warranty of\n"
    "MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n"
    "GNU General Public License for more details.\n"
    "\n"
    "You should have received a copy of the GNU General Public License along\n"
    "with this program; if not, write to the Free Software Foundation, Inc.,\n"
    "51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.\n"
    "\"\"\"";
// REUSE-IgnoreEnd

class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (spdxContentTest);
  CPPUNIT_TEST (nonSpdxContentTest);
  CPPUNIT_TEST (multiSpdxContentTest);

  CPPUNIT_TEST_SUITE_END ();

private:
  /**
   * \brief Runs a scan on content and check matches against expectedStrings
   * \param content         Content to scan
   * \param expectedStrings Expected strings from scanner result
   */
  void scannerTest (const string& content, vector<string> expectedStrings)
  {
    // Temporary store the content in a file
    char* tempFilePath = strdup("/tmp/ojo-XXXXXX");
    mkstemp(tempFilePath);

    fstream tempFile(tempFilePath);
    tempFile << content;
    tempFile.close();

    OjoAgent ojo;
    vector<ojomatch> matches = ojo.processFile(tempFilePath);

    // Remove the temporary file
    unlink(tempFilePath);

    CPPUNIT_ASSERT_EQUAL(expectedStrings.size(), matches.size());

    for (auto expected : expectedStrings)
    {
      CPPUNIT_ASSERT(std::find(matches.begin(), matches.end(), expected) != matches.end());
    }
  }

protected:
  /**
   * \brief Test ojo on content with SPDX license
   * \test
   * -# Create an OjoAgent object
   * -# Load test data and expected data
   * -# Test using scannerTest()
   */
  void spdxContentTest()
  {
    scannerTest(testContent, {"GPL-2.0"});
  }

  /**
   * \brief Test ojo on content without SPDX license
   * \test
   * -# Create an OjoAgent object
   * -# Load test data without SPDX license and expected data
   * -# Test using scannerTest()
   */
  void nonSpdxContentTest()
  {
    scannerTest(testContentWithoutIdentifier, {});
  }

  /**
   * \brief Test ojo on content with multiple SPDX license
   * \test
   * -# Create an OjoAgent object
   * -# Load test data with multiple SPDX license and expected data
   * -# Test using scannerTest()
   */
  void multiSpdxContentTest()
  {
    scannerTest(multipleSpdxLicense, {"GPL-2.0", "LGPL-2.1+",
      "Classpath-exception-2.0", "Dual-license"});
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
