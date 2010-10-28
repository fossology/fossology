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
#include "ununpack-disk.h"
#include "utility.h"

/* locals */
static char *Name = NULL;

/* test functions */
void FatDiskName(char *name);

/**
 * @brief Convert to lowercase.
 */
void testFatDiskName1()
{
  Name = (char *)malloc(100);
  strcpy(Name, "Fossology\0");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fossology"), 0); 
  printf("name is :%s\n", Name);
}
 
void testFatDiskName2()
{
  strcpy(Name, "Fosso");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);

  printf("name is :%s\n", Name);
  strcpy(Name, "FOSSOLOGY HELLO\0");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fossology hello"), 0);
  printf("name is :%s\n", Name);
}

void testFatDiskNameNameEmpty()
{
  strcpy(Name, "");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, ""), 0);
  printf("name is :%s\n", Name);
}

CU_TestInfo FatDiskName_testcases[] =
{
    {"Testing function FatDiskName, 1:", testFatDiskName1},
    {"Testing function FatDiskName, 2:", testFatDiskName2},
    {"Testing function FatDiskName, the parameter is empty:", testFatDiskNameNameEmpty},
    CU_TEST_INFO_NULL
};
