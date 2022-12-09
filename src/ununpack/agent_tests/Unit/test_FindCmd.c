/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"

/**
 * \file
 * \brief Unit test cases for FindCmd()
 */
/**
 * \brief find xx.7z
 * \test
 * -# Call FindCmd() on 7z file
 * -# Check if function returns correct index
 */
void testFindCmdNormal()
{
  char *Filename = "../testdata/test.7z";
  int result = 0;
  result = FindCmd(Filename);
  //FO_ASSERT_EQUAL(result, 15);
  if (result == 16 || result == 17)
  {
    FO_PASS(result);

  }else
  {
    FO_FAIL(result);
  }
}

/**
 * \brief find xx.dsc
 * \test
 * -# Call FindCmd() on dsc file
 * -# Check if function returns correct index
 */
void testFindCmd4DscFile()
{
  char *Filename = "../testdata/test_1-1.dsc";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 28);
}

/**
 * \brief find xx.cab
 * \test
 * -# Call FindCmd() on cab file
 * -# Check if function returns correct index
 */
void testFindCmd4CabFile()
{
  char *Filename = "../testdata/test.cab";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 17);
}

/**
 * \brief find xx.msi
 * \test
 * -# Call FindCmd() on msi file
 * -# Check if function returns correct index
 */
void testFindCmd4MsiFile()
{
  char *Filename = "../testdata/test.msi";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 17);
}

/**
 * \brief find xx.rpm
 * \test
 * -# Call FindCmd() on rpm file
 * -# Check if function returns correct index
 */
void testFindCmd4RpmFile()
{
  char *Filename = "../testdata/test.rpm";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 18);
  Filename = "../testdata/test.rpm";
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 18);
}

/**
 * \brief find xx.iso
 * \test
 * -# Call FindCmd() on iso file
 * -# Check if function returns correct index
 */
void testFindCmd4IsoFile()
{
  char *Filename = "../testdata/test.iso";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 21);  // let isoinfo handle the isos
}

/**
 * \brief find xx.zip
 * \test
 * -# Call FindCmd() on zip file
 * -# Check if function returns correct index
 */
void testFindCmd4ZipFile()
{
  char *Filename = "../testdata/test.zip";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 10);
}

/**
 * \brief find xx.rar
 * \test
 * -# Call FindCmd() on rar file
 * -# Check if function returns correct index
 */
void testFindCmd4RarFile()
{
  char *Filename = "../testdata/test.rar";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 14);
}

/**
 * \brief find xx.cpio
 * \test
 * -# Call FindCmd() on cpio file
 * -# Check if function returns correct index
 */
void testFindCmd4CpioFile()
{
  char *Filename = "../testdata/test.cpio";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 13);
}


/**
 * \brief find xx.deb
 * \test
 * -# Call FindCmd() on deb file
 * -# Check if function returns correct index
 */
void testFindCmd4DebFile()
{
  char *Filename = "../testdata/test.deb";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 20);  /* let ar handle udeb */
}

/**
 * \brief find xx.ar
 * \test
 * -# Call FindCmd() on ar file
 * -# Check if function returns correct index
 */
void testFindCmd4ArchiveLibFile()
{
  char *Filename = "../testdata/test.ar";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 19);
}


/**
 * \brief find xx.tar
 * \test
 * -# Call FindCmd() on tar file
 * -# Check if function returns correct index
 */
void testFindCmd4TarFile()
{
  char *Filename = "../testdata/emptydirs.tar";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 11);
}


/**
 * \brief find xx.z
 * \test
 * -# Call FindCmd() on z file
 * -# Check if function returns correct index
 */
void testFindCmd4ZFile()
{
  char *Filename = "../testdata/test.z";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 3);
}

/**
 * \brief find xx.exe
 * \test
 * -# Call FindCmd() on exe file
 * -# Check if function returns correct index
 */
void testFindCmd4ExeFile()
{
  char *Filename = "../testdata/test.exe";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 31);  /* this can be unpacked by 7z */
}

/**
 * \brief find xx.bz2
 * \test
 * -# Call FindCmd() on bz2 file
 * -# Check if function returns correct index
 */
void testFindCmd4Bz2File()
{
  char *Filename = "../testdata/fossI16L335U29.tar.bz2";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 5);
}

/**
 * \brief find ext3 fs
 * \test
 * -# Call FindCmd() on ext3 file
 * -# Check if function returns correct index
 */
void testFindCmd4Ext3File()
{
  char *Filename = "../testdata/ext3test-image";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 26);
}

/**
 * \brief find ext2 fs
 * \test
 * -# Call FindCmd() on ext2 file
 * -# Check if function returns correct index
 */
void testFindCmd4Ext2File()
{
  char *Filename = "../testdata/ext2file.fs";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 25);
}

/**
 * \brief find fat fs
 * \test
 * -# Call FindCmd() on fat file
 * -# Check if function returns correct index
 */
void testFindCmd4FatFile()
{
  char *Filename = "../testdata/fatfile.fs";
  int result = 0;
  result = FindCmd(Filename);
  //FO_ASSERT_EQUAL(result, 22);
  if (result == 17 || result == 23)
  {
    FO_PASS(result);
  }else{
    FO_FAIL(result);
  }

}

/**
 * \brief find ntfs fs
 * \test
 * -# Call FindCmd() on fs file
 * -# Check if function returns correct index
 */
void testFindCmd4NtfsFile()
{
  char *Filename = "../testdata/ntfsfile.fs";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 24);
}

/**
 * \brief find partition file
 * \test
 * -# Call FindCmd() on partition file
 * -# Check if function returns correct index
 * \todo Test file does not exists
 */
void testFindCmd4PartitionFile()
{
  char *Filename = "../testdata/vmlinuz-2.6.26-2-686";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 27);
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo FindCmd_testcases[] =
{
  {"FindCmd: normal:", testFindCmdNormal},
  {"FindCmd: dsc", testFindCmd4DscFile},
  {"FindCmd: cab", testFindCmd4CabFile},
  {"FindCmd: msi", testFindCmd4MsiFile},
  {"FindCmd: rpm", testFindCmd4RpmFile},
  {"FindCmd: iso", testFindCmd4IsoFile},
  {"FindCmd: zip", testFindCmd4ZipFile},
  {"FindCmd: rar", testFindCmd4RarFile},
  {"FindCmd: cpio", testFindCmd4CpioFile},
  {"FindCmd: deb", testFindCmd4DebFile},
  {"FindCmd: archive lib", testFindCmd4ArchiveLibFile},
  {"FindCmd: tar", testFindCmd4TarFile},
  {"FindCmd: Z", testFindCmd4ZFile},
  {"FindCmd: exe", testFindCmd4ExeFile},
  {"FindCmd: bz2", testFindCmd4Bz2File},
  {"FindCmd: ext2 file system", testFindCmd4Ext2File},
  {"FindCmd: ext3 file system", testFindCmd4Ext3File},
  {"FindCmd: fat file system", testFindCmd4FatFile},
  {"FindCmd: ntfs file system", testFindCmd4NtfsFile},
  //{"FindCmd: partition", testFindCmd4PartitionFile},
  CU_TEST_INFO_NULL
};
