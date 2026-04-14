/*
 SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test for host operations
 */

/* include functions to test */
#include <testRun.h>
#include <host.h>

/* ************************************************************************** */
/* **** host function tests ************************************************* */
/* ************************************************************************** */

/**
 * \brief Test for host_init()
 * \test
 * -# Create a host using host_init()
 * -# Check if the host returned is not NULL
 * -# Check if the host gets name, address, agent_dir and max properly
 * -# Check if the host has no running agents
 * -# Destroy the host
 */
void test_host_init()
{
  host_t* host;

  host = host_init("local", "localhost", "directory", 10, NULL, 0);
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "local");
  FO_ASSERT_STRING_EQUAL(host->address, "localhost");
  FO_ASSERT_STRING_EQUAL(host->agent_dir, "directory");
  FO_ASSERT_EQUAL(host->max, 10);
  FO_ASSERT_EQUAL(host->running, 0);
  FO_ASSERT_EQUAL(host->n_tags, 0);
  FO_ASSERT_PTR_NULL(host->tags);

  host_destroy(host);
}

/**
 * \brief Test for host_insert()
 * \test
 * -# Initialize scheduler using scheduler_init()
 * -# Create a host using host_init()
 * -# Insert the host to the scheduler using host_insert()
 * -# Check if scheduler's host list and queue size increases
 * -# Verify if the hosts are added in the given order to the scheduler's host
 *    queue
 */
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
    host_insert(host_init(name, "localhost", "directory", i, NULL, 0), scheduler);

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

/**
 * \brief Test for host_increase_load()
 * \test
 * -# Initialize host using host_init()
 * -# Check the running agents on host are 0
 * -# Call host_increase_load() on the host
 * -# Check if the running agents on host are increasing
 */
void test_host_increase_load()
{
  host_t* host = host_init("local", "localhost", "directory", 10, NULL, 0);

  FO_ASSERT_EQUAL(host->running, 0);
  host_increase_load(host);
  FO_ASSERT_EQUAL(host->running, 1);
  host_increase_load(host);
  FO_ASSERT_EQUAL(host->running, 2);

  host_destroy(host);
}

/**
 * \brief Test for host_decrease_load()
 * \test
 * -# Initialize host using host_init()
 * -# Set the host load to 2
 * -# Call host_decrease_load() on the host
 * -# Check if the running agents on host are decreasing
 */
void test_host_decrease_load()
{
  host_t* host = host_init("local", "localhost", "directory", 10, NULL, 0);
  host->running = 2;

  FO_ASSERT_EQUAL(host->running, 2);
  host_decrease_load(host);
  FO_ASSERT_EQUAL(host->running, 1);
  host_decrease_load(host);
  FO_ASSERT_EQUAL(host->running, 0);

  host_destroy(host);
}

/**
 * \brief Test for get_host()
 * \test
 * -# Initialize the scheduler using scheduler_init()
 * -# Add hosts to the scheduler with different capacity using host_insert()
 * -# Get the hosts from scheduler using get_host.
 * -# Check the name of the host for a given capacity
 */
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
    host_insert(host_init(name, "localhost", "directory", i + 1, NULL, 0), scheduler);
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

/**
 * \brief Test host_init() stores and NUL-terminates a tag list
 * \test
 * -# Create a host with two tags and verify n_tags and tag strings
 * -# Create a host with no tags and verify n_tags==0 and tags==NULL
 */
void test_host_init_with_tags()
{
  host_t* host;
  const char* tag_arr[] = {"nomos", "monk"};

  host = host_init("worker-heavy", "heavy.local", "/usr/lib/fossology", 8,
                   (char**)tag_arr, 2);
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_EQUAL(host->n_tags, 2);
  FO_ASSERT_PTR_NOT_NULL(host->tags);
  FO_ASSERT_STRING_EQUAL(host->tags[0], "nomos");
  FO_ASSERT_STRING_EQUAL(host->tags[1], "monk");
  FO_ASSERT_PTR_NULL(host->tags[2]);   /* NULL sentinel */
  host_destroy(host);

  host = host_init("worker-default", "default.local", "/usr/lib/fossology", 4,
                   NULL, 0);
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_EQUAL(host->n_tags, 0);
  FO_ASSERT_PTR_NULL(host->tags);
  host_destroy(host);
}

/**
 * \brief Test get_host_for() routes by agent type with untagged fallback
 * \test
 * -# Initialize scheduler, add one tagged host ("nomos") and one untagged host
 * -# get_host_for("nomos") returns the tagged host
 * -# get_host_for("copyright") skips the tagged host and returns the untagged host
 */
void test_get_host_for_affinity()
{
  scheduler_t* scheduler;
  host_t* result;
  const char* heavy_tags[] = {"nomos"};

  scheduler = scheduler_init(testdb, NULL);

  host_insert(host_init("heavy", "heavy.local", "/usr/lib/fossology", 4,
                        (char**)heavy_tags, 1), scheduler);
  host_insert(host_init("light", "light.local", "/usr/lib/fossology", 4,
                        NULL, 0), scheduler);

  /* "nomos" → tagged host "heavy" is first eligible */
  result = get_host_for(scheduler, "nomos", 1);
  FO_ASSERT_PTR_NOT_NULL(result);
  FO_ASSERT_STRING_EQUAL(result->name, "heavy");

  /* "copyright" → "heavy" rejects it (tagged nomos only), "light" accepts */
  result = get_host_for(scheduler, "copyright", 1);
  FO_ASSERT_PTR_NOT_NULL(result);
  FO_ASSERT_STRING_EQUAL(result->name, "light");

  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* *** suite declaration **************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_host[] =
{
    {"Test host_init",                 test_host_init                },
    {"Test host_insert",               test_host_insert              },
    {"Test host_increase_load",        test_host_increase_load       },
    {"Test host_decrease_load",        test_host_decrease_load       },
    {"Test host_get_host",             test_get_host                 },
    {"Test host_init_with_tags",       test_host_init_with_tags      },
    {"Test get_host_for_affinity",     test_get_host_for_affinity    },
    CU_TEST_INFO_NULL
};

