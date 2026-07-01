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
#include "spdx_expression_parser.h"

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

// REUSE-IgnoreStart
const std::string spdxOrExpression = "/*\n"
    " * SPDX-License-Identifier: MIT OR Apache-2.0\n"
    " */\n"
    "\n"
    "int main() { return 0; }\n";
// REUSE-IgnoreEnd

// REUSE-IgnoreStart
const std::string spdxAndExpression = "/*\n"
    " * Unique test marker: spdx-mit-and-bsd-2026-06-05-a\n"
    " *\n"
    " * SPDX-License-Identifier: MIT AND BSD-2-Clause\n"
    " */\n"
    "\n"
    "int main(void) { return 101; }\n";
// REUSE-IgnoreEnd

// REUSE-IgnoreStart
const std::string spdxParenthesizedExpression = "/*\n"
    " * SPDX-License-Identifier: (MIT OR Apache-2.0) AND BSD-2-Clause\n"
    " */\n"
    "\n"
    "int main() { return 0; }\n";
// REUSE-IgnoreEnd

// REUSE-IgnoreStart
const std::string spdxParserIntegrationContent = "/*\n"
    " * Parser integration test file for OJO + Nomos.\n"
    " *\n"
    " * SPDX-License-Identifier: MIT OR Apache-2.0\n"
    " * SPDX-License-Identifier: MIT AND BSD-2-Clause\n"
    " * SPDX-License-Identifier: GPL-2.0-only WITH Classpath-exception-2.0\n"
    " * SPDX-License-Identifier: (MIT OR Apache-2.0) AND BSD-2-Clause\n"
    " * SPDX-License-Identifier: MIT OR (Apache-2.0 AND BSD-2-Clause)\n"
    " *\n"
    " * Licensed under either the MIT License or Apache License 2.0.\n"
    " * This file is licensed under both MIT and BSD-2-Clause.\n"
    " */\n"
    "\n"
    "int main(void) { return 0; }\n";
// REUSE-IgnoreEnd

class scannerTestSuite : public CPPUNIT_NS :: TestFixture {
  CPPUNIT_TEST_SUITE (scannerTestSuite);
  CPPUNIT_TEST (parserContractValidCorpusTest);
  CPPUNIT_TEST (parserContractInvalidCorpusTest);
  CPPUNIT_TEST (spdxContentTest);
  CPPUNIT_TEST (nonSpdxContentTest);
  CPPUNIT_TEST (multiSpdxContentTest);
  CPPUNIT_TEST (orExpressionContentTest);
  CPPUNIT_TEST (andExpressionContentTest);
  CPPUNIT_TEST (parenthesizedExpressionContentTest);
  CPPUNIT_TEST (parserIntegrationContentTest);

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

  void parserValidTest(const string& input, const string& expectedCanonical,
      const string& expectedAst)
  {
    SpdxExpressionResult result = spdx_expression_parse(input.c_str());

    CPPUNIT_ASSERT(result.valid);
    CPPUNIT_ASSERT(result.canonical != NULL);
    CPPUNIT_ASSERT(result.ast_json != NULL);
    CPPUNIT_ASSERT_EQUAL(expectedCanonical, string(result.canonical));
    CPPUNIT_ASSERT_EQUAL(expectedAst, string(result.ast_json));

    spdx_expression_result_free(&result);
  }

