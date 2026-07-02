/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include <cstdio>
#include <unistd.h>

#include "OjoChecker.hpp"

namespace
{

// getOjoSpdxIdsByPfile() needs a live fo::DbManager/Postgres connection
// (same as getKotobaShortnamesByPfile() in KotobaChecker.cc) and is not
// covered by this offline unit test binary; only the pure-function part
// (spdxIdsPresentInFile()) is exercised here.

class TempFile
{
public:
  explicit TempFile(const std::string& content)
  {
    char tmpl[] = "/tmp/enhreuser_ojo_test_XXXXXX";
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

class SpdxIdsPresentInFileTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(SpdxIdsPresentInFileTest);
  CPPUNIT_TEST(testAllIdsPresent);
  CPPUNIT_TEST(testMissingIdIsVeto);
  CPPUNIT_TEST(testCaseInsensitive);
  CPPUNIT_TEST(testEmptyIdSetIsVeto);
  CPPUNIT_TEST(testUnreadableFileIsVeto);
  CPPUNIT_TEST_SUITE_END();

protected:
  void testAllIdsPresent()
  {
    TempFile f("// SPDX-License-" "Identifier: MIT\n"
               "// SPDX-License-" "Identifier: GPL-2.0-only\n");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT(spdxIdsPresentInFile(f.path(), {"MIT", "GPL-2.0-only"}));
  }

  void testMissingIdIsVeto()
  {
    TempFile f("// SPDX-License-" "Identifier: MIT\n");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT(!spdxIdsPresentInFile(f.path(), {"MIT", "Apache-2.0"}));
  }

  void testCaseInsensitive()
  {
    TempFile f("// spdx-license-identifier: mit\n");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT(spdxIdsPresentInFile(f.path(), {"MIT"}));
  }

  void testEmptyIdSetIsVeto()
  {
    TempFile f("// SPDX-License-" "Identifier: MIT\n");
    CPPUNIT_ASSERT(f.valid());
    CPPUNIT_ASSERT(!spdxIdsPresentInFile(f.path(), {}));
  }

  void testUnreadableFileIsVeto()
  {
    CPPUNIT_ASSERT(!spdxIdsPresentInFile("/nonexistent/path/xyz", {"MIT"}));
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(SpdxIdsPresentInFileTest);
