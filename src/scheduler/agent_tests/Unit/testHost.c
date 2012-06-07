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
#include <testRun.h>
#include <host.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

extern GTree* host_list;
extern GList* host_queue;

host h;
int visit;

void host_traverse(host ho)
{
  visit++;
}

/* ************************************************************************** */
/* **** host function tests ************************************************* */
/* ************************************************************************** */

void test_host_list_init()
{
  FO_ASSERT_PTR_NULL(host_list);
  FO_ASSERT_PTR_NULL(host_queue);

  host_list_init();

  FO_ASSERT_PTR_NOT_NULL(host_list);
  FO_ASSERT_PTR_NULL(host_queue);
}

void test_host_list_clean()
{

  FO_ASSERT_PTR_NOT_NULL(host_list);
  FO_ASSERT_EQUAL(g_tree_nnodes(host_list), 3);

  host_list_clean();

  FO_ASSERT_PTR_NOT_NULL(host_list);
  FO_ASSERT_PTR_NULL(host_queue);
  FO_ASSERT_EQUAL(g_tree_nnodes(host_list), 0);

  g_tree_destroy(host_list);
}

void test_host_init()
{
  host_init("local", "localhost", "directory", 8);
  h = g_tree_lookup(host_list, "local");

  FO_ASSERT_PTR_NOT_NULL(h);
  FO_ASSERT_STRING_EQUAL(h->name, "local");
  FO_ASSERT_STRING_EQUAL(h->address, "localhost");
  FO_ASSERT_STRING_EQUAL(h->agent_dir, "directory");
  FO_ASSERT_EQUAL(h->max, 8);
  FO_ASSERT_EQUAL(h->running, 0);

  FO_ASSERT_PTR_NOT_NULL(host_queue);
  FO_ASSERT_EQUAL(g_tree_nnodes(host_list), 1);
  FO_ASSERT_EQUAL(g_list_length(host_queue), 1);

  host_init("other", "localhost", "directory", 3);
  host_init("last", "localhost", "directory", 3);
}

void test_host_increase_load()
{
  host_increase_load(h);

  FO_ASSERT_EQUAL(h->running, 1);
}

void test_host_decrease_load()
{
  host_decrease_load(h);

  FO_ASSERT_EQUAL(h->running, 0);
}

void test_get_host()
{
  host got = get_host(1);
  FO_ASSERT_PTR_EQUAL(got, h);
  got = get_host(1);
  FO_ASSERT_PTR_EQUAL(got, g_tree_lookup(host_list, "other"));
  got = get_host(1);
  FO_ASSERT_PTR_EQUAL(got, g_tree_lookup(host_list, "last"));
  got = get_host(1);
  FO_ASSERT_PTR_EQUAL(got, h);
  got = get_host(4);
  FO_ASSERT_PTR_EQUAL(got, h);
}

void test_for_each_host()
{
  visit = 0;

  for_each_host(host_traverse);

  FO_ASSERT_EQUAL(visit, g_tree_nnodes(host_list));
}

void test_num_hosts()
{
  FO_ASSERT_EQUAL(num_hosts(), g_tree_nnodes(host_list));
}

/* ************************************************************************** */
/* *** suite decl *********************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_host[] =
{

    {"Test host_list_init",     test_host_list_init     },
    {"Test host_init",          test_host_init          },
    {"Test host_increase_load", test_host_increase_load },
    {"Test host_decrease_load", test_host_decrease_load },
    {"Test host_get_host",      test_get_host           },
    {"Test for_each_host",      test_for_each_host      },
    {"Test num_hosts",          test_num_hosts          },
    {"Test host_list_clean",    test_host_list_clean    },
    CU_TEST_INFO_NULL
};

