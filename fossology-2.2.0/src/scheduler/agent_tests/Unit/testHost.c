/*********************************************************************
Copyright (C) 2011, 2012 Hewlett-Packard Development Company, L.P.

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
/* **** host function tests ************************************************* */
/* ************************************************************************** */

void test_host_init()
{
  host_t* host;

  host = host_init("local", "localhost", "directory", 10);
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "local");
  FO_ASSERT_STRING_EQUAL(host->address, "localhost");
  FO_ASSERT_STRING_EQUAL(host->agent_dir, "directory");
  FO_ASSERT_EQUAL(host->max, 10);
  FO_ASSERT_EQUAL(host->running, 0);

  host_destroy(host);
}

void test_host_insert()
{
  scheduler_t* scheduler;
  gint list_size;
  gint queue_size;
  uint32_t i;
  GList* iter;
  gchar* name = g_strdup(" _local");

  scheduler = scheduler_init(testdb, NULL);

  /* add 10 hosts to the scheduler */
  for(i = 0; i < 9; i++)
  {
    name[0] = (char)('1' + i);
    host_insert(host_init(name, "localhost", "directory", i), scheduler);

    list_size  = g_tree_nnodes(scheduler->host_list);
    queue_size = g_list_length(scheduler->host_queue);
    FO_ASSERT_EQUAL(list_size,  i + 1);
    FO_ASSERT_EQUAL(queue_size, i + 1);
  }

  list_size  = g_tree_nnodes(scheduler->host_list);
  queue_size = g_list_length(scheduler->host_queue);
  FO_ASSERT_EQUAL(list_size,  9);
  FO_ASSERT_EQUAL(queue_size, 9);

  /* make sure they are in the correct order */
  for(iter = scheduler->host_queue, i = 0; iter != NULL; iter = iter->next, i++)
    FO_ASSERT_EQUAL(((host_t*)iter->data)->max, i);

  scheduler_destroy(scheduler);
  g_free(name);
}

void test_host_increase_load()
{
  host_t* host = host_init("local", "localhost", "directory", 10);

  FO_ASSERT_EQUAL(host->running, 0);
  host_increase_load(host);
  FO_ASSERT_EQUAL(host->running, 1);
  host_increase_load(host);
  FO_ASSERT_EQUAL(host->running, 2);

  host_destroy(host);
}

void test_host_decrease_load()
{
  host_t* host = host_init("local", "localhost", "directory", 10);
  host->running = 2;

  FO_ASSERT_EQUAL(host->running, 2);
  host_decrease_load(host);
  FO_ASSERT_EQUAL(host->running, 1);
  host_decrease_load(host);
  FO_ASSERT_EQUAL(host->running, 0);

  host_destroy(host);
}

void test_get_host()
{
  host_t* host;
  scheduler_t* scheduler;
  uint32_t i;
  char* name = g_strdup(" _local");

  scheduler = scheduler_init(testdb, NULL);

  for(i = 0; i < 9; i++)
  {
    name[0] = (char)('1' + i);
    host_insert(host_init(name, "localhost", "directory", i + 1), scheduler);
  }

  for(i = 0; i < 9; i++)
  {
    host = get_host(&scheduler->host_queue, i + 1);
    name[0] = (char)('1' + i);

    FO_ASSERT_PTR_EQUAL(host, g_tree_lookup(scheduler->host_list, name));
    FO_ASSERT_EQUAL(host->max, i + 1);
  }

  host = get_host(&scheduler->host_queue, 3);
  FO_ASSERT_STRING_EQUAL(host->name, "3_local");
  FO_ASSERT_EQUAL(host->max, 3);
  host = get_host(&scheduler->host_queue, 1);
  FO_ASSERT_STRING_EQUAL(host->name, "1_local");
  FO_ASSERT_EQUAL(host->max, 1);
  host = get_host(&scheduler->host_queue, 9);
  FO_ASSERT_STRING_EQUAL(host->name, "9_local");
  FO_ASSERT_EQUAL(host->max, 9);
  host = get_host(&scheduler->host_queue, 3);
  FO_ASSERT_STRING_EQUAL(host->name, "4_local");
  FO_ASSERT_EQUAL(host->max, 4);

  scheduler_destroy(scheduler);
  g_free(name);
}

/* ************************************************************************** */
/* *** suite declaration **************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_host[] =
{
    {"Test host_init",          test_host_init          },
    {"Test host_insert",        test_host_insert        },
    {"Test host_increase_load", test_host_increase_load },
    {"Test host_decrease_load", test_host_decrease_load },
    {"Test host_get_host",      test_get_host           },
    CU_TEST_INFO_NULL
};

