/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
/**
 * @file
 * @brief Unit tests for ReuserDatabaseHandler private helper methods.
 *
 * Tests isValidIdentifier() and replaceUnicodeControlChars() via a thin
 * protected-accessor subclass so the private helpers are reachable without
 * modifying production code.
 */

#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "ReuserDatabaseHandler.hpp"
#include "MockReuserDatabaseHandler.hpp"

/**
 * @class ReuserHelpersAccessor
 * @brief Thin subclass that exposes private helper methods for testing.
 */
class ReuserHelpersAccessor : public MockReuserDatabaseHandler
{
public:
  using MockReuserDatabaseHandler::MockReuserDatabaseHandler;

  bool callIsValidIdentifier(const std::string& s)
  {
    return isValidIdentifier(s);
  }

  std::string callReplaceUnicodeControlChars(const std::string& s)
  {
    return replaceUnicodeControlChars(s);
  }
};

// ── isValidIdentifier tests ───────────────────────────────────────────────────

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
  CPPUNIT_TEST(testSingleQuoteIsInvalid);
  CPPUNIT_TEST(testDoubleQuoteIsInvalid);
  CPPUNIT_TEST(testDollarIsInvalid);
  CPPUNIT_TEST(testNullByteIsInvalid);
  CPPUNIT_TEST(testSqlInjectionPatternIsInvalid);
  CPPUNIT_TEST(testKnownTableNamesAreValid);
  CPPUNIT_TEST_SUITE_END();

  ReuserHelpersAccessor acc;

protected:
  /**
   * @brief An empty string is not a valid SQL identifier.
   */
  void testEmptyStringIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier(""));
  }

  /**
   * @brief A string of lowercase ASCII letters is valid.
   */
  void testLowercaseLettersAreValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("abc"));
  }

  /**
   * @brief A string of uppercase ASCII letters is valid.
   */
  void testUppercaseLettersAreValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("ABC"));
  }

  /**
   * @brief A string of ASCII digits is valid.
   */
  void testDigitsAreValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("123"));
  }

  /**
   * @brief An underscore character is valid.
   */
  void testUnderscoreIsValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("_"));
  }

  /**
   * @brief A typical mixed alphanumeric/underscore identifier is valid.
   */
  void testMixedAlphanumericUnderscoreIsValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("uploadtree_a"));
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("upload_fk_123"));
  }

  /**
   * @brief A space character makes an identifier invalid.
   */
  void testSpaceIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("upload tree"));
  }

  /**
   * @brief A hyphen makes an identifier invalid.
   */
  void testHyphenIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("upload-tree"));
  }

  /**
   * @brief A dot makes an identifier invalid (guards against schema.table injection).
   */
  void testDotIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("public.uploadtree"));
  }

  /**
   * @brief A semicolon makes an identifier invalid (guards against statement injection).
   */
  void testSemicolonIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("foo;DROP TABLE uploadtree"));
  }

  /**
   * @brief A single quote makes an identifier invalid.
   */
  void testSingleQuoteIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("up'load"));
  }

  /**
   * @brief A double quote makes an identifier invalid.
   */
  void testDoubleQuoteIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("up\"load"));
  }

  /**
   * @brief A dollar sign makes an identifier invalid.
   */
  void testDollarIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("$1"));
  }

  /**
   * @brief A null byte makes an identifier invalid.
   */
  void testNullByteIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier(std::string("up\x00load", 8)));
  }

  /**
   * @brief A classic SQL injection pattern is rejected.
   *
   * Ensures the guard prevents an attacker from embedding arbitrary SQL
   * via a crafted table name.
   */
  void testSqlInjectionPatternIsInvalid()
  {
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("t WHERE 1=1--"));
    CPPUNIT_ASSERT(!acc.callIsValidIdentifier("t UNION SELECT 1"));
  }

  /**
   * @brief The three concrete uploadtree table names used in production are valid.
   */
  void testKnownTableNamesAreValid()
  {
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("uploadtree"));
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("uploadtree_a"));
    // upload-specific table names follow the pattern uploadtree_<pk>
    CPPUNIT_ASSERT(acc.callIsValidIdentifier("uploadtree_42"));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(IsValidIdentifierTest);

// ── replaceUnicodeControlChars tests ─────────────────────────────────────────

class ReplaceUnicodeControlCharsTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ReplaceUnicodeControlCharsTest);
  CPPUNIT_TEST(testPlainAsciiIsUnchanged);
  CPPUNIT_TEST(testTabAndNewlineAreKept);
  CPPUNIT_TEST(testCarriageReturnIsKept);
  CPPUNIT_TEST(testNullByteIsStripped);
  CPPUNIT_TEST(testC0ControlCharsAreStripped);
  CPPUNIT_TEST(testC1ControlCharsAreStripped);
  CPPUNIT_TEST(testDeleteCharIsStripped);
  CPPUNIT_TEST(testUtf8MultiBytePrintableIsKept);
  CPPUNIT_TEST(testSurrogatePairCodepointIsKept);
  CPPUNIT_TEST(testMixedControlAndPrintableFiltered);
  CPPUNIT_TEST(testEmptyStringIsUnchanged);
  CPPUNIT_TEST_SUITE_END();

  ReuserHelpersAccessor acc;

  std::string call(const std::string& s)
  {
    return acc.callReplaceUnicodeControlChars(s);
  }

