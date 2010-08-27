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

/* includes for files that will be tested */
#include <copyright.h>
#include <radixtree.h>

/* library includes */
#include <stdio.h>
#include <pcre.h>

/* cunit includes */
#include <CUnit/CUnit.h>

/* ************************************************************************** */
/* **** copyright local declarations **************************************** */
/* ************************************************************************** */

struct copyright_internal
{
  radix_tree dict;        // the dictionary to search within
  radix_tree name;        // the list of names to match
  cvector entries;        // the set of copyright found in a particular file
  pcre* email_re;         // regex for finding emails
  pcre* url_re;           // the regex for finding emails
  const char* reg_error;  // for regular expression error messages
  int reg_error_offset;   // for regex error offsets
};

struct copy_entry_internal
{
  char text[1024];            // the code that was identified as a copyright
  char name_match[256];       // the name that matched the entry identified as a copyright
  char dict_match[256];       // the dictionary match that originally identified the entry
  unsigned int start_byte;    // the location in the file that this copyright starts
  unsigned int end_byte;      // the location in the file that this copyright ends
  char* type;
};

int find_beginning(char* ptext, int idx);
int find_end(char* ptext, int idex, int bufsize);
void strip_emtry_entries(copyright copy);

/* ************************************************************************** */
/* **** local function tests ************************************************ */
/* ************************************************************************** */

void test_copyright_init()
{
  copyright copy;

  /* start the test */
  printf("Test copyright_init: ");

  /* start the tests */
  copyright_init(&copy);



  copyright_destroy(copy);
  printf("\n");
}

/* ************************************************************************** */
/* **** cunit test info ***************************************************** */
/* ************************************************************************** */

CU_TestInfo copyright_local_testcases[] =
{
    CU_TEST_INFO_NULL
};

CU_TestInfo copyright_testcases[] =
{
    CU_TEST_INFO_NULL
};
