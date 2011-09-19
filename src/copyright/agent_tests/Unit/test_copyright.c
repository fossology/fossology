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

/* a global copyright object. This is used to avoid needing to initialize a   */
/* new copyright every time a test is run. This is done because copyrights    */
/* are a very expensive object to create                                      */
copyright copy;

/* external function to test if a particular test failed */
extern void (*test_failure)(void);

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
void strip_empty_entries(copyright copy);
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

void test_find_beginning()
{
  /* set up the test params */
  char* text = "hello this is a test, hello this is a test, hello this is a test,\nhello this is a test, hello this is a test";

  /* start the test */
  printf("Test find_beginning: ");

  /* run some tests, these numbers index to specific locations in the above */
  /* string, i.e. they are magic numbers, only change them if you know the  */
  /* correct indices in the above string                                    */
  CU_ASSERT_EQUAL(find_beginning(text, 10), 5);
  CU_ASSERT_EQUAL(find_beginning(text, 60), 59);
  CU_ASSERT_EQUAL(find_beginning(text, 70), 65);

  test_failure();
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

  test_failure();
  printf("\n");
}

void test_strip_empty_entries()
{
  struct copy_entry_internal entry;

  /* start the test */
  printf("Test string_empty_entries: ");

  /* create a copy_entry to copy */
  copy_entry_init(&entry);
  entry.dict_match[0] = 'a';
  cvector_push_back(copy->entries, &entry);
  entry.name_match[0] = 'b';
  cvector_push_back(copy->entries, &entry);
  entry.dict_match[0] = '\0';
  cvector_push_back(copy->entries, &entry);
  strip_empty_entries(copy);

  /* do the asserts */
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 1);

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

void test_contains_copyright()
{
  char buffer[256];

  /* start the test */
  printf("Test contains_copyright: ");

  /* run the tests */
  CU_ASSERT_EQUAL(
      contains_copyright(copy->dict, "This does contain a copyright", buffer), 20);
  CU_ASSERT_TRUE(!strcmp(buffer, "copyright"));
  CU_ASSERT_EQUAL(
      contains_copyright(copy->dict, "This does not contain one", buffer), 25);
  CU_ASSERT_EQUAL(strlen(buffer), 0);

  test_failure();
  printf("\n");
}

void test_load_dictionary()
{
  radix_tree tree;

  /* start the test */
  printf("Test load_dictionary: ");
  radix_init(&tree);

  /* do the asserts */
  CU_ASSERT_TRUE(load_dictionary(tree, "../../agent/copyright.dic"));
  CU_ASSERT_TRUE(radix_contains(tree, "copyright"));
  CU_ASSERT_TRUE(radix_contains(tree, "(c)"));
  CU_ASSERT_TRUE(radix_contains(tree, "author"));
  CU_ASSERT_FALSE(radix_contains(tree, "hello"));
  CU_ASSERT_FALSE(load_dictionary(tree, "bad filename"));

  radix_destroy(tree);
  test_failure();
  printf("\n");
}

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

  test_failure();
  printf("\n");
}

void test_copy_entry_copy()
{
  struct copy_entry_internal entry;

  /* start the test */
  printf("Test copy_entry_copy: ");

  /* create a copy_entry to copy */
  copy_entry_init(&entry);
  strcpy(entry.text, "hello");
  strcpy(entry.name_match, "Alex");
  strcpy(entry.dict_match, "copyright");

  /* perform the copy */
  copy_entry cpy = copy_entry_copy(&entry);

  /* run the asserts */
  CU_ASSERT_TRUE(!strcmp(cpy->text, "hello"));
  CU_ASSERT_TRUE(!strcmp(cpy->name_match, "Alex"));
  CU_ASSERT_TRUE(!strcmp(cpy->dict_match, "copyright"));
  strcpy(entry.text, "not hello");
  strcpy(entry.name_match, "Zach");
  strcpy(entry.dict_match, "(c)");
  CU_ASSERT_TRUE(!strcmp(cpy->text, "hello"));
  CU_ASSERT_TRUE(!strcmp(cpy->name_match, "Alex"));
  CU_ASSERT_TRUE(!strcmp(cpy->dict_match, "copyright"));

  free(cpy);
  test_failure();
  printf("\n");
}

