/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
 */
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "ClearingDecisionUtils.hpp"

namespace cdu = fo::ClearingDecisionUtils;

class IsValidIdentifierTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(IsValidIdentifierTest);
  CPPUNIT_TEST(testEmptyStringIsInvalid);
  CPPUNIT_TEST(testLowercaseLettersAreValid);
  CPPUNIT_TEST(testUppercaseLettersAreValid);
  CPPUNIT_TEST(testDigitsAreValid);
  CPPUNIT_TEST(testUnderscoreIsValid);
  CPPUNIT_TEST(testMixedAlphanumericUnderscoreIsValid);
  CPPUNIT_TEST(testSpaceIsInvalid);
  CPPUNIT_TEST(testHyphenIsInvalid);
  CPPUNIT_TEST(testDotIsInvalid);
  CPPUNIT_TEST(testSemicolonIsInvalid);
  CPPUNIT_TEST(testSqlInjectionPatternIsInvalid);
  CPPUNIT_TEST(testKnownTableNamesAreValid);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEmptyStringIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier(""));
  }

  void testLowercaseLettersAreValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("abc"));
  }

  void testUppercaseLettersAreValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("ABC"));
  }

  void testDigitsAreValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("123"));
  }

  void testUnderscoreIsValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("_"));
  }

  void testMixedAlphanumericUnderscoreIsValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("uploadtree_a"));
    CPPUNIT_ASSERT(cdu::isValidIdentifier("upload_fk_123"));
  }

  void testSpaceIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("upload tree"));
  }

  void testHyphenIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("upload-tree"));
  }

  void testDotIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("public.uploadtree"));
  }

  void testSemicolonIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("foo;DROP TABLE uploadtree"));
  }

  void testSqlInjectionPatternIsInvalid()
  {
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("t WHERE 1=1--"));
    CPPUNIT_ASSERT(!cdu::isValidIdentifier("t UNION SELECT 1"));
  }

  void testKnownTableNamesAreValid()
  {
    CPPUNIT_ASSERT(cdu::isValidIdentifier("uploadtree"));
    CPPUNIT_ASSERT(cdu::isValidIdentifier("uploadtree_a"));
    CPPUNIT_ASSERT(cdu::isValidIdentifier("uploadtree_42"));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(IsValidIdentifierTest);

class ReplaceUnicodeControlCharsTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReplaceUnicodeControlCharsTest);
  CPPUNIT_TEST(testPlainAsciiIsUnchanged);
  CPPUNIT_TEST(testTabAndNewlineAreKept);
  CPPUNIT_TEST(testNullByteIsStripped);
  CPPUNIT_TEST(testC0ControlCharsAreStripped);
  CPPUNIT_TEST(testC1ControlCharsAreStripped);
  CPPUNIT_TEST(testDeleteCharIsStripped);
  CPPUNIT_TEST(testUtf8MultiBytePrintableIsKept);
  CPPUNIT_TEST(testSurrogatePairCodepointIsKept);
  CPPUNIT_TEST(testMixedControlAndPrintableFiltered);
  CPPUNIT_TEST(testEmptyStringIsUnchanged);
  CPPUNIT_TEST_SUITE_END();

  std::string call(const std::string& s)
  {
    return cdu::replaceUnicodeControlChars(s);
  }

protected:
  void testPlainAsciiIsUnchanged()
  {
    CPPUNIT_ASSERT_EQUAL(std::string("hello world"), call("hello world"));
  }

  void testTabAndNewlineAreKept()
  {
    CPPUNIT_ASSERT_EQUAL(std::string("\t\n"), call("\t\n"));
  }

  void testNullByteIsStripped()
  {
    std::string in(1, '\x00');
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(in));
  }

  void testC0ControlCharsAreStripped()
  {
    for (char c = '\x01'; c <= '\x08'; ++c)
    {
      std::string in(1, c);
      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        "Expected control char 0x" + std::to_string((unsigned char)c) + " to be stripped",
        std::string(""), call(in));
    }
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x0B"));
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x0C"));
    for (char c = '\x0E'; c <= '\x1F'; ++c)
    {
      std::string in(1, c);
      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        "Expected control char 0x" + std::to_string((unsigned char)c) + " to be stripped",
        std::string(""), call(in));
    }
  }

  void testC1ControlCharsAreStripped()
  {
    std::string c1_80 = "\xC2\x80";
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(c1_80));
    std::string c1_9f = "\xC2\x9F";
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(c1_9f));
  }

  void testDeleteCharIsStripped()
  {
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x7F"));
  }

  void testUtf8MultiBytePrintableIsKept()
  {
    std::string copyright = "\xC2\xA9";
    CPPUNIT_ASSERT_EQUAL(copyright, call(copyright));
  }

  void testSurrogatePairCodepointIsKept()
  {
    std::string emoji = "\xF0\x9F\x98\x80";
    CPPUNIT_ASSERT_EQUAL(emoji, call(emoji));
  }

  void testMixedControlAndPrintableFiltered()
  {
    std::string in = "hello\x01world";
    CPPUNIT_ASSERT_EQUAL(std::string("helloworld"), call(in));
    std::string emoji = "\xF0\x9F\x98\x80";
    std::string mixed = emoji + "\x01" + emoji;
    CPPUNIT_ASSERT_EQUAL(emoji + emoji, call(mixed));
  }

  void testEmptyStringIsUnchanged()
  {
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(""));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReplaceUnicodeControlCharsTest);

class DecisionTypePriorityTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(DecisionTypePriorityTest);
  CPPUNIT_TEST(testPriorityOrdering);
  CPPUNIT_TEST(testStrongerTypeBeatsWeaker);
  CPPUNIT_TEST_SUITE_END();

protected:
  int prio(int decisionType)
  {
    return cdu::getDecisionTypePriority(decisionType);
  }

  void testPriorityOrdering()
  {
    CPPUNIT_ASSERT(prio(0) < prio(3));
    CPPUNIT_ASSERT(prio(3) < prio(4));
    CPPUNIT_ASSERT(prio(4) < prio(7));
    CPPUNIT_ASSERT(prio(7) < prio(6));
    CPPUNIT_ASSERT(prio(6) < prio(5));
    CPPUNIT_ASSERT_EQUAL(0, prio(999));
  }

  void testStrongerTypeBeatsWeaker()
  {
    int chosenPk = 50;
    int chosenType = 4;
    auto applyRow = [&](int pk, int type)
    {
      if (prio(type) > prio(chosenType))
      {
        chosenPk = pk;
        chosenType = type;
      }
    };
    applyRow(50, 4);
    applyRow(40, 5);
    CPPUNIT_ASSERT_EQUAL(40, chosenPk);
    CPPUNIT_ASSERT_EQUAL(5, chosenType);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(DecisionTypePriorityTest);
