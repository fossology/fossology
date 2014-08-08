/*********************************************************************
Copyright (C) 2014, Siemens AG

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

#include "nomos.h"
#include "util.h"
#include "list.h"
#include "licenses.h"
#include "process.h"
#include "nomos_regex.h"
#include "_autodefs.h"
//nomos globals
extern licText_t licText[]; /* Defined in _autodata.c */
struct globals gl;
struct curScan cur;

/* ************************************************************************** */
/* **** test case sets ****************************************************** */
/* ************************************************************************** */

extern CU_TestInfo nomos_gap_testcases[];
extern CU_TestInfo doctorBuffer_testcases[];
/* ************************************************************************** */
/* **** create test suite *************************************************** */
/* ************************************************************************** */

CU_SuiteInfo suites[] =
{
    {"Testing process:", NULL, NULL, nomos_gap_testcases},
    {"Testing doctor Buffer:", NULL, NULL, doctorBuffer_testcases},
    CU_SUITE_INFO_NULL
};

/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  return focunit_main(argc, argv, "nomos_Util_Tests", suites);
}
