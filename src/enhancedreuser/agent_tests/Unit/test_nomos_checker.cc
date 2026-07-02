/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <cstdio>
#include <unistd.h>

#include "NomosChecker.hpp"

namespace
{

// getNomosRegionsByPfile(), getNomosLicensesByPfile() and nomosRanOnUpload()
// need a live fo::DbManager/Postgres connection (same as
// getKotobaShortnamesByPfile() in KotobaChecker.cc) and are not covered by
// this offline unit test binary; only the pure-function parts
// (extractNormalizedLicenseText(), nomosLicensesEqual()) are exercised here.

class TempFile
{
public:
  explicit TempFile(const std::string& content)
  {
    char tmpl[] = "/tmp/enhreuser_nomos_test_XXXXXX";
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

class ExtractNormalizedLicenseTextTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(ExtractNormalizedLicenseTextTest);
  CPPUNIT_TEST(testEmptyRegionsIsEmpty);
  CPPUNIT_TEST(testUnreadableFileIsEmpty);
  CPPUNIT_TEST(testSingleRegionExtractsSubstring);
  CPPUNIT_TEST(testMultipleRegionsConcatenatedInOrder);
  CPPUNIT_TEST(testWhitespaceIsNormalized);
  CPPUNIT_TEST(testOutOfRangeRegionIsSkipped);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEmptyRegionsIsEmpty()
  {
    TempFile f("Copyright 2026 Foo\nLicensed under MIT\n");
    CPPUNIT_ASSERT_EQUAL(std::string(""),
      extractNormalizedLicenseText(f.path(), {}));
  }

  void testUnreadableFileIsEmpty()
  {
    CPPUNIT_ASSERT_EQUAL(std::string(""),
      extractNormalizedLicenseText("/nonexistent/path/xyz", {{0, 5}}));
  }

  void testSingleRegionExtractsSubstring()
  {
    TempFile f("0123456789");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT_EQUAL(std::string("2345"),
      extractNormalizedLicenseText(f.path(), {{2, 6}}));
  }

  void testMultipleRegionsConcatenatedInOrder()
  {
    TempFile f("AAAABBBBCCCC");
    CPPUNIT_ASSERT(f.valid());
    // Regions [0,4) = "AAAA", [8,12) = "CCCC"; expect "AAAA CCCC".
    CPPUNIT_ASSERT_EQUAL(std::string("AAAA CCCC"),
      extractNormalizedLicenseText(f.path(), {{0, 4}, {8, 12}}));
  }

  void testWhitespaceIsNormalized()
  {
    std::string content = "Copyright   2026\n\n  Foo   Corp.";
    TempFile f(content);
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT_EQUAL(std::string("Copyright 2026 Foo Corp."),
      extractNormalizedLicenseText(f.path(), {{0, static_cast<int>(content.size())}}));
  }

  void testOutOfRangeRegionIsSkipped()
  {
    TempFile f("short");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT_EQUAL(std::string(""),
      extractNormalizedLicenseText(f.path(), {{0, 1000}}));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(ExtractNormalizedLicenseTextTest);

class NomosLicensesEqualTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(NomosLicensesEqualTest);
  CPPUNIT_TEST(testEqualSets);
  CPPUNIT_TEST(testOrderDoesNotMatter);
  CPPUNIT_TEST(testDifferentSets);
  CPPUNIT_TEST(testAddedLicenseDiffers);
  CPPUNIT_TEST(testRemovedLicenseDiffers);
  CPPUNIT_TEST(testBothEmptyIsEqual);
  CPPUNIT_TEST(testEmptyVsNonEmptyDiffers);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testEqualSets()
  {
    CPPUNIT_ASSERT(nomosLicensesEqual({"GPL-2.0-only"}, {"GPL-2.0-only"}));
  }

  void testOrderDoesNotMatter()
  {
    CPPUNIT_ASSERT(nomosLicensesEqual(
      {"MIT", "GPL-2.0-only"}, {"GPL-2.0-only", "MIT"}));
  }

  void testDifferentSets()
  {
    CPPUNIT_ASSERT(!nomosLicensesEqual({"GPL-2.0-only"}, {"MIT"}));
  }

  void testAddedLicenseDiffers()
  {
    CPPUNIT_ASSERT(!nomosLicensesEqual(
      {"GPL-2.0-only"}, {"GPL-2.0-only", "MIT"}));
  }

  void testRemovedLicenseDiffers()
  {
    CPPUNIT_ASSERT(!nomosLicensesEqual(
      {"GPL-2.0-only", "MIT"}, {"GPL-2.0-only"}));
  }

  void testBothEmptyIsEqual()
  {
    CPPUNIT_ASSERT(nomosLicensesEqual({}, {}));
  }

  void testEmptyVsNonEmptyDiffers()
  {
    CPPUNIT_ASSERT(!nomosLicensesEqual({}, {"GPL-2.0-only"}));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(NomosLicensesEqualTest);
