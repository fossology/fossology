/*********************************************************************
Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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
*********************************************************************/
#include "run_tests.h"

/**
 * @brief initialize
 */
int  FindCmdInit()
{
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    return -1;
  }

  magic_load(MagicCookie,NULL);
  return 0;
}

/**
 * @brief clean env and others
 */
int FindCmdClean()
{
  magic_close(MagicCookie);
  return 0;
}

/**
 * @brief find xx.7z
 */
void testFindCmdNormal()
{
  char *Filename = "../test-data/testdata4unpack.7z";
  int result = 0;
  result = FindCmd(Filename);
  //FO_ASSERT_EQUAL(result, 15);
  if (result == 15 || result == 16)
  {
    FO_PASS(result);
  
  }else
  {
    FO_FAIL(result);
  }
}

/**
 * @brief find .dsc
 */
void testFindCmd4DscFile()
{
  char *Filename = "../test-data/testdata4unpack/fcitx_3.6.2-1.dsc";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 27);
}

/**
 * @brief find xx.cab
 */
void testFindCmd4CabFile()
{
  char *Filename = "../test-data/testdata4unpack/SKU011.CAB";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 16);
}

/**
 * @brief find xx.msi
 */
void testFindCmd4MsiFile()
{
  char *Filename = "../test-data/testdata4unpack/xunzai_Contacts.msi.msi";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 16);
}

/**
 * @brief find xx.rpm
 */
void testFindCmd4RpmFile()
{
  char *Filename = "../test-data/testdata4unpack/libgnomeui2-2.24.3-1pclos2010.src.rpm";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 17);
  Filename = "../test-data/testdata4unpack/fossology-1.2.0-1.el5.i386.rpm";
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 17);
}

/**
 * @brief find xx.iso
 */
void testFindCmd4IsoFile()
{
  char *Filename = "../test-data/testdata4unpack/imagefile.iso";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 20);  // let isoinfo handle the isos
}

/**
 * @brief find xx.zip
 */
void testFindCmd4ZipFile()
{
  char *Filename = "../test-data/testdata4unpack/threezip.zip";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 9);
}

/**
 * @brief find xx.rar
 */
void testFindCmd4RarFile()
{
  char *Filename = "../test-data/testdata4unpack/winscp376.rar";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 13);
}

/**
 * @brief find xx.cpio
 */
void testFindCmd4CpioFile()
{
  char *Filename = "../test-data/testdata4unpack/test.cpio";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 12);
}


/**
 * @brief find xx.udeb
 */
void testFindCmd4DebFile()
{
  char *Filename = "../test-data/testdata4unpack/libpango1.0-udeb_1.28.1-1_i386.udeb";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 19);  /* let ar handle udeb */
}

/**
 * @brief find xx.a
 */
void testFindCmd4ArchiveLibFile()
{
  char *Filename = "../test-data/testdata4unpack/libfossagent.a";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 18);
}


/**
 * @brief find xx.tar
 */
void testFindCmd4TarFile()
{
  char *Filename = "../test-data/testdata4unpack/rpm.tar";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 10);
}


/**
 * @brief find xx.Z
 */
void testFindCmd4ZFile()
{
  char *Filename = "../test-data/testdata4unpack/FileName.tar.Z";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 2);
}

/**
 * @brief find xx.exe
 */
void testFindCmd4ExeFile()
{
  char *Filename = "../test-data/testdata4unpack/PUTTY.EXE";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 16);  /* this can be unpacked by 7z */
}

/**
 * @brief find xx.bz2
 */
void testFindCmd4Bz2File()
{
  char *Filename = "../test-data/testdata4unpack/test.tar.bz2";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 3);
}

/**
 * @brief find ext3 fs
 */
void testFindCmd4Ext3File()
{
  char *Filename = "../test-data/testdata4unpack/ext3test-image";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 25);
}

/**
 * @brief find ext2 fs
 */
void testFindCmd4Ext2File()
{
  char *Filename = "../test-data/testdata4unpack/ext2test-image";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 24);
}

/**
 * @brief find fat fs
 */
void testFindCmd4FatFile()
{
  char *Filename = "../test-data/testdata4unpack/fattest-image";
  int result = 0;
  result = FindCmd(Filename);
  //FO_ASSERT_EQUAL(result, 22);
  if (result == 16 || result == 22)
  {
    FO_PASS(result);
  }else{
    FO_FAIL(result);
  }
  
}

/**
 * @brief find ntfs fs
 */
void testFindCmd4NtfsFile()
{
  char *Filename = "../test-data/testdata4unpack/ntfstest-image";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 23);
}

/**
 * @brief find partition
 */
void testFindCmd4PartitionFile()
{
  char *Filename = "../test-data/testdata4unpack/vmlinuz-2.6.26-2-686";
  int result = 0;
  result = FindCmd(Filename);
  FO_ASSERT_EQUAL(result, 26);
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
  {"FindCmd: partition", testFindCmd4PartitionFile},
  CU_TEST_INFO_NULL
};
