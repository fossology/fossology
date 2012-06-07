/* **************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
************************************************************** */
#ifndef LIBFOCUNIT_H
#define LIBFOCUNIT_H

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <getopt.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

int focunit_main(int argc, char **argv, char *test_name, CU_SuiteInfo *suites);

/** 
 * @file libcunit.h
 * @brief FOSSology wrappers for CUnit macros.
 * These wrappers have the same name as their CUnit
 * counterparts defined in CUnit.h but substitute FO_ for CU_
 * For example, CU_ASSERT_EQUAL becomes FO_ASSERT_EQUAL
 *
 * For convienence all CU_* macros are redefined as FO_* but
 * only macros that have an expected value have a different behavior.
 * These macros will print both the actual and expected value if an 
 * assertion fails.
 */

/** Record a pass condition without performing a logical test. */
#define FO_PASS(msg) CU_PASS(msg)

/** Simple assertion.
 *  Reports failure with no other action.
 */
#define FO_ASSERT(value) CU_ASSERT(value)

/** Simple assertion.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_FATAL(value)  CU_ASSERT_FATAL(value) 

/** Simple assertion.
 *  Reports failure with no other action.
 */
#define FO_TEST(value) CU_TEST(value)

/** Simple assertion.
 *  Reports failure and causes test to abort.
 */
#define FO_TEST_FATAL(value) CU_TEST_FATAL(value)

/** Record a failure without performing a logical test. */
#define FO_FAIL(msg) CU_FAIL(msg)

/** Record a failure without performing a logical test, and abort test. */
#define FO_FAIL_FATAL(msg) CU_FAIL_FATAL(msg)

/** Asserts that value is CU_TRUE.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_TRUE(value) CU_ASSERT_TRUE(value)

/** Asserts that value is CU_TRUE.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_TRUE_FATAL(value) CU_ASSERT_TRUE_FATAL(value)

/** Asserts that value is CU_FALSE.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_FALSE(value) CU_ASSERT_FALSE(value)

/** Asserts that value is CU_FALSE.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_FALSE_FATAL(value) CU_ASSERT_FALSE_FATAL(value)

/** Asserts that actual == expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_EQUAL(actual, expected) \
  { \
    if (actual != expected) printf("%s(%d) Expected %d, got %d\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_EQUAL(actual, expected)\
  }

/** Asserts that actual == expected.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_EQUAL_FATAL(actual, expected) \
  { \
    if (actual != expected) printf("FATAL: %s(%d) Expected %d, got %d\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that actual != expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_NOT_EQUAL(actual, expected) \
  { \
    if (actual == expected) printf("%s(%d) Expected != %d got %d\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NOT_EQUAL(actual, expected)\
  }

/** Asserts that actual != expected.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_NOT_EQUAL_FATAL(actual, expected) \
  { \
    if (actual == expected) printf("FATAL: %s(%d) Expected != %d, got %d\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NOT_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that pointers actual == expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_PTR_EQUAL(actual, expected) \
  { \
    if (actual != expected) printf("%s(%d) Expected %p, got %p\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_PTR_EQUAL(actual, expected)\
  }

/** Asserts that pointers actual == expected.
 * Reports failure and causes test to abort.
 */
