/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/**
 * @file test_fossconfig.c
 * @brief unit tests for the fossconfig library section of libfossology.
 */

/* includes for files that will be tested */
#include <fossconfig.h>

/* library includes */
#include <stdio.h>

/* cunit includes */
#include <CUnit/CUnit.h>

/* ************************************************************************** */
/* *** declaration of private members *************************************** */
/* ************************************************************************** */

fo_conf* test_data;

/* ************************************************************************** */
/* * defines for easy of mapping to test configuration file                 * */
/* *   these are included so that if changes are made to the configuration  * */
/* *   file, these can be changed and that will propagate through all of    * */
/* *   test cases                                                           * */
/* *                                                                        * */
/* * for those unfamiliar with the c preprocessor, the ## operator joins    * */
/* * two strings, so calling "JOIN(GROUP_, 1)" will produce "GROUP_1"       * */
/* * because of this calling "key(1, 2)" should produce "GROUP_1_KEY_2"     * */
/* *                                                                        * */
/* * this means that a change to the test configuration file should mean a  * */
/* * change to only one spot in this file. the group(1) should also be more * */
/* * readable in the error output than GROUP_1 or "one" which is the main   * */
/* * reason why all these #defines were added                               * */
/* ************************************************************************** */

#define CONF_FILE "confdata/conftest.conf"
#define NONE      "none"

#define GROUP(g)         GROUP_##g
#define KEY(g, k)        GROUP_##g##_KEY_##k
#define VAL(g, v)        GROUP_##g##_VALUE_##v
#define VAL_IDX(g, v, i) GROUP_##g##_VALUE_##v##_##i

#define GROUP_0 "one"
#define GROUP_0_KEY_0 "key_a"
#define GROUP_0_VALUE_0 "hello"
#define GROUP_0_KEY_1 "key_b"
#define GROUP_0_VALUE_1 "goodbye"

#define GROUP_1 "two"
#define GROUP_1_KEY_0 "another"
#define GROUP_1_VALUE_0 "value"
#define GROUP_1_KEY_1 "names"
#define GROUP_1_VALUE_1 "Bob, Marry, Mark, Larry, Vincent, Alex"

#define GROUP_2 "three"
#define GROUP_2_KEY_0 "this"
#define GROUP_2_VALUE_0 "group"
#define GROUP_2_KEY_1 "has"
#define GROUP_2_VALUE_1 "3"
#define GROUP_2_KEY_2 "key"
#define GROUP_2_VALUE_2 "literals"

#define GROUP_3 "four"
#define GROUP_3_KEY_0 "is"
#define GROUP_3_VALUE_0_0 "is"
#define GROUP_3_VALUE_0_1 "a"
#define GROUP_3_VALUE_0_2 "list"
#define GROUP_3_VALUE_0_3 "group"
#define GROUP_3_KEY_1 "there"
#define GROUP_3_VALUE_1_0 "there"
#define GROUP_3_VALUE_1_1 "are"
#define GROUP_3_VALUE_1_2 "two"
#define GROUP_3_VALUE_1_3 "lists"
#define GROUP_3_VALUE_1_4 "in"
#define GROUP_3_VALUE_1_5 "this"
#define GROUP_3_VALUE_1_6 "group"
#define GROUP_3_KEY_2 "not"
#define GROUP_3_VALUE_2 "list"

/* ************************************************************************** */
/* *** tests **************************************************************** */
/* ************************************************************************** */

/**
 * @brief test the fo_config_load function. This should check all of the error
 *        conditions using different configuration files as will as a successful
 *        load. It is important to note that if the fo_config_load of the valid
 *        configuration file fails, all following test cases will fail as a
 *        result. Because of this, a failure here will cause a fatal abort of
 *        testing.
 *
 * @return void
 */
void test_fo_config_load()
{
  GError* error = NULL;

  test_data = fo_config_load("dummy", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_missing_file);
  g_clear_error(&error);

  test_data = fo_config_load("confdata/invalid_group.conf", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_file);
  g_clear_error(&error);

  test_data = fo_config_load("confdata/no_group.conf", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_key);
  g_clear_error(&error);

  test_data = fo_config_load("confdata/key_value.conf", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_file);
  g_clear_error(&error);

  test_data = fo_config_load("confdata/bad_key.conf", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_file);
  g_clear_error(&error);

  test_data = fo_config_load("confdata/key_name.conf", &error);
  CU_ASSERT_PTR_NULL(test_data);
  CU_ASSERT_EQUAL(error->domain, PARSE_ERROR);
  CU_ASSERT_EQUAL(error->code, fo_invalid_file);
  g_clear_error(&error);

  test_data = fo_config_load(CONF_FILE, &error);
  if(error)
  {
    CU_FAIL_FATAL("can't load test configuration, aborting");
  }
}