void test_copyright_callout()
{
  pcre_callout_block info;

  /* start the test */
  printf("Test test_copyright_callout: ");
  info.callout_data = copy->entries;
  info.subject = "This is just a testing string";
  info.start_match = 0;
  info.current_position = 10;
  info.callout_number = 1;

  /* call the test methods */
  CU_ASSERT_EQUAL(copyright_callout(&info), 1);
  info.start_match = 1;
  info.current_position = 9;
  CU_ASSERT_EQUAL(copyright_callout(&info), 1);
  info.start_match = 11;
  info.current_position = 15;
  CU_ASSERT_EQUAL(copyright_callout(&info), 1);
  info.start_match = 1;
  info.current_position = 11;
  info.callout_number = 2;
  CU_ASSERT_EQUAL(copyright_callout(&info), 1);
  info.callout_number = 3;
  CU_ASSERT_EQUAL(copyright_callout(&info), 1);

  /* do the asserts */
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 3);
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 0))->name_match, "email"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 0))->dict_match, "email"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 2))->name_match, "url"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 2))->dict_match, "url"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 0))->text, "This is ju"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 2))->text, "his is jus"));

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

/* ************************************************************************** */
/* **** standard function tests ********************************************* */
/* ************************************************************************** */

void test_copyright_init()
{
  /* start the test */
  printf("Test copyright_init:");

  /* start the tests */
  CU_ASSERT_TRUE(copyright_init(&copy));

  test_failure();
  printf("\n");
}

void test_copyright_destroy()
{
  /* start the test */
  printf("Test copyright_destroy:");

  /* start the tests */
  copyright_destroy(copy);

  test_failure();
  printf("\n");
}

void test_copyright_clear()
{
  struct copy_entry_internal entry;

  /* start the test */
  printf("Test copyright_clear: ");

  /* create a copy_entry to copy */
  copy_entry_init(&entry);

  /* run the functions */
  cvector_push_back(copy->entries, &entry);
  cvector_push_back(copy->entries, &entry);

  /* do the asserts */
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 2);
  copyright_clear(copy);
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 0);

  test_failure();
  printf("\n");
}

void test_copyright_analyze()
{
  FILE* istr;

  /* start the test */
  printf("Test copyright_analyze: ");

  istr = fopen("testcase", "r");
  if(!istr) {
    return;
  }

  copyright_analyze(copy, istr);
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 5);
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 0))->text,
      "copyright (c) 2010 john not_a_person smith"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 1))->text,
      "written by john not_a_person smith"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 2))->text,
      "copyright (c) 2009, 2010"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 3))->text,
      "<smith.not.john@not.a.url>"));
  CU_ASSERT_TRUE(!strcmp(((copy_entry)cvector_get(copy->entries, 4))->text,
      "http://www.not.a.url/index.html"));

  test_failure();
  printf("\n");
}

void test_copyright_email_url()
{
  /* start the test */
  printf("Test copyright_email_url: ");

  /* run the tests */
  copyright_email_url(copy, "test.email@url.com http://www.testurl.com not_a_url");
  CU_ASSERT_EQUAL(cvector_size(copy->entries), 2);
  CU_ASSERT_TRUE(!strcmp(cvector_get(copy->entries, 0), "test.email@url.com"));
  CU_ASSERT_TRUE(!strcmp(cvector_get(copy->entries, 1), "http://www.testurl.com"));

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

void test_copyright_begin()
{
  /* start the test */
  printf("Test copy_copyright_begin: ");

  /* make sure that the correct location is returned */
  CU_ASSERT_EQUAL((cvector_iterator)copyright_begin(copy), cvector_begin(copy->entries));

  test_failure();
  printf("\n");
}

void test_copyright_end()
{
  /* start the test */
  printf("Test copy_copyright_end: ");

  /* make sure that the correct location is returned */
  CU_ASSERT_EQUAL((cvector_iterator)copyright_end(copy), cvector_end(copy->entries));

  test_failure();
  printf("\n");
}

void test_copyright_at()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copyright_at: ");

  /* create a copy_entry for use during function */
  copy_entry_init(&entry);
  strcpy(entry.text, "words");
  cvector_push_back(copy->entries, &entry);

  /* run the asserts */
  CU_ASSERT_TRUE(!strcmp(copyright_at(copy, 0)->text, "words"));

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

void test_copyright_get()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copyright_get: ");

  /* create a copy_entry for use during function */
  copy_entry_init(&entry);
  strcpy(entry.text, "words");
  cvector_push_back(copy->entries, &entry);

  /* run the asserts */
  CU_ASSERT_TRUE(!strcmp(copyright_get(copy, 0)->text, "words"));

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

void test_copyright_size()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copyright_size: ");

  /* create a copy_entry for use during function */
  copy_entry_init(&entry);
  cvector_push_back(copy->entries, &entry);

  /* run the asserts */
  CU_ASSERT_EQUAL(copyright_size(copy), 1);

  cvector_clear(copy->entries);
  test_failure();
  printf("\n");
}

