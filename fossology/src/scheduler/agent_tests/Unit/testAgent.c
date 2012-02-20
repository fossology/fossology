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

/* include functions to test */
#include <agent.h>
#include <libfocunit.h>

/* cunit include */
#include <CUnit/CUnit.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

extern GTree* meta_agents;
extern GTree* agents;
extern GRegex* heart_regex;

extern FILE* log_file;

/* ************************************************************************** */
/* **** suite initializations *********************************************** */
/* ************************************************************************** */

/**
 * We don't want to actually generate any error messages. To do this, the log
 * file will be set to /dev/null.
 *
 * @return -1 on failure, 0 of success
 */
int init_agent_suite(void)
{
  if(log_file && fclose(log_file) != 0)
    return -1;
  if((log_file = fopen("/dev/null", "w+")) == NULL)
    return -1;
  return 0;
}

/**
 * Since we changed the log file in the initializations, we need to close it
 * and set the pointer to NULL so that the logging system can reset it to the
 * correct value.
 *
 * @return -1 of failure, 0 on success
 */
int clean_agent_suite(void)
{
  if(fclose(log_file) != 0)
    return -1;

  log_file = NULL;
  return 0;
}

/* ************************************************************************** */
/* **** standard function tests ********************************************* */
/* ************************************************************************** */

void test_meta_agent_init()
{
  char* name = "copyright";
  char* cmmd  = name;
  int   max  = 11;
  int   spc  = 0;

  meta_agent ma = meta_agent_init(name, cmmd, max, spc);

  FO_ASSERT_PTR_NOT_NULL_FATAL(ma);
  FO_ASSERT_STRING_EQUAL(ma->name,    "copyright");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "copyright --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, max);
  FO_ASSERT_EQUAL(ma->special, spc);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);

  g_free(ma);

  FO_ASSERT_PTR_NULL(meta_agent_init(NULL, cmmd, max, spc));
  FO_ASSERT_PTR_NULL(meta_agent_init(name, NULL, max, spc));
}

void test_add_meta_agent()
{
  meta_agent ma;

  FO_ASSERT_TRUE(add_meta_agent("name", "cmd", 11, 1));
  FO_ASSERT_FALSE(add_meta_agent(NULL, "cmd", 11, 1));

  FO_ASSERT_EQUAL(g_tree_nnodes(meta_agents), 1);
  FO_ASSERT_PTR_NOT_NULL((ma = g_tree_lookup(meta_agents, "name")));
  FO_ASSERT_STRING_EQUAL(ma->name, "name");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "cmd --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, 11);
  FO_ASSERT_EQUAL(ma->special, 1);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);

  g_free(ma);
  g_tree_remove(meta_agents, "name");
}

void test_agent_list_init()
{
  FO_ASSERT_PTR_NULL(meta_agents);
  FO_ASSERT_PTR_NULL(agents);
  FO_ASSERT_PTR_NULL(heart_regex);

  agent_list_init();

  FO_ASSERT_PTR_NOT_NULL(meta_agents);
  FO_ASSERT_PTR_NOT_NULL(agents);
  FO_ASSERT_PTR_NOT_NULL(heart_regex);
}

void test_agent_list_clear()
{
  agent_list_clean();

  FO_ASSERT_PTR_NOT_NULL(meta_agents);
  FO_ASSERT_PTR_NOT_NULL(agents);
  FO_ASSERT_PTR_NOT_NULL(heart_regex);
  FO_ASSERT_EQUAL(g_tree_nnodes(meta_agents), 0);
  FO_ASSERT_EQUAL(g_tree_nnodes(agents), 0);
}

CU_TestInfo tests_agent[] =
{
    {"Test meta_agent_init",  test_meta_agent_init },
    {"Test agent_list_init",  test_agent_list_init },
    {"Test agent_list_clear", test_agent_list_clear},
    CU_TEST_INFO_NULL
};