protected:
  /**
   * @brief Plain ASCII text passes through unchanged.
   */
  void testPlainAsciiIsUnchanged()
  {
    CPPUNIT_ASSERT_EQUAL(std::string("hello world"), call("hello world"));
  }

  /**
   * @brief Horizontal tab (U+0009) and line feed (U+000A) are kept.
   *
   * These are standard whitespace characters not classified as controls
   * by the filter (only U+0000–U+0008 are stripped in the C0 range).
   */
  void testTabAndNewlineAreKept()
  {
    CPPUNIT_ASSERT_EQUAL(std::string("\t\n"), call("\t\n"));
  }

  /**
   * @brief Carriage return (U+000D) is kept.
   */
  void testCarriageReturnIsKept()
  {
    CPPUNIT_ASSERT_EQUAL(std::string("\r"), call("\r"));
  }

  /**
   * @brief Null byte (U+0000) is stripped.
   */
  void testNullByteIsStripped()
  {
    std::string in(1, '\x00');
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(in));
  }

  /**
   * @brief C0 control characters U+0001–U+0008 are stripped.
   *
   * Characters in the range 0x01–0x08 are non-printable controls that
   * can corrupt database content or confuse downstream parsers.
   */
  void testC0ControlCharsAreStripped()
  {
    // U+0001 through U+0008
    for (char c = '\x01'; c <= '\x08'; ++c)
    {
      std::string in(1, c);
      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        "Expected control char 0x" + std::to_string((unsigned char)c) + " to be stripped",
        std::string(""), call(in));
    }
    // U+000B (VT) and U+000C (FF) are also stripped
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x0B"));
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x0C"));
    // U+000E through U+001F
    for (char c = '\x0E'; c <= '\x1F'; ++c)
    {
      std::string in(1, c);
      CPPUNIT_ASSERT_EQUAL_MESSAGE(
        "Expected control char 0x" + std::to_string((unsigned char)c) + " to be stripped",
        std::string(""), call(in));
    }
  }

  /**
   * @brief C1 control characters U+0080–U+009F are stripped.
   *
   * These are legacy 8-bit control codes that appear in some legacy
   * copyright strings; they must not pass through to the database.
   */
  void testC1ControlCharsAreStripped()
  {
    // U+0080 in UTF-8 is \xC2\x80
    std::string c1_80 = "\xC2\x80";
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(c1_80));
    // U+009F in UTF-8 is \xC2\x9F
    std::string c1_9f = "\xC2\x9F";
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(c1_9f));
  }

  /**
   * @brief DEL character (U+007F) is stripped.
   */
  void testDeleteCharIsStripped()
  {
    CPPUNIT_ASSERT_EQUAL(std::string(""), call("\x7F"));
  }

  /**
   * @brief A printable multi-byte UTF-8 character (e.g. U+00A9 ©) is kept.
   */
  void testUtf8MultiBytePrintableIsKept()
  {
    // © = U+00A9 = \xC2\xA9 in UTF-8
    std::string copyright_sign = "\xC2\xA9";
    CPPUNIT_ASSERT_EQUAL(copyright_sign, call(copyright_sign));
  }

  /**
   * @brief A codepoint above U+FFFF (surrogate-pair range in UTF-16) is kept.
   *
   * ICU's char32At() returns the full codepoint for supplementary characters
   * and advances the index by 2 UTF-16 code units.  The implementation skips
   * the second unit via ++i to avoid processing half a surrogate pair as a
   * separate character.  This test verifies that such a codepoint (U+1F600 😀)
   * survives the filter intact and the iterator does not go out of bounds.
   */
  void testSurrogatePairCodepointIsKept()
  {
    // U+1F600 GRINNING FACE in UTF-8: \xF0\x9F\x98\x80
    std::string emoji = "\xF0\x9F\x98\x80";
    CPPUNIT_ASSERT_EQUAL(emoji, call(emoji));
  }

  /**
   * @brief A string mixing control characters and printable text is filtered correctly.
   *
   * Only the printable parts survive; embedded controls are removed without
   * corrupting adjacent characters.
   */
  void testMixedControlAndPrintableFiltered()
  {
    // "hello\x01world" → "helloworld"
    std::string in = "hello\x01world";
    CPPUNIT_ASSERT_EQUAL(std::string("helloworld"), call(in));

    // control sandwiched between emoji
    // \xF0\x9F\x98\x80 \x01 \xF0\x9F\x98\x80  →  emoji + emoji
    std::string emoji = "\xF0\x9F\x98\x80";
    std::string mixed = emoji + "\x01" + emoji;
    CPPUNIT_ASSERT_EQUAL(emoji + emoji, call(mixed));
  }

  /**
   * @brief An empty input string returns an empty string.
   */
  void testEmptyStringIsUnchanged()
  {
    CPPUNIT_ASSERT_EQUAL(std::string(""), call(""));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ReplaceUnicodeControlCharsTest);
