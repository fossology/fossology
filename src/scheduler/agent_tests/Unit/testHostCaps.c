/*
 SPDX-FileCopyrightText: © 2024 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit tests for host agent capability extension (PR3)
 */

/* include functions to test */
#include <testRun.h>
#include <host.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** host_supports_agent tests ******************************************* */
/* ************************************************************************** */

/**
 * \brief host_supports_agent returns TRUE when agent_caps is NULL
 * \test
 * -# Create a host with no agent_caps (NULL = accept all)
 * -# Call host_supports_agent with any agent name
 * -# Verify it returns TRUE
 */
void test_host_caps_null_accepts_all()
{
  host_t* host = host_init("local", "localhost", "directory", 10);

  FO_ASSERT_TRUE(host_supports_agent(host, "nomos"));
  FO_ASSERT_TRUE(host_supports_agent(host, "copyright"));
  FO_ASSERT_TRUE(host_supports_agent(host, "ecc"));
  FO_ASSERT_PTR_NULL(host->agent_caps);

  host_destroy(host);
}

/**
 * \brief host_supports_agent returns TRUE when agent is in caps list
 * \test
 * -# Create a host with agent_caps = [nomos, ojo]
 * -# Verify host_supports_agent returns TRUE for nomos and ojo
 */
void test_host_caps_agent_in_list()
{
  GList* caps = NULL;
  caps = g_list_append(caps, g_strdup("nomos"));
  caps = g_list_append(caps, g_strdup("ojo"));

  host_t* host = host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps);

  FO_ASSERT_TRUE(host_supports_agent(host, "nomos"));
  FO_ASSERT_TRUE(host_supports_agent(host, "ojo"));

  host_destroy(host);
}

/**
 * \brief host_supports_agent returns FALSE when agent is NOT in caps list
 * \test
 * -# Create a host with agent_caps = [nomos, ojo]
 * -# Verify host_supports_agent returns FALSE for copyright and ecc
 */
void test_host_caps_agent_not_in_list()
{
  GList* caps = NULL;
  caps = g_list_append(caps, g_strdup("nomos"));
  caps = g_list_append(caps, g_strdup("ojo"));

  host_t* host = host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps);

  FO_ASSERT_FALSE(host_supports_agent(host, "copyright"));
  FO_ASSERT_FALSE(host_supports_agent(host, "ecc"));
  FO_ASSERT_FALSE(host_supports_agent(host, "keyword"));

  host_destroy(host);
}

/**
 * \brief host_supports_agent handles NULL arguments safely
 * \test
 * -# Verify host_supports_agent returns FALSE when host is NULL
 * -# Verify host_supports_agent returns FALSE when agent_name is NULL
 */
void test_host_caps_null_args()
{
  host_t* host = host_init("local", "localhost", "directory", 10);

  FO_ASSERT_FALSE(host_supports_agent(NULL, "nomos"));
  FO_ASSERT_FALSE(host_supports_agent(host, NULL));
  FO_ASSERT_FALSE(host_supports_agent(NULL, NULL));

  host_destroy(host);
}

/* ************************************************************************** */
/* **** get_host with caps tests ******************************************** */
/* ************************************************************************** */

/**
 * \brief get_host returns a capable host when one exists
 * \test
 * -# Create a scheduler with two hosts:
 *    - host A: caps = [nomos, ojo], max = 5
 *    - host B: caps = [copyright, ecc], max = 5
 * -# Call get_host with agent_name = "nomos"
 * -# Verify host A is returned
 */
void test_get_host_capable()
{
  scheduler_t* scheduler;
  host_t* host;
  GList* caps_a = NULL;
  GList* caps_b = NULL;

  scheduler = scheduler_init(testdb, NULL);

  caps_a = g_list_append(caps_a, g_strdup("nomos"));
  caps_a = g_list_append(caps_a, g_strdup("ojo"));
  host_insert(host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps_a), scheduler);

  caps_b = g_list_append(caps_b, g_strdup("copyright"));
  caps_b = g_list_append(caps_b, g_strdup("ecc"));
  host_insert(host_init_with_caps("worker-1", "10.0.0.2", "/usr/local", 5, caps_b), scheduler);

  host = get_host(&scheduler->host_queue, 1, "nomos");
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "worker-0");

  host = get_host(&scheduler->host_queue, 1, "copyright");
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "worker-1");

  scheduler_destroy(scheduler);
}

