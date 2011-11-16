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

/* local includes */
#include <libfocunit.h>

/* std library includes */
#include <stdio.h>
#include <assert.h>

/* cunit includes */
#include <CUnit/CUnit.h>
#include <CUnit/Automated.h>

/* ************************************************************************** */
/* **** test case sets ****************************************************** */
/* ************************************************************************** */

extern CU_TestInfo function_registry_testcases[];
extern CU_TestInfo cvector_testcases[];
extern CU_TestInfo copyright_testcases[];
extern CU_TestInfo radix_testcases[];

/* ************************************************************************** */
/* **** create test suite *************************************************** */
/* ************************************************************************** */

CU_SuiteInfo suites[] =
{
    {"Testing function registry:", NULL, NULL, function_registry_testcases},
    {"Testing cvector:", NULL, NULL, cvector_testcases},
    {"Testing radix tree:", NULL, NULL,  radix_testcases},
    {"Testing copyright:", NULL, NULL, copyright_testcases},
    CU_SUITE_INFO_NULL
};

/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  return focunit_main(argc, argv, "copyright_Tests", suites);
}