/**
 * @brief Test the group set function. Note that the order the groups are in the
 *        names array is different from the order they are declared in the file.
 *        This is because the names are stored internally in alphabetical order
 *
 * @return void
 */
void test_fo_config_group_set()
{
  int length;
  char** names = fo_config_group_set(test_data, &length);

  CU_ASSERT_EQUAL(length, 4);
  CU_ASSERT_STRING_EQUAL(names[0], GROUP(3));
  CU_ASSERT_STRING_EQUAL(names[1], GROUP(0));
  CU_ASSERT_STRING_EQUAL(names[2], GROUP(2));
  CU_ASSERT_STRING_EQUAL(names[3], GROUP(1));
}

/**
 * @brief Test the key set function. Again, keys are stored in alphabetical
 *        order, so the comparison order may be wonky.
 *
 * @return void
 */
void test_fo_config_key_set()
{
  int length;
  char** names;

  names = fo_config_key_set(test_data, GROUP(0),   &length);
  CU_ASSERT_EQUAL(length, 2);
  CU_ASSERT_STRING_EQUAL(names[0], KEY(0, 0));
  CU_ASSERT_STRING_EQUAL(names[1], KEY(0, 1));

  names = fo_config_key_set(test_data, GROUP(1),   &length);
  CU_ASSERT_EQUAL(length, 2);
  CU_ASSERT_STRING_EQUAL(names[0], KEY(1, 0));
  CU_ASSERT_STRING_EQUAL(names[1], KEY(1, 1));

  names = fo_config_key_set(test_data, GROUP(2), &length);
  CU_ASSERT_EQUAL(length, 3);
  CU_ASSERT_STRING_EQUAL(names[0], KEY(2, 1));
  CU_ASSERT_STRING_EQUAL(names[1], KEY(2, 2));
  CU_ASSERT_STRING_EQUAL(names[2], KEY(2, 0));

  names = fo_config_key_set(test_data, GROUP(3), &length);
  CU_ASSERT_EQUAL(length, 3);
  CU_ASSERT_STRING_EQUAL(names[0], KEY(3, 0));
  CU_ASSERT_STRING_EQUAL(names[1], KEY(3, 2));
  CU_ASSERT_STRING_EQUAL(names[2], KEY(3, 1));

  CU_ASSERT_PTR_NULL(fo_config_key_set(test_data, "none", &length));
}

/**
 * @brief Tests the has group function
 * @return void
 */
void test_fo_config_has_group()
{
  CU_ASSERT_TRUE (fo_config_has_group(test_data, GROUP(0)));
  CU_ASSERT_FALSE(fo_config_has_group(test_data, NONE));
}

/**
 * @brief Test the has key function. There are three cases here because there
 *        are two ways that a config can not have a key. If the key isn't in the
 *        group or the group doesn't exist
 *
 * @return void
 */
void test_fo_config_has_key()
{
  CU_ASSERT_TRUE (fo_config_has_key(test_data, GROUP(0), KEY(0, 0)));
  CU_ASSERT_FALSE(fo_config_has_key(test_data, NONE, KEY(0, 0)));
  CU_ASSERT_FALSE(fo_config_has_key(test_data, GROUP(0), NONE));
}

/**
 * @brief Test the get function. This will also test the error cases of invalid
 *        key and invalid group names.
 *
 * @return
 */
void test_fo_config_get()
{
  GError* error = NULL;

  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(0), KEY(0, 0), &error),
      VAL(0, 0));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(0), KEY(0, 1), &error),
      VAL(0, 1));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(1), KEY(1, 0), &error),
      VAL(1, 0));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(1), KEY(1, 1), &error),
      VAL(1, 1));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(2), KEY(2, 0), &error),
      VAL(2, 0));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(2), KEY(2, 1), &error),
      VAL(2, 1));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(2), KEY(2, 2), &error),
      VAL(2, 2));
  CU_ASSERT_STRING_EQUAL(
      fo_config_get(test_data, GROUP(3), KEY(3, 2), &error),
      VAL(3, 2));

  CU_ASSERT_PTR_NULL(fo_config_get(test_data, GROUP(0), NONE, &error));
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_missing_key);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: unknown key=\"none\" for group=\"one\"");
  g_clear_error(&error);

  CU_ASSERT_PTR_NULL(fo_config_get(test_data, NONE, KEY(0, 0), &error));
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_missing_group);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: unknown group \"none\"");
  g_clear_error(&error);
}

/**
 * @brief Tests the is list function. Tests groups that has both and a group
 *        that doesn't have a list. Error cases are tested elsewhere.
 *
 * @return void
 */
