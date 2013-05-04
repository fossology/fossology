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
#include <cvector.h>
#include <libfocunit.h>

/* std library includes */
#include <stdio.h>

/* cunit includes */
#include <CUnit/CUnit.h>

/* ************************************************************************** */
/* **** cvector locals ****************************************************** */
/* ************************************************************************** */

struct cvector_internal
{
  int size;                   // the number of element in the cvector
  int capacity;               // the number of elements that data can store
  void** data;                // the array that controls access to the data
  function_registry* memory;  // the memory management functions employed by cvector
};

/* ************************************************************************** */
/* **** function registry tests ********************************************* */
/* ************************************************************************** */

void test_int_function_registry()
{
  function_registry* fr = int_function_registry();
  int tester = 1;

  /* start the tests */
  FO_ASSERT_TRUE(!strcmp(fr->name, "integer"));

  /* test the copy function */
  int* cpy = (int*)fr->copy(&tester);
  FO_ASSERT_EQUAL(*cpy, tester);

  /* test to be sure that it is actually a copy */
  tester = 2;
  FO_ASSERT_NOT_EQUAL(*cpy, tester);

  /* free memory */
  fr->destroy(cpy);
  free(fr);

  /* finish the test */
}

void test_char_function_registry()
{
  function_registry* fr = char_function_registry();
  char tester = 'a';

  /* start the tests */
  FO_ASSERT_TRUE(!strcmp(fr->name, "character"));

  /* test the copy function */
  char* cpy = (char*)fr->copy(&tester);
  FO_ASSERT_EQUAL(*cpy, tester);

  /* test to be sure that it is actually a copy */
  tester = 'b';
  FO_ASSERT_NOT_EQUAL(*cpy, tester);

  /* free memory */
  fr->destroy(cpy);
  free(fr);

  /* finish the test */
}

void test_double_function_registry()
{
  function_registry* fr = double_function_registry();
  double tester = 1.11;

  /* start the tests */
  FO_ASSERT_TRUE(!strcmp(fr->name, "float"));

  /* test the copy function */
  double* cpy = (double*)fr->copy(&tester);
  FO_ASSERT_DOUBLE_EQUAL(*cpy, tester, 0.0001);

  /* test to be sure that it is actually a copy */
  tester = 2.11;
  FO_ASSERT_DOUBLE_NOT_EQUAL(*cpy, tester, 0.0001);

  /* free memory */
  fr->destroy(cpy);
  free(fr);

  /* finish the test */
}

void test_pointer_function_registry()
{
  function_registry* fr = pointer_function_registry();
  int* tester = (int*)&fr;

  /* start the tests */
  FO_ASSERT_TRUE(!strcmp(fr->name, "pointer"));

  /* test the copy function */
  void** cpy = (void**)fr->copy(&tester);
  FO_ASSERT_PTR_EQUAL(*cpy, tester);

  /* test to be sure that it is actually a copy */
  tester = (int*)&tester;
  FO_ASSERT_PTR_NOT_EQUAL(*cpy, tester);

  /* free memory */
  fr->destroy(cpy);
  free(fr);

  /* finish the test */
}

void test_string_function_registry()
{
  function_registry* fr = string_function_registry();
  char* tester = "hello";

  /* start the tests */
  FO_ASSERT_TRUE(!strcmp(fr->name, "string"));

  /* test the copy function */
  char* cpy = (char*)fr->copy(tester);
  FO_ASSERT_TRUE(!strcmp(cpy, tester));

  /* test to be sure that it is actually a copy */
  tester = "world";
  FO_ASSERT_FALSE(!strcmp(cpy, tester));

  /* free memory */
  fr->destroy(cpy);
  free(fr);

  /* finish the test */
}

/* ************************************************************************** */
/* **** cvector tests ******************************************************* */
/* ************************************************************************** */

void test_cvector_init()
{
  cvector vec;

  /* start the test */

  /* start the tests */
  cvector_init(&vec, NULL);
  FO_ASSERT_EQUAL(vec->size, 0);
  FO_ASSERT_EQUAL(vec->capacity, 1);
  FO_ASSERT_PTR_NULL(vec->memory);
  FO_ASSERT_PTR_NOT_NULL(vec->data);

  cvector_destroy(vec);
}

void test_cvector_push_back()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);
  cvector_push_back(vec, NULL);

  /* test the results */
  FO_ASSERT_EQUAL(*(int*)vec->data[0], tester);
  tester = 2;
  FO_ASSERT_NOT_EQUAL(*(int*)vec->data[0], tester);
  FO_ASSERT_EQUAL(vec->size, 2);
  FO_ASSERT_PTR_EQUAL(vec->data[1], NULL);

  cvector_destroy(vec);
}

