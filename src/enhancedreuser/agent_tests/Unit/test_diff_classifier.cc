/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <unistd.h>

#include "DiffClassifier.hpp"

using namespace fo;

namespace
{

std::vector<DiffHunk> parse(const char* text)
{
  return parseUnifiedDiff(text);
}

ChangedLine cl(int line, const char* text)
{
  return {line, text};
}

std::vector<ChangedLine> lines(std::initializer_list<ChangedLine> l)
{
  return std::vector<ChangedLine>(l);
}

NirjasOutput outputWithSingleComment(int startLine, int endLine)
{
  NirjasOutput out;
  out.lang = "c";
  out.singleLineComments.push_back({startLine, endLine, ""});
  return out;
}

NirjasOutput errorOutput()
{
  NirjasOutput out;
  out.lang = "error";
  return out;
}

std::vector<DiffHunk> codeHunk()
{
  DiffHunk h;
  h.removed.push_back(cl(10, "int x = 1;"));
  h.added.push_back(cl(10, "int x = 2;"));
  return {h};
}

class TempFile
{
public:
  explicit TempFile(const std::string& content)
  {
    char tmpl[] = "/tmp/enhreuser_test_XXXXXX";
    fd_ = mkstemp(tmpl);
    path_ = tmpl;
    if (fd_ >= 0)
    {
      write(fd_, content.c_str(), content.size());
      close(fd_);
    }
  }
  ~TempFile()
  {
    if (!path_.empty()) unlink(path_.c_str());
  }
  const std::string& path() const { return path_; }
  bool valid() const { return fd_ >= 0; }

private:
  int         fd_ = -1;
  std::string path_;
};

} // namespace

class ParseUnifiedDiffTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ParseUnifiedDiffTest);
  CPPUNIT_TEST(testEmptyOutput);
  CPPUNIT_TEST(testIdenticalOutput);
  CPPUNIT_TEST(testBasicHunkWithLineNumbers);
  CPPUNIT_TEST(testPureAdditionHunk);
  CPPUNIT_TEST(testMultipleHunks);
  CPPUNIT_TEST(testNoNewlineAtEnd);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEmptyOutput()
  {
    auto hunks = parse("");
    CPPUNIT_ASSERT(hunks.empty());
  }

  void testIdenticalOutput()
  {
    // diff -u on identical files prints nothing.
    auto hunks = parse("--- a\t2024\n+++ b\t2024\n");
    CPPUNIT_ASSERT(hunks.empty());
  }

  void testBasicHunkWithLineNumbers()
  {
    auto hunks = parse(
      "--- a\t2024\n"
      "+++ b\t2024\n"
      "@@ -5,4 +5,4 @@\n"
      " context\n"
      "-old line\n"
      "+new line\n"
      " context2\n");
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks.size()));
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks[0].removed.size()));
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks[0].added.size()));
    // context on old line 5, removed on line 6
    CPPUNIT_ASSERT_EQUAL(6, hunks[0].removed[0].line);
    CPPUNIT_ASSERT_EQUAL(std::string("old line"), hunks[0].removed[0].text);
    CPPUNIT_ASSERT_EQUAL(6, hunks[0].added[0].line);
    CPPUNIT_ASSERT_EQUAL(std::string("new line"), hunks[0].added[0].text);
  }

  void testPureAdditionHunk()
  {
    auto hunks = parse(
      "--- /dev/null\t2024\n"
      "+++ b\t2024\n"
      "@@ -0,0 +1,3 @@\n"
      "+first\n"
      "+second\n"
      "+third\n");
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks.size()));
    CPPUNIT_ASSERT_EQUAL((unsigned)0, static_cast<unsigned>(hunks[0].removed.size()));
    CPPUNIT_ASSERT_EQUAL((unsigned)3, static_cast<unsigned>(hunks[0].added.size()));
    CPPUNIT_ASSERT_EQUAL(1, hunks[0].added[0].line);
    CPPUNIT_ASSERT_EQUAL(3, hunks[0].added[2].line);
  }

  void testMultipleHunks()
  {
    auto hunks = parse(
      "--- a\t2024\n"
      "+++ b\t2024\n"
      "@@ -1,2 +1,2 @@\n"
      "-a\n"
      "+A\n"
      "@@ -10,2 +10,2 @@\n"
      "-b\n"
      "+B\n");
    CPPUNIT_ASSERT_EQUAL((unsigned)2, static_cast<unsigned>(hunks.size()));
    CPPUNIT_ASSERT_EQUAL(1, hunks[0].removed[0].line);
    CPPUNIT_ASSERT_EQUAL(10, hunks[1].removed[0].line);
  }

  void testNoNewlineAtEnd()
  {
    auto hunks = parse(
      "--- a\t2024\n"
      "+++ b\t2024\n"
      "@@ -1,2 +1,2 @@\n"
      "-foo\n"
      "\\ No newline at end of file\n"
      "+bar\n"
      "\\ No newline at end of file\n");
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks.size()));
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(hunks[0].removed.size()));
    CPPUNIT_ASSERT_EQUAL(std::string("foo"), hunks[0].removed[0].text);
    CPPUNIT_ASSERT_EQUAL(std::string("bar"), hunks[0].added[0].text);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ParseUnifiedDiffTest);

class UnifiedDiffTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(UnifiedDiffTest);
  CPPUNIT_TEST(testIdenticalFilesOkEmptyHunks);
  CPPUNIT_TEST(testDifferentFilesOkWithHunks);
  CPPUNIT_TEST(testMissingFileError);
  CPPUNIT_TEST(testBinaryFileError);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testIdenticalFilesOkEmptyHunks()
  {
    TempFile a("hello\nworld\n");
    TempFile b("hello\nworld\n");
    CPPUNIT_ASSERT(a.valid() && b.valid());
    DiffResult r = unifiedDiff(a.path(), b.path());
    CPPUNIT_ASSERT(r.status == DiffStatus::Ok);
    CPPUNIT_ASSERT(r.hunks.empty());
  }

  void testDifferentFilesOkWithHunks()
  {
    TempFile a("hello\nworld\n");
    TempFile b("hello\nuniverse\n");
    DiffResult r = unifiedDiff(a.path(), b.path());
    CPPUNIT_ASSERT(r.status == DiffStatus::Ok);
    CPPUNIT_ASSERT_EQUAL((unsigned)1, static_cast<unsigned>(r.hunks.size()));
    CPPUNIT_ASSERT_EQUAL(std::string("world"), r.hunks[0].removed[0].text);
    CPPUNIT_ASSERT_EQUAL(std::string("universe"), r.hunks[0].added[0].text);
  }

  void testMissingFileError()
  {
    TempFile a("hello\n");
    DiffResult r = unifiedDiff(a.path(), "/nonexistent/path/xyz");
    CPPUNIT_ASSERT(r.status == DiffStatus::Error);
  }

  void testBinaryFileError()
  {
    TempFile a(std::string("text\n") + std::string(1, '\0') + "\nmore\n");
    TempFile b(std::string("text\n") + std::string(1, '\0') + "\ndiff\n");
    DiffResult r = unifiedDiff(a.path(), b.path());
    CPPUNIT_ASSERT(r.status == DiffStatus::Error);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(UnifiedDiffTest);

class CopyrightChangeTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(CopyrightChangeTest);
  CPPUNIT_TEST(testYearBumpIsTrue);
  CPPUNIT_TEST(testYearRangeBumpIsTrue);
  CPPUNIT_TEST(testOwnerChangeIsTrue);
  CPPUNIT_TEST(testUnchangedIsFalse);
  CPPUNIT_TEST(testNonCopyrightIsFalse);
  CPPUNIT_TEST(testCopyrightAddedOrRemovedIsTrue);
  CPPUNIT_TEST(testHtmlEntityIsTrue);
  CPPUNIT_TEST(testCircledCSymbolIsTrue);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testYearBumpIsTrue()
  {
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "Copyright 2003 by Foo")}),
      lines({cl(1, "Copyright 2005 by Foo")})));
  }

  void testYearRangeBumpIsTrue()
  {
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "Copyright (C) 2003-2005 by Foo")}),
      lines({cl(1, "Copyright (C) 2003-2006 by Foo")})));
  }

  void testOwnerChangeIsTrue()
  {
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "Copyright 2003 by Foo")}),
      lines({cl(1, "Copyright 2003 by Bar")})));
  }

  void testUnchangedIsFalse()
  {
    CPPUNIT_ASSERT(!isCopyrightChange(
      lines({cl(1, "Copyright by Foo")}),
      lines({cl(1, "Copyright by Foo")})));
  }

  void testNonCopyrightIsFalse()
  {
    CPPUNIT_ASSERT(!isCopyrightChange(
      lines({cl(1, "x = 2003;")}), lines({cl(1, "x = 2005;")})));
  }

  void testCopyrightAddedOrRemovedIsTrue()
  {
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({}), lines({cl(1, "Copyright 2026 by Foo")})));
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "Copyright 2026 by Foo")}), lines({})));
  }

  void testHtmlEntityIsTrue()
  {
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "&copy; 2003 Foo")}),
      lines({cl(1, "&copy; 2005 Foo")})));
  }

  void testCircledCSymbolIsTrue()
  {
    // U+24B8 circled latin capital letter C (UTF-8 E2 92 B8)
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "\xE2\x92\xB8 2003 Foo")}),
      lines({cl(1, "\xE2\x92\xB8 2005 Foo")})));
    // U+24D2 circled latin small letter c (UTF-8 E2 93 92)
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "\xE2\x93\x92 2003 Foo")}),
      lines({cl(1, "\xE2\x93\x92 2005 Foo")})));
    // U+249E parenthesized latin small letter c (UTF-8 E2 92 9E)
    CPPUNIT_ASSERT(isCopyrightChange(
      lines({cl(1, "\xE2\x92\x9E 2003 Foo")}),
      lines({cl(1, "\xE2\x92\x9E 2005 Foo")})));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(CopyrightChangeTest);

class IsLineInCommentsTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(IsLineInCommentsTest);
  CPPUNIT_TEST(testInsideBlockIsTrue);
  CPPUNIT_TEST(testOutsideBlockIsFalse);
  CPPUNIT_TEST(testZeroOrNegativeIsFalse);
  CPPUNIT_TEST(testErrorOutputIsFalse);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testInsideBlockIsTrue()
  {
    auto out = outputWithSingleComment(3, 5);
    CPPUNIT_ASSERT(isLineInComments(3, out));
    CPPUNIT_ASSERT(isLineInComments(5, out));
    CPPUNIT_ASSERT(isLineInComments(4, out));
  }

  void testOutsideBlockIsFalse()
  {
    auto out = outputWithSingleComment(3, 5);
    CPPUNIT_ASSERT(!isLineInComments(2, out));
    CPPUNIT_ASSERT(!isLineInComments(6, out));
  }

  void testZeroOrNegativeIsFalse()
  {
    auto out = outputWithSingleComment(1, 5);
    CPPUNIT_ASSERT(!isLineInComments(0, out));
    CPPUNIT_ASSERT(!isLineInComments(-1, out));
  }

  void testErrorOutputIsFalse()
  {
    CPPUNIT_ASSERT(!isLineInComments(3, errorOutput()));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(IsLineInCommentsTest);

class ClassifyChangeTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ClassifyChangeTest);
  CPPUNIT_TEST(testEmptyHunksIsLicenseSame);
  CPPUNIT_TEST(testCodeChangeIsCodeChange);
  CPPUNIT_TEST(testCopyrightBumpIsCopyrightChange);
  CPPUNIT_TEST(testCopyrightBumpWithoutComments);
  CPPUNIT_TEST(testCopyrightOwnerChangeWithoutCommentsIsCopyrightChange);
  CPPUNIT_TEST(testHtmlEntityWithAllRightsReservedApplies);
  CPPUNIT_TEST(testCommentOnlyChangeIsLicenseSame);
  CPPUNIT_TEST(testSpdxCommentChangeIsLicenseChanged);
  CPPUNIT_TEST(testNoCommentDataCodeIsCodeChange);
  CPPUNIT_TEST(testNoCommentDataLicenseTextIsLicenseChanged);
  CPPUNIT_TEST(testCopyrightHunkPlusCodeHunkIsCodeAndCopyrightChange);
  CPPUNIT_TEST(testLicenseHintPlusCopyrightChangeIsLicenseChanged);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEmptyHunksIsLicenseSame()
  {
    CPPUNIT_ASSERT(classifyChange({}, errorOutput(), errorOutput())
      == ChangeType::LicenseSame);
  }

  void testCodeChangeIsCodeChange()
  {
    CPPUNIT_ASSERT(classifyChange(codeHunk(), errorOutput(), errorOutput())
      == ChangeType::CodeChange);
  }

  void testCopyrightBumpIsCopyrightChange()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "Copyright (C) 2003 by Foo"));
    h.added.push_back(cl(1, "Copyright (C) 2005 by Foo"));
    auto out = outputWithSingleComment(1, 1);
    CPPUNIT_ASSERT(classifyChange({h}, out, out)
      == ChangeType::CopyrightChange);
  }

  void testCopyrightBumpWithoutComments()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "Copyright 2003 by Foo"));
    h.added.push_back(cl(1, "Copyright 2005 by Foo"));
    // No comment data at all – the year bump must still be accepted.
    CPPUNIT_ASSERT(classifyChange({h}, errorOutput(), errorOutput())
      == ChangeType::CopyrightChange);
  }

  void testCopyrightOwnerChangeWithoutCommentsIsCopyrightChange()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "Copyright 2003 by Foo"));
    h.added.push_back(cl(1, "Copyright 2003 by Bar"));
    // Holder change with no comment data must apply (copyright, not license).
    CPPUNIT_ASSERT(classifyChange({h}, errorOutput(), errorOutput())
      == ChangeType::CopyrightChange);
  }

  void testHtmlEntityWithAllRightsReservedApplies()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "&copy; 2003 Foo, All rights reserved."));
    h.added.push_back(cl(1, "&copy; 2003 Foo, Inc., All rights reserved."));
    // No comment data; the "All rights reserved" token must not override the
    // copyright-statement detection.
    CPPUNIT_ASSERT(classifyChange({h}, errorOutput(), errorOutput())
      == ChangeType::CopyrightChange);
  }

  void testSpdxCommentChangeIsLicenseChanged()
  {
    // Even though nomos (matched-text compare) and ojo (SPDX-id compare) in
    // EnhancedReuserDatabaseHandler normally catch this upstream, being
    // inside a recognized comment does not by itself prove the license text
    // is unchanged, so classifyChange() must also reject it directly.
    DiffHunk h;
    h.removed.push_back(cl(1, "// SPDX-License-" "Identifier: GPL-2.0-only"));
    h.added.push_back(cl(1, "// SPDX-License-" "Identifier: MIT"));
    auto out = outputWithSingleComment(1, 1);
    CPPUNIT_ASSERT(classifyChange({h}, out, out)
      == ChangeType::LicenseChanged);
  }

  void testCommentOnlyChangeIsLicenseSame()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "// hello"));
    h.added.push_back(cl(1, "// hello world"));
    auto out = outputWithSingleComment(1, 1);
    CPPUNIT_ASSERT(classifyChange({h}, out, out)
      == ChangeType::LicenseSame);
  }

  void testNoCommentDataCodeIsCodeChange()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "#!/bin/sh"));
    h.added.push_back(cl(1, "#!/bin/bash"));
    CPPUNIT_ASSERT(classifyChange({h}, errorOutput(), errorOutput())
      == ChangeType::CodeChange);
  }

  void testNoCommentDataLicenseTextIsLicenseChanged()
  {
    DiffHunk h;
    h.removed.push_back(cl(1, "This file is licensed under the MIT license"));
    h.added.push_back(cl(1, "This file is licensed under the GPL"));
    CPPUNIT_ASSERT(classifyChange({h}, errorOutput(), errorOutput())
      == ChangeType::LicenseChanged);
  }

  void testCopyrightHunkPlusCodeHunkIsCodeAndCopyrightChange()
  {
    DiffHunk copyrightHunk;
    copyrightHunk.removed.push_back(cl(2, "Copyright (c) 2004 Example Corp."));
    copyrightHunk.added.push_back(cl(2, "Copyright (c) 2006 Example Corp."));
    // Header comment block (lines 1-5) covers the copyright line only; the
    // code hunk at line 10 falls outside it, as in a real source file.
    auto out = outputWithSingleComment(1, 5);
    // A copyright-only hunk and a separate code hunk elsewhere in the same
    // file must not collapse into a plain CodeChange (losing the copyright
    // signal).
    std::vector<DiffHunk> hunks = {copyrightHunk, codeHunk().at(0)};
    CPPUNIT_ASSERT(classifyChange(hunks, out, out)
      == ChangeType::CodeAndCopyrightChange);
  }

  void testLicenseHintPlusCopyrightChangeIsLicenseChanged()
  {
    // One hunk changing both a copyright statement and an SPDX identifier:
    // the license/SPDX-hint change must win (LicenseChanged), not be masked
    // by the copyright change (CopyrightChange).
    DiffHunk h;
    h.removed.push_back(cl(1, "Copyright (C) 2003 by Foo"));
    h.removed.push_back(cl(2, "// SPDX-License-" "Identifier: GPL-2.0-only"));
    h.added.push_back(cl(1, "Copyright (C) 2005 by Foo"));
    h.added.push_back(cl(2, "// SPDX-License-" "Identifier: MIT"));
    // Comment block covering the SPDX line only, so the no-comment-data
    // fallback cannot produce the veto on its own.
    auto out = outputWithSingleComment(2, 2);
    CPPUNIT_ASSERT(classifyChange({h}, out, out)
      == ChangeType::LicenseChanged);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ClassifyChangeTest);
