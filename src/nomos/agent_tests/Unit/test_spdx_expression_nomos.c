/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <CUnit/CUnit.h>
#include <string.h>

#include "nomos.h"
#include "nomos_utils.h"
#include "spdx_expression_nomos.h"

extern struct curScan cur;

static void initExpressionTestState()
{
  cur.expressionMatches = g_array_new(FALSE, FALSE,
      sizeof(LicenseExpressionMatch));
}

static void clearExpressionTestState()
{
  for (guint i = 0; i < cur.expressionMatches->len; i++)
  {
    cleanLicenseExpressionMatch(&g_array_index(cur.expressionMatches,
        LicenseExpressionMatch, i));
  }
  g_array_free(cur.expressionMatches, TRUE);
  cur.expressionMatches = NULL;
}

void test_spdxExpressionPreScanMasksAcceptedExpression()
{
  char original[] =
      "// SPDX-License" "-Identifier: MIT OR Apache-2.0\n"
      "/* SPDX-License" "-Identifier: MIT */\n";
  char working[] =
      "// SPDX-License" "-Identifier: MIT OR Apache-2.0\n"
      "/* SPDX-License" "-Identifier: MIT */\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT OR Apache-2.0");
  CU_ASSERT_PTR_NOT_NULL(expression->astJson);
  CU_ASSERT_PTR_NULL(strstr(working, "MIT OR Apache-2.0"));
  CU_ASSERT_PTR_NOT_NULL(strstr(working, "SPDX-License" "-Identifier:"));
  CU_ASSERT_PTR_NOT_NULL(strstr(working,
      "SPDX-License" "-Identifier: MIT */"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanCanonicalizesKnownIds()
{
  char original[] = "// SPDX-License" "-Identifier: mit OR apache-2.0\n";
  char working[] = "// SPDX-License" "-Identifier: mit OR apache-2.0\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT OR Apache-2.0");

  clearExpressionTestState();
}

void test_spdxExpressionPreScanLeavesInvalidExpressionUnmasked()
{
  char original[] = "// SPDX-License" "-Identifier: MIT OR OR Apache-2.0\n";
  char working[] = "// SPDX-License" "-Identifier: MIT OR OR Apache-2.0\n";
  int count;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 0);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 0);
  CU_ASSERT_STRING_EQUAL(working, original);

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesParenthesizedLeftExpression()
{
  char original[] =
      "// SPDX-License"
      "-Identifier: (MIT OR Apache-2.0) AND BSD-2-Clause\n";
  char working[] =
      "// SPDX-License"
      "-Identifier: (MIT OR Apache-2.0) AND BSD-2-Clause\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical,
      "(MIT OR Apache-2.0) AND BSD-2-Clause");
  CU_ASSERT_PTR_NULL(strstr(working, "MIT OR Apache-2.0"));
  CU_ASSERT_PTR_NULL(strstr(working, "BSD-2-Clause"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesParenthesizedRightExpression()
{
  char original[] =
      "// SPDX-License"
      "-Identifier: MIT OR (Apache-2.0 AND BSD-2-Clause)\n";
  char working[] =
      "// SPDX-License"
      "-Identifier: MIT OR (Apache-2.0 AND BSD-2-Clause)\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical,
      "MIT OR Apache-2.0 AND BSD-2-Clause");
  CU_ASSERT_PTR_NULL(strstr(working, "Apache-2.0"));
  CU_ASSERT_PTR_NULL(strstr(working, "BSD-2-Clause"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesEitherOrNotice()
{
  char original[] =
      "Licensed under either the MIT License or Apache License 2.0.\n";
  char working[] =
      "Licensed under either the MIT License or Apache License 2.0.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT OR Apache-2.0");
  CU_ASSERT_PTR_NULL(strstr(working, "MIT License"));
  CU_ASSERT_PTR_NULL(strstr(working, "Apache License 2.0"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesAtYourOptionNotice()
{
  char original[] =
      "Licensed under the Apache License, Version 2.0 or the MIT license, "
      "at your option.\n";
  char working[] =
      "Licensed under the Apache License, Version 2.0 or the MIT license, "
      "at your option.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "Apache-2.0 OR MIT");
  CU_ASSERT_PTR_NULL(strstr(working, "Apache License"));
  CU_ASSERT_PTR_NULL(strstr(working, "MIT license"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesChoiceNotice()
{
  char original[] =
      "This software is available under your choice of the MIT License or "
      "Apache License 2.0.\n";
  char working[] =
      "This software is available under your choice of the MIT License or "
      "Apache License 2.0.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT OR Apache-2.0");
  CU_ASSERT_PTR_NULL(strstr(working, "MIT License"));
  CU_ASSERT_PTR_NULL(strstr(working, "Apache License 2.0"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesDualLicensedNotice()
{
  char original[] = "This file is dual-licensed under MIT or Apache-2.0.\n";
  char working[] = "This file is dual-licensed under MIT or Apache-2.0.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT OR Apache-2.0");
  CU_ASSERT_PTR_NULL(strstr(working, "Apache-2.0"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesBothNotice()
{
  char original[] = "This file is licensed under both MIT and BSD-2-Clause.\n";
  char working[] = "This file is licensed under both MIT and BSD-2-Clause.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical, "MIT AND BSD-2-Clause");
  CU_ASSERT_PTR_NULL(strstr(working, "BSD-2-Clause"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanHandlesWithExceptionNotice()
{
  char original[] =
      "This library is licensed under GPL-2.0-only with "
      "Classpath-exception-2.0.\n";
  char working[] =
      "This library is licensed under GPL-2.0-only with "
      "Classpath-exception-2.0.\n";
  int count;
  LicenseExpressionMatch* expression;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 1);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 1);

  expression = &g_array_index(cur.expressionMatches, LicenseExpressionMatch, 0);
  CU_ASSERT_STRING_EQUAL(expression->canonical,
      "GPL-2.0-only WITH Classpath-exception-2.0");
  CU_ASSERT_PTR_NULL(strstr(working, "Classpath-exception-2.0"));

  clearExpressionTestState();
}

void test_spdxExpressionPreScanRejectsContainsStatement()
{
  char original[] = "This package contains MIT code and Apache code.\n";
  char working[] = "This package contains MIT code and Apache code.\n";
  int count;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 0);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 0);
  CU_ASSERT_STRING_EQUAL(working, original);

  clearExpressionTestState();
}

void test_spdxExpressionPreScanRejectsCompatibilityStatement()
{
  char original[] = "Compatible with MIT or Apache licensed projects.\n";
  char working[] = "Compatible with MIT or Apache licensed projects.\n";
  int count;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 0);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 0);
  CU_ASSERT_STRING_EQUAL(working, original);

  clearExpressionTestState();
}

void test_spdxExpressionPreScanRejectsBundledSoftwareStatement()
{
  char original[] =
      "This product bundles software available under MIT and Apache-2.0.\n";
  char working[] =
      "This product bundles software available under MIT and Apache-2.0.\n";
  int count;

  initExpressionTestState();

  count = extractSpdxExpressionFindings(original, working,
      (int)strlen(original));

  CU_ASSERT_EQUAL(count, 0);
  CU_ASSERT_EQUAL(cur.expressionMatches->len, 0);
  CU_ASSERT_STRING_EQUAL(working, original);

  clearExpressionTestState();
}

CU_TestInfo spdx_expression_nomos_testcases[] = {
  {"Testing SPDX expression pre-scan masks accepted expression:",
      test_spdxExpressionPreScanMasksAcceptedExpression},
  {"Testing SPDX expression pre-scan canonicalizes known ids:",
      test_spdxExpressionPreScanCanonicalizesKnownIds},
  {"Testing SPDX expression pre-scan leaves invalid expression unmasked:",
      test_spdxExpressionPreScanLeavesInvalidExpressionUnmasked},
  {"Testing SPDX expression pre-scan handles parenthesized left expression:",
      test_spdxExpressionPreScanHandlesParenthesizedLeftExpression},
  {"Testing SPDX expression pre-scan handles parenthesized right expression:",
      test_spdxExpressionPreScanHandlesParenthesizedRightExpression},
  {"Testing SPDX expression pre-scan handles either/or notice:",
      test_spdxExpressionPreScanHandlesEitherOrNotice},
  {"Testing SPDX expression pre-scan handles at-your-option notice:",
      test_spdxExpressionPreScanHandlesAtYourOptionNotice},
  {"Testing SPDX expression pre-scan handles choice notice:",
      test_spdxExpressionPreScanHandlesChoiceNotice},
  {"Testing SPDX expression pre-scan handles dual-licensed notice:",
      test_spdxExpressionPreScanHandlesDualLicensedNotice},
  {"Testing SPDX expression pre-scan handles both notice:",
      test_spdxExpressionPreScanHandlesBothNotice},
  {"Testing SPDX expression pre-scan handles WITH exception notice:",
      test_spdxExpressionPreScanHandlesWithExceptionNotice},
  {"Testing SPDX expression pre-scan rejects contains statement:",
      test_spdxExpressionPreScanRejectsContainsStatement},
  {"Testing SPDX expression pre-scan rejects compatibility statement:",
      test_spdxExpressionPreScanRejectsCompatibilityStatement},
  {"Testing SPDX expression pre-scan rejects bundled software statement:",
      test_spdxExpressionPreScanRejectsBundledSoftwareStatement},
  CU_TEST_INFO_NULL
};
