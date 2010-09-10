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
#include <cvector.h>

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

void  copy_entry_init(copy_entry entry);
int find_beginning(char* ptext, int idx);
int find_end(char* ptext, int idex, int bufsize);
void strip_emty_entries(copyright copy);
int contains_copyright(radix_tree tree, char* string, char* buf);
int load_dictionary(radix_tree dict, char* filename);
void* copy_entry_copy(void* to_copy);
void  copy_entry_destroy();
void  copy_entry_print(void* to_print, FILE* ostr);
function_registry* copy_entry_function_registry();
int copyright_callout(pcre_callout_block* info);

/* ************************************************************************** */
/* **** local function tests ************************************************ */
/* ************************************************************************** */

void test_copy_entry_init()
{
  /* set up the test params */
  struct copy_entry_internal entry;

  /* start the test */
  printf("Test copy_entry_init: ");
  copy_entry_init(&entry);

  /* check the results */
  CU_ASSERT_EQUAL(entry.text[0], '\0');
  CU_ASSERT_EQUAL(entry.name_match[0], '\0');
  CU_ASSERT_EQUAL(entry.dict_match[0], '\0');
  CU_ASSERT_EQUAL(entry.start_byte, 0);
  CU_ASSERT_EQUAL(entry.end_byte, 0);
  CU_ASSERT_EQUAL(entry.type, NULL);

  printf("\n");
}

void test_find_beginning()
{
  /* set up the test params */
  char* text = "hello this is a test, hello this is a test, hello this is a test,\nhello this is a test, hello this is a test";

  /* start the test */
  printf("Test find_beginning: ");

  /* run some tests, these numbers index to specific locations in the above */
  /* string, i.e. they are magic numbers, only change them if you know the  */
  /* correct indices in the above string                                    */
  CU_ASSERT_EQUAL(find_beginning(text, 10), 0);
  CU_ASSERT_EQUAL(find_beginning(text, 60), 10);
  CU_ASSERT_EQUAL(find_beginning(text, 70), 65);

  printf("\n");
}

void test_find_end()
{
  /* set up the test params */
  char* text = "hello this is a test, hello this is a test, hello this is a test,\nhello this is a test, hello this is a test";

  /* start the test */
  printf("Test find_end: ");

  /* run some tests, these numbers index to specific locations in the above */
  /* string, i.e. they are magic numbers, only change them if you know the  */
  /* correct indices in the above string                                    */
  CU_ASSERT_EQUAL(find_end(text, 0, strlen(text)), 64);
  CU_ASSERT_EQUAL(find_end(text, 70, strlen(text)), 108);

  printf("\n");
}

void test_strip_empty_entries()
{
  copyright copy;

  /* start the test */
  printf("Test strip_empty_entries: NOT IMPLEMENTED");
  copyright_init(&copy);

  // TODO implement

  printf("\n");
}

void test_load_dictionary()
{
  radix_tree tree;

  /* start the test */
  printf("Test load_dictionary: ");
  radix_init(&tree);

  /* do the asserts */
  CU_ASSERT_TRUE(load_dictionary(tree, "../../copyright.dic"));
  CU_ASSERT_TRUE(radix_contains(tree, "copyright"));
  CU_ASSERT_TRUE(radix_contains(tree, "(c)"));
  CU_ASSERT_TRUE(radix_contains(tree, "author"));
  CU_ASSERT_FALSE(load_dictionary(tree, "bad filename"));

  radix_destroy(tree);
  printf("\n");
}

/* ************************************************************************** */
/* **** standard function tests ********************************************* */
/* ************************************************************************** */

void test_copyright_init()
{
  copyright copy;

  /* start the test */
  printf("Test copyright_init: NOT IMPLEMENTED");

  /* start the tests */
  CU_ASSERT_TRUE(copyright_init(&copy));

  copyright_destroy(copy);
  printf("\n");
}

/* ************************************************************************** */
/* **** cunit test info ***************************************************** */
/* ************************************************************************** */

CU_TestInfo copyright_testcases[] =
{
    {"Testing copy_entry_init:", test_copy_entry_init},
    {"Testing find_beginning:", test_find_beginning},
    {"Testing find_end:", test_find_end},
    {"Testing load_dictionary:", test_load_dictionary},
    {"Testing copyright_init:", test_copyright_init},
    {"Testing strip_emtpy_entries:", test_strip_empty_entries},
    CU_TEST_INFO_NULL
};
