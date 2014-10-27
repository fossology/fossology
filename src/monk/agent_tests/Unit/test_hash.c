/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>

#include "hash.h"

void test_hash1() {
  CU_ASSERT_NOT_EQUAL(hash(""), hash("t"));
  CU_ASSERT_NOT_EQUAL(hash("t"), hash("test"));
  CU_ASSERT_NOT_EQUAL(hash("test"), hash("test1"));
}

void test_hash2() {
  CU_ASSERT_EQUAL(hash("a\0b"), hash("a"));
}

CU_TestInfo hash_testcases[] = {
  {"Testing extracting Hash test1:", test_hash1},
  {"Testing extracting Hash test2:", test_hash2},
  CU_TEST_INFO_NULL
};