void test_cvector_insert()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_insert(vec, vec->data, &tester);
  cvector_insert(vec, vec->data, &tester);
  cvector_insert(vec, vec->data, &tester);

  /* test the results */
  FO_ASSERT_EQUAL(*(int*)vec->data[0], tester);
  tester = 2;
  FO_ASSERT_NOT_EQUAL(*(int*)vec->data[0], tester);

  cvector_destroy(vec);
}

void test_cvector_clear()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);
  cvector_push_back(vec, &tester);
  cvector_push_back(vec, &tester);
  cvector_clear(vec);

  /* test the results */
  FO_ASSERT_EQUAL(vec->size, 0);
  FO_ASSERT_PTR_EQUAL(vec->data[0], NULL);

  cvector_destroy(vec);
}

void test_cvector_pop_back()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);
  cvector_pop_back(vec);

  /* test the results */
  FO_ASSERT_EQUAL(vec->size, 0);
  FO_ASSERT_PTR_EQUAL(vec->data[0], NULL);

  cvector_destroy(vec);
}

void test_cvector_remove()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);
  cvector_remove(vec, vec->data);

  /* test the results */
  FO_ASSERT_EQUAL(vec->size, 0);
  FO_ASSERT_PTR_EQUAL(vec->data[0], NULL);

  cvector_destroy(vec);
}

void test_cvector_get()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;
  FO_ASSERT_EQUAL(*(int*)cvector_get(vec, 0), tester);

  cvector_destroy(vec);
}

void test_cvector_at()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;
  FO_ASSERT_EQUAL(*(int*)cvector_at(vec, 0), tester);

  cvector_destroy(vec);
}

void test_cvector_begin()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;
  FO_ASSERT_PTR_EQUAL(cvector_begin(vec), vec->data);

  cvector_destroy(vec);
}

void test_cvector_end()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;
  FO_ASSERT_PTR_EQUAL(cvector_end(vec), vec->data+vec->size);

  cvector_destroy(vec);
}

void test_cvector_size()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());

  /* test the results */;
  FO_ASSERT_EQUAL(cvector_size(vec), 0);
  cvector_push_back(vec, &tester);
  FO_ASSERT_EQUAL(cvector_size(vec), 1);
  cvector_push_back(vec, &tester);
  FO_ASSERT_EQUAL(cvector_size(vec), 2);

  cvector_destroy(vec);
}

void test_cvector_capacity()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());

  /* test the results */;
  FO_ASSERT_EQUAL(cvector_capacity(vec), 1);
  cvector_push_back(vec, &tester);
  FO_ASSERT_EQUAL(cvector_capacity(vec), 1);
  cvector_push_back(vec, &tester);
  FO_ASSERT_EQUAL(cvector_capacity(vec), 2);
  cvector_push_back(vec, &tester);
  FO_ASSERT_EQUAL(cvector_capacity(vec), 4);

  cvector_destroy(vec);
}

/* ************************************************************************** */
/* **** test cvector error checkes ****************************************** */
/* ************************************************************************** */

void test_cvector_at_error()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;


  cvector_destroy(vec);
}

void test_cvector_insert_error()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;


  cvector_destroy(vec);
}

void test_cvector_remove_error()
{
  cvector vec;
  int tester = 1;

  /* start the test */

  /* run the functions */
  cvector_init(&vec, int_function_registry());
  cvector_push_back(vec, &tester);

  /* test the results */;

  cvector_destroy(vec);
}

/* ************************************************************************** */
/* **** cunit test info ***************************************************** */
/* ************************************************************************** */

CU_TestInfo function_registry_testcases[] =
{
    {"Testing int_function_registry:", test_int_function_registry},
    {"Testing char_function_registry:", test_char_function_registry},
    {"Testing double_function_registry:", test_double_function_registry},
    {"Testing pointer_function_registry:", test_pointer_function_registry},
    {"Testing string_function_registry:", test_string_function_registry},
    CU_TEST_INFO_NULL
};

CU_TestInfo cvector_testcases[] =
{
    {"Testing cvector_init:", test_cvector_init},
    {"Testing cvector_push_back:", test_cvector_push_back},
    {"Testing cvector_insert:", test_cvector_insert},
    {"Testing cvector_clear:", test_cvector_clear},
    {"Testing cvector_pop_back:", test_cvector_pop_back},
    {"Testing cvector_remove:", test_cvector_remove},
    {"Testing cvector_get:", test_cvector_get},
    {"Testing cvector_at:", test_cvector_at},
    {"Testing cvector_begin:", test_cvector_begin},
    {"Testing cvector_end:", test_cvector_end},
    {"Testing cvector_size:", test_cvector_size},
    {"Testing cvector_capacity:", test_cvector_capacity},
    {"Testing cvector_at_error:", test_cvector_at_error},
    {"Testing cvector_insert_error:", test_cvector_insert_error},
    {"Testing cvector_remove_error:", test_cvector_remove_error},
    CU_TEST_INFO_NULL
};