void test_copy_entry_text()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_text: ");

  /* perform the tests */
  strcpy(entry.text, "This is simple text");
  CU_ASSERT_TRUE(!strcmp(copy_entry_text(&entry), "This is simple text"));

  test_failure();
  printf("\n");
}

void test_copy_entry_name()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_name: ");

  /* perform the tests */
  strcpy(entry.name_match, "Alex");
  CU_ASSERT_TRUE(!strcmp(copy_entry_name(&entry), "Alex"));

  test_failure();
  printf("\n");
}

void test_copy_entry_dict()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_dict: ");

  /* perform the tests */
  strcpy(entry.dict_match, "copyright");
  CU_ASSERT_TRUE(!strcmp(copy_entry_dict(&entry), "copyright"));

  test_failure();
  printf("\n");
}

void test_copy_entry_type()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_type: ");

  /* perform the tests */
  entry.type = "statement";
  CU_ASSERT_TRUE(!strcmp(copy_entry_type(&entry), "statement"));

  test_failure();
  printf("\n");
}

void test_copy_entry_start()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_start: ");

  /* perform the tests */
  entry.start_byte = 100;
  CU_ASSERT_EQUAL(copy_entry_start(&entry), 100);

  test_failure();
  printf("\n");
}

void test_copy_entry_end()
{
  struct copy_entry_internal entry;

  /* star the test */
  printf("Test copy_entry_end: ");

  /* perform the tests */
  entry.end_byte = 100;
  CU_ASSERT_EQUAL(copy_entry_end(&entry), 100);

  test_failure();
  printf("\n");
}

/* ************************************************************************** */
/* **** cunit test info ***************************************************** */
/* ************************************************************************** */

CU_TestInfo copyright_testcases[] =
{
    {"Testing load_dictionary:", test_load_dictionary},
    {"Testing copyright_init:", test_copyright_init},
    {"Testing copy_entry_copy:", test_copy_entry_copy},
    {"Testing copy_entry_init:", test_copy_entry_init},
    {"Testing find_beginning:", test_find_beginning},
    {"Testing find_end:", test_find_end},
    {"Testing copyright_clear:", test_copyright_clear},
    {"Testing copyright_at:", test_copyright_at},
    {"Testing copyright_get:", test_copyright_get},
    {"Testing copyright_size:", test_copyright_size},
    {"Testing strip_emtpy_entries:", test_strip_empty_entries},
    {"Testing contains_copyright:", test_contains_copyright},
    {"Testing copyright_callout:", test_copyright_callout},
    {"Testing copyright_begin:", test_copyright_begin},
    {"Testing copyright_end:", test_copyright_end},
    {"Testing copy_entry_text:", test_copy_entry_text},
    {"Testing copy_entry_name:", test_copy_entry_name},
    {"Testing copy_entry_dict:", test_copy_entry_dict},
    {"Testing copy_entry_type:", test_copy_entry_type},
    {"Testing copy_entry_start:", test_copy_entry_start},
    {"Testing copy_entry_end:", test_copy_entry_end},
    {"Testing copyright_email_url:", test_copyright_email_url},
    {"Testing copyright_analyze:", test_copyright_analyze},
    {"Testing copyright_destroy:", test_copyright_destroy},
    CU_TEST_INFO_NULL
};