void test_fo_config_is_list()
{
  GError* error = NULL;

  CU_ASSERT_FALSE(fo_config_is_list(test_data, GROUP(3), KEY(0, 0), &error));
  CU_ASSERT_TRUE (fo_config_is_list(test_data, GROUP(3), KEY(3, 0), &error));
  CU_ASSERT_TRUE (fo_config_is_list(test_data, GROUP(3), KEY(3, 1), &error));
  CU_ASSERT_FALSE(fo_config_is_list(test_data, GROUP(3), KEY(3, 2), &error));
}

/**
 * @brief Tests the list length function. Checks both lists in the test file and
 *        tests a none-list key error
 *
 * @return void
 */
void test_fo_config_list_length()
{
  GError* error = NULL;

  CU_ASSERT_EQUAL(
      fo_config_list_length(test_data, GROUP(3), KEY(3, 0), &error),
      4);
  CU_ASSERT_EQUAL(
      fo_config_list_length(test_data, GROUP(3), KEY(3, 1), &error),
      7);
  CU_ASSERT_EQUAL(
      fo_config_list_length(test_data, GROUP(3), KEY(3, 2), &error),
      0);

  CU_ASSERT_PTR_NOT_NULL(error);
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_group);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: four[not] must be of type list to get length");
  g_clear_error(&error);
}

/**
 * @brief Tests the get list function. Tests a none list key, and the index
 *        being out of the valid range.
 *
 * @return void
 */
void test_fo_config_get_list()
{
  GError* error = NULL;

#define CONFIG_GET_LIST_ASSERT(g, k, i) \
  CU_ASSERT_STRING_EQUAL(fo_config_get_list(test_data, \
      GROUP(g), KEY(g, k), i, &error), VAL_IDX(g, k, i))

  CONFIG_GET_LIST_ASSERT(3, 0, 0);
  CONFIG_GET_LIST_ASSERT(3, 0, 1);
  CONFIG_GET_LIST_ASSERT(3, 0, 2);
  CONFIG_GET_LIST_ASSERT(3, 0, 3);

  CONFIG_GET_LIST_ASSERT(3, 1, 0);
  CONFIG_GET_LIST_ASSERT(3, 1, 1);
  CONFIG_GET_LIST_ASSERT(3, 1, 2);
  CONFIG_GET_LIST_ASSERT(3, 1, 3);
  CONFIG_GET_LIST_ASSERT(3, 1, 4);
  CONFIG_GET_LIST_ASSERT(3, 1, 5);
  CONFIG_GET_LIST_ASSERT(3, 1, 6);

#undef CONFIG_GET_LIST_ASSERT

  CU_ASSERT_PTR_NULL(
      fo_config_get_list(test_data, GROUP(3), KEY(3, 2), 0, &error));
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_key);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: four[not] must be of type list to get list element")
  g_clear_error(&error);

  CU_ASSERT_PTR_NULL(
      fo_config_get_list(test_data, GROUP(3), KEY(3, 0), 4, &error));
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_key);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: four[is] 4 is out of range");
  g_clear_error(&error);

  CU_ASSERT_PTR_NULL(
      fo_config_get_list(test_data, GROUP(3), KEY(3, 0), -1, &error));
  CU_ASSERT_EQUAL(error->domain, RETRIEVE_ERROR);
  CU_ASSERT_EQUAL(error->code,   fo_invalid_key);
  CU_ASSERT_STRING_EQUAL(error->message,
      "ERROR: four[is] -1 is out of range");
  g_clear_error(&error);
}

/**
 * @brief Tests the config free function. This makes sure that everything is
 *        correctly set to NULL after a free.
 *
 * @return void
 */
void test_fo_config_free()
{
  fo_config_free(test_data);
}

/* ************************************************************************** */
/* *** cunit test info ****************************************************** */
/* ************************************************************************** */

CU_TestInfo fossconfig_testcases[] =
{
    { "fo_config_load()",         test_fo_config_load         },
    { "fo_config_group_set()",    test_fo_config_group_set    },
    { "fo_config_key_set()",      test_fo_config_key_set      },
    { "fo_config_has_group()",    test_fo_config_has_group    },
    { "fo_config_has_key()",      test_fo_config_has_key      },
    { "fo_config_get()",          test_fo_config_get          },
    { "fo_config_is_list()",      test_fo_config_is_list      },
    { "fo_config_list_length()",  test_fo_config_list_length  },
    { "fo_config_get_list()",     test_fo_config_get_list     },
    { "fo_config_free()",         test_fo_config_free         },
    CU_TEST_INFO_NULL
};
