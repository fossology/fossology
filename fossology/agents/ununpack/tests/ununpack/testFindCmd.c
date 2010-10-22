/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
/* cunit includes */
#include <CUnit/CUnit.h>
#include "ununpack.h"
#include "utility.h"

int	FindCmd	(char *Filename);

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
  CU_ASSERT_EQUAL(result, 14);
  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find .dsc
 */
void testFindCmd4DscFile()
{
  char *Filename = "../test-data/testdata4unpack/fcitx_3.6.2-1.dsc";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 26);
  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif  
}

/**
 * @brief find xx.cab
 */
void testFindCmd4CabFile()
{
  char *Filename = "../test-data/testdata4unpack/SKU011.CAB";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 15);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.msi
 */
void testFindCmd4MsiFile()
{
  char *Filename = "../test-data/testdata4unpack/xunzai_Contacts.msi.msi";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 15);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif

}

/**
 * @brief find xx.rpm
 */
void testFindCmd4RpmFile()
{
  char *Filename = "../test-data/testdata4unpack/libgnomeui2-2.24.3-1pclos2010.src.rpm";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 16);
  Filename = "../test-data/testdata4unpack/fossology-1.2.0-1.el5.i386.rpm";
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 16);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif

}

/**
 * @brief find xx.iso
 */
void testFindCmd4IsoFile()
{
  char *Filename = "../test-data/testdata4unpack/imagefile.iso";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 19);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.zip
 */
void testFindCmd4ZipFile()
{
  char *Filename = "../test-data/testdata4unpack/threezip.zip";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 8);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.rar
 */
void testFindCmd4RarFile()
{
  char *Filename = "../test-data/testdata4unpack/winscp376.rar";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 12);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.cpio
 */
void testFindCmd4CpioFile()
{
  char *Filename = "../test-data/testdata4unpack/test.cpio";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 11);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}


/**
 * @brief find xx.udeb
 */
void testFindCmd4DebFile()
{
  char *Filename = "../test-data/testdata4unpack/libpango1.0-udeb_1.28.1-1_i386.udeb";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 18);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.a
 */
void testFindCmd4ArchiveLibFile()
{
  char *Filename = "../test-data/testdata4unpack/libfossagent.a";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 17);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}


/**
 * @brief find xx.tar
 */
void testFindCmd4TarFile()
{
  char *Filename = "../test-data/testdata4unpack/rpm.tar";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 9);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}


/**
 * @brief find xx.Z
 */
void testFindCmd4ZFile()
{
  char *Filename = "../test-data/testdata4unpack/FileName.tar.Z";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 2);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.exe
 */
void testFindCmd4ExeFile()
{
  char *Filename = "../test-data/testdata4unpack/PUTTY.EXE";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 27);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find xx.bz2
 */
void testFindCmd4Bz2File()
{
  char *Filename = "../test-data/testdata4unpack/test.tar.bz2";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 3);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}

/**
 * @brief find ext2 fs
 */
void testFindCmd4Ext2File()
{
  char *Filename = "../test-data/testdata4unpack/ext2test-image";
  int result = 0;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 23);

  #ifdef DEBUG
  printf("result is :%d\n", result);
  #endif
}


CU_TestInfo FindCmd_testcases[] =
{
    {"Testing FindCmd normal:", testFindCmdNormal},
    {"Testing FindCmd dsc file:", testFindCmd4DscFile},
    {"Testing FindCmd cab file:", testFindCmd4CabFile},
    {"Testing FindCmd msi file:", testFindCmd4MsiFile},
    {"Testing FindCmd rpm file:", testFindCmd4RpmFile},
    {"Testing FindCmd iso file:", testFindCmd4IsoFile},
    {"Testing FindCmd zip file:", testFindCmd4ZipFile},
    {"Testing FindCmd rar file:", testFindCmd4RarFile},
    {"Testing FindCmd cpio file:", testFindCmd4CpioFile},
    {"Testing FindCmd deb file:", testFindCmd4DebFile},
    {"Testing FindCmd archive lib file:", testFindCmd4ArchiveLibFile},
    {"Testing FindCmd tar file:", testFindCmd4TarFile},
    {"Testing FindCmd Z file:", testFindCmd4ZFile},
    {"Testing FindCmd exe file:", testFindCmd4ExeFile},
    {"Testing FindCmd bz2 file:", testFindCmd4Bz2File},
    {"Testing FindCmd ext2 file system:", testFindCmd4Ext2File},
    CU_TEST_INFO_NULL
};