/**
 * \brief get_host skips incapable hosts and returns next capable one
 * \test
 * -# Create scheduler with host A (caps=[nomos]) and host B (caps=NULL, accept-all)
 * -# Call get_host with agent_name = "copyright"
 * -# Host A should be skipped, host B returned
 */
void test_get_host_skips_incapable()
{
  scheduler_t* scheduler;
  host_t* host;
  GList* caps_a = NULL;

  scheduler = scheduler_init(testdb, NULL);

  caps_a = g_list_append(caps_a, g_strdup("nomos"));
  host_insert(host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps_a), scheduler);
  host_insert(host_init("worker-1", "10.0.0.2", "/usr/local", 5), scheduler);

  host = get_host(&scheduler->host_queue, 1, "copyright");
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "worker-1");

  scheduler_destroy(scheduler);
}

/**
 * \brief get_host returns NULL when no host supports the requested agent
 * \test
 * -# Create scheduler with hosts only supporting [nomos, ojo]
 * -# Call get_host with agent_name = "copyright"
 * -# Verify NULL is returned
 */
void test_get_host_returns_null()
{
  scheduler_t* scheduler;
  host_t* host;
  GList* caps = NULL;

  scheduler = scheduler_init(testdb, NULL);

  caps = g_list_append(caps, g_strdup("nomos"));
  host_insert(host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps), scheduler);

  host = get_host(&scheduler->host_queue, 1, "copyright");
  FO_ASSERT_PTR_NULL(host);

  scheduler_destroy(scheduler);
}

/**
 * \brief get_host with NULL agent_name returns any host (backwards compat)
 * \test
 * -# Create scheduler with a host that has specific caps
 * -# Call get_host with agent_name = NULL
 * -# Verify a host is returned (NULL agent_name matches any host)
 */
void test_get_host_null_agent_any()
{
  scheduler_t* scheduler;
  host_t* host;
  GList* caps = NULL;

  scheduler = scheduler_init(testdb, NULL);

  caps = g_list_append(caps, g_strdup("nomos"));
  host_insert(host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps), scheduler);

  host = get_host(&scheduler->host_queue, 1, NULL);
  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_STRING_EQUAL(host->name, "worker-0");

  scheduler_destroy(scheduler);
}

/**
 * \brief host_init_with_caps with NULL caps behaves like host_init
 * \test
 * -# Create a host with host_init_with_caps and NULL caps
 * -# Verify agent_caps is NULL and host_supports_agent returns TRUE for any agent
 */
void test_host_init_with_null_caps()
{
  host_t* host = host_init_with_caps("local", "localhost", "/usr/local", 10, NULL);

  FO_ASSERT_PTR_NOT_NULL(host);
  FO_ASSERT_PTR_NULL(host->agent_caps);
  FO_ASSERT_TRUE(host_supports_agent(host, "nomos"));
  FO_ASSERT_TRUE(host_supports_agent(host, "anything"));

  host_destroy(host);
}

/**
 * \brief host_destroy frees agent_caps without double-free
 * \test
 * -# Create a host with caps, destroy it
 * -# Verify no crash (memory sanitizer will catch double-free)
 */
void test_host_destroy_frees_caps()
{
  GList* caps = NULL;
  caps = g_list_append(caps, g_strdup("nomos"));
  caps = g_list_append(caps, g_strdup("ojo"));
  caps = g_list_append(caps, g_strdup("copyright"));

  host_t* host = host_init_with_caps("worker-0", "10.0.0.1", "/usr/local", 5, caps);
  FO_ASSERT_PTR_NOT_NULL(host->agent_caps);
  FO_ASSERT_EQUAL(g_list_length(host->agent_caps), 3);

  /* host_destroy should free caps list without crashing */
  host_destroy(host);
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_host_caps[] =
{
    {"Test host_supports_agent NULL caps accepts all",  test_host_caps_null_accepts_all  },
    {"Test host_supports_agent agent in list",          test_host_caps_agent_in_list     },
    {"Test host_supports_agent agent not in list",      test_host_caps_agent_not_in_list },
    {"Test host_supports_agent NULL args",              test_host_caps_null_args         },
    {"Test get_host returns capable host",              test_get_host_capable            },
    {"Test get_host skips incapable hosts",             test_get_host_skips_incapable    },
    {"Test get_host returns NULL when none capable",    test_get_host_returns_null       },
    {"Test get_host NULL agent matches any",            test_get_host_null_agent_any     },
    {"Test host_init_with_caps NULL caps",              test_host_init_with_null_caps    },
    {"Test host_destroy frees caps safely",             test_host_destroy_frees_caps     },
    CU_TEST_INFO_NULL
};