#define FO_ASSERT_PTR_EQUAL_FATAL(actual, expected) \
  { \
    if (actual != expected) printf("FATAL: %s(%d) Expected %p, got %p\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_PTR_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that pointers actual != expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_PTR_NOT_EQUAL(actual, expected) \
  { \
    if (actual == expected) printf("%s(%d) Expected != %p got %p\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_PTR_NOT_EQUAL(actual, expected)\
  }

/** Asserts that pointers actual != expected.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_PTR_NOT_EQUAL_FATAL(actual, expected) \
  { \
    if (actual == expected) printf("FATAL: %s(%d) Expected != %p, got %p\n",\
                 __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_PTR_NOT_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that pointer value is NULL.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_PTR_NULL(value) CU_ASSERT_PTR_NULL(value)

/** Asserts that pointer value is NULL.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_PTR_NULL_FATAL(value) CU_ASSERT_PTR_NULL_FATAL(value)

/** Asserts that pointer value is not NULL.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_PTR_NOT_NULL(value) CU_ASSERT_PTR_NOT_NULL(value)

/** Asserts that pointer value is not NULL.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_PTR_NOT_NULL_FATAL(value) CU_ASSERT_PTR_NOT_NULL_FATAL(value)

/** Asserts that string actual == expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_STRING_EQUAL(actual, expected) \
  { \
    if (0 != (strcmp((const char*)(actual), (const char*)(expected)))) \
      printf("%s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_STRING_EQUAL(actual, expected)\
  }

/** Asserts that string actual == expected.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_STRING_EQUAL_FATAL(actual, expected) \
  { \
    if (0 != (strcmp((const char*)(actual), (const char*)(expected)))) \
       printf("FATAL: %s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_STRING_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that string actual != expected.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_STRING_NOT_EQUAL(actual, expected) \
  { \
    if (0 == strcmp((const char*)(actual), (const char*)(expected))) \
       printf("%s(%d) Expected != (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_STRING_NOT_EQUAL(actual, expected)\
  }

/** Asserts that string actual != expected.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_STRING_NOT_EQUAL_FATAL(actual, expected) \
  { \
    if (0 == strcmp((const char*)(actual), (const char*)(expected))) \
       printf("FATAL: %s(%d) Expected != (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_STRING_NOT_EQUAL_FATAL(actual, expected)\
  }

/** Asserts that string actual == expected with length specified.
 *  The comparison is limited to count characters.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_NSTRING_EQUAL(actual, expected, count) \
  { \
    if (0 != strcmp((const char*)(actual), (const char*)(expected), (size_t)(count))) \
      printf("%s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NSTRING_EQUAL(actual, expected, count)\
  }

/** Asserts that string actual == expected with length specified.
 *  The comparison is limited to count characters.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_NSTRING_EQUAL_FATAL(actual, expected, count) \
  { \
    if (0 != strcmp((const char*)(actual), (const char*)(expected), (size_t)(count))) \
      printf("FATAL: %s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NSTRING_EQUAL_FATAL(actual, expected, count)\
  }

/** Asserts that string actual != expected with length specified.
 *  The comparison is limited to count characters.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_NSTRING_NOT_EQUAL(actual, expected, count) \
  { \
    if (0 == strcmp((const char*)(actual), (const char*)(expected), (size_t)(count))) \
      printf("%s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NSTRING_NOT_EQUAL(actual, expected, count)\
  }

/** Asserts that string actual != expected with length specified.
 *  The comparison is limited to count characters.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_NSTRING_NOT_EQUAL_FATAL(actual, expected, count) \
  { \
    if (0 == strcmp((const char*)(actual), (const char*)(expected), (size_t)(count))) \
      printf("FATAL: %s(%d) Expected (%s), got (%s)\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_NSTRING_NOT_EQUAL_FATAL(actual, expected, count)\
  }

/** Asserts that double actual == expected within the specified tolerance.
 *  If actual is within granularity of expected, the assertion passes.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_DOUBLE_EQUAL(actual, expected, granularity) \
  { \
    if (fabs((double)(actual) - (expected)) >= fabs((double)(granularity))) \
      printf("%s(%d) Expected %f, got %f\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_DOUBLE_EQUAL(actual, expected, granularity)\
  }

/** Asserts that double actual == expected within the specified tolerance.
 *  If actual is within granularity of expected, the assertion passes.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_DOUBLE_EQUAL_FATAL(actual, expected, granularity) \
  { \
    if (fabs((double)(actual) - (expected)) >= fabs((double)(granularity))) \
      printf("FATAL: %s(%d) Expected %f, got %f\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_DOUBLE_EQUAL_FATAL(actual, expected, granularity)\
  }

/** Asserts that double actual != expected within the specified tolerance.
 *  If actual is within granularity of expected, the assertion fails.
 *  Reports failure with no other action.
 */
#define FO_ASSERT_DOUBLE_NOT_EQUAL(actual, expected, granularity) \
  { \
    if (fabs((double)(actual) - (expected)) <= fabs((double)(granularity)))\
      printf("%s(%d) Expected %f, got %f\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_DOUBLE_NOT_EQUAL(actual, expected, granularity)\
  }

/** Asserts that double actual != expected within the specified tolerance.
 *  If actual is within granularity of expected, the assertion fails.
 *  Reports failure and causes test to abort.
 */
#define FO_ASSERT_DOUBLE_NOT_EQUAL_FATAL(actual, expected, granularity) \
  { \
    if (fabs((double)(actual) - (expected)) <= fabs((double)(granularity))) \
      printf("FATAL: %s(%d) Expected %f, got %f\n", __FILE__,__LINE__, expected, actual);\
    CU_ASSERT_DOUBLE_NOT_EQUAL_FATAL(actual, expected, granularity)\
  }

#endif