  void parserInvalidTest(const string& input, const string& expectedError)
  {
    SpdxExpressionResult result = spdx_expression_parse(input.c_str());

    CPPUNIT_ASSERT(!result.valid);
    CPPUNIT_ASSERT(result.error_code != NULL);
    CPPUNIT_ASSERT_EQUAL(expectedError, string(result.error_code));

    spdx_expression_result_free(&result);
  }

protected:
  /**
   * \brief Test parser against valid shared contract examples.
   * \test
   * -# Parse representative valid expressions from the shared corpus
   * -# Check canonical output and exact AST JSON
   */
  void parserContractValidCorpusTest()
  {
    parserValidTest("MIT", "MIT",
      "{\"type\":\"license\",\"id\":\"MIT\"}");
    parserValidTest("MIT OR Apache-2.0", "MIT OR Apache-2.0",
      "{\"type\":\"OR\",\"left\":{\"type\":\"license\",\"id\":\"MIT\"},\"right\":{\"type\":\"license\",\"id\":\"Apache-2.0\"}}");
    parserValidTest("mit OR apache-2.0", "MIT OR Apache-2.0",
      "{\"type\":\"OR\",\"left\":{\"type\":\"license\",\"id\":\"MIT\"},\"right\":{\"type\":\"license\",\"id\":\"Apache-2.0\"}}");
    parserValidTest("GPL-2.0-only WITH Classpath-exception-2.0",
      "GPL-2.0-only WITH Classpath-exception-2.0",
      "{\"type\":\"WITH\",\"license\":{\"type\":\"license\",\"id\":\"GPL-2.0-only\"},\"exception\":{\"type\":\"exception\",\"id\":\"Classpath-exception-2.0\"}}");
    parserValidTest("GPL-2.0 AND LGPL-2.1+ WITH Classpath-exception-2.0",
      "GPL-2.0 AND LGPL-2.1+ WITH Classpath-exception-2.0",
      "{\"type\":\"AND\",\"left\":{\"type\":\"license\",\"id\":\"GPL-2.0\"},\"right\":{\"type\":\"WITH\",\"license\":{\"type\":\"license\",\"id\":\"LGPL-2.1+\"},\"exception\":{\"type\":\"exception\",\"id\":\"Classpath-exception-2.0\"}}}");
    parserValidTest("(MIT OR Apache-2.0) AND BSD-2-Clause",
      "(MIT OR Apache-2.0) AND BSD-2-Clause",
      "{\"type\":\"AND\",\"left\":{\"type\":\"OR\",\"left\":{\"type\":\"license\",\"id\":\"MIT\"},\"right\":{\"type\":\"license\",\"id\":\"Apache-2.0\"}},\"right\":{\"type\":\"license\",\"id\":\"BSD-2-Clause\"}}");
    parserValidTest("MIT OR Apache-2.0 AND BSD-2-Clause",
      "MIT OR Apache-2.0 AND BSD-2-Clause",
      "{\"type\":\"OR\",\"left\":{\"type\":\"license\",\"id\":\"MIT\"},\"right\":{\"type\":\"AND\",\"left\":{\"type\":\"license\",\"id\":\"Apache-2.0\"},\"right\":{\"type\":\"license\",\"id\":\"BSD-2-Clause\"}}}");
    parserValidTest("LicenseRef-Proprietary", "LicenseRef-Proprietary",
      "{\"type\":\"licenseRef\",\"id\":\"LicenseRef-Proprietary\"}");
    parserValidTest("DocumentRef-third-party:LicenseRef-Custom",
      "DocumentRef-third-party:LicenseRef-Custom",
      "{\"type\":\"licenseRef\",\"id\":\"DocumentRef-third-party:LicenseRef-Custom\"}");
    parserValidTest("none", "NONE",
      "{\"type\":\"special\",\"id\":\"NONE\"}");
    parserValidTest("NOASSERTION", "NOASSERTION",
      "{\"type\":\"special\",\"id\":\"NOASSERTION\"}");
  }

  /**
   * \brief Test parser against invalid shared contract examples.
   * \test
   * -# Parse representative invalid expressions from the shared corpus
   * -# Check exact stable error codes
   */
  void parserContractInvalidCorpusTest()
  {
    parserInvalidTest("", "empty_expression");
    parserInvalidTest("MIT OR OR Apache-2.0", "expected_license");
    parserInvalidTest("(MIT OR Apache-2.0", "missing_closing_parenthesis");
    parserInvalidTest("MIT AND NONE", "special_license_must_stand_alone");
    parserInvalidTest("(MIT OR Apache-2.0) WITH Classpath-exception-2.0",
      "with_requires_simple_license");
  }

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
    scannerTest(multipleSpdxLicense,
      {"GPL-2.0 AND LGPL-2.1+ WITH Classpath-exception-2.0"});
  }

  /**
   * \brief Test ojo on content with an SPDX OR expression.
   * \test
   * -# Load test data with an OR expression
   * -# Test that OJO returns one expression finding
   */
  void orExpressionContentTest()
  {
    scannerTest(spdxOrExpression, {"MIT OR Apache-2.0"});
  }

  /**
   * \brief Test ojo on content with a plain SPDX AND expression.
   * \test
   * -# Load test data with an AND expression
   * -# Test that OJO emits the full expression, not only Nomos-style members
   */
  void andExpressionContentTest()
  {
    scannerTest(spdxAndExpression, {"MIT AND BSD-2-Clause"});
  }

  /**
   * \brief Test ojo on content with a parenthesized SPDX expression.
   * \test
   * -# Load test data with a parenthesized expression
   * -# Test that OJO keeps the expression meaning in canonical output
   */
  void parenthesizedExpressionContentTest()
  {
    scannerTest(spdxParenthesizedExpression,
      {"(MIT OR Apache-2.0) AND BSD-2-Clause"});
  }

  /**
   * \brief Test ojo on a mixed parser integration file.
   * \test
   * -# Load test data with multiple SPDX identifiers and natural-language text
   * -# Test that OJO reports only the SPDX identifiers it owns
   */
  void parserIntegrationContentTest()
  {
    scannerTest(spdxParserIntegrationContent,
      {"MIT OR Apache-2.0",
       "MIT AND BSD-2-Clause",
       "GPL-2.0-only WITH Classpath-exception-2.0",
       "(MIT OR Apache-2.0) AND BSD-2-Clause",
       "MIT OR Apache-2.0 AND BSD-2-Clause"});
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION( scannerTestSuite );
