/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit tests for agent operations
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <agent.h>
#include <job.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */
/*
int agent_init_suite(void)
{
  meta_agents = g_tree_new_full(string_compare, NULL, NULL, (GDestroyNotify)meta_agent_destroy);
  job_list    = g_tree_new((GCompareFunc)int_compare);
  agents      = g_tree_new((GCompareFunc)int_compare);

  return init_suite();
}

int agent_clean_suite(void)
{
  g_tree_destroy(job_list);
  g_tree_destroy(agents);

  return clean_suite();
}
*/

/**
 * \brief Creates 2 pipes and initialize parameters
 * \param[out] int_dst  Initial destination pipe
 * \param[out] int_src  Initial source pipe
 * \param[out] file_dst File descriptor for int_dst with read only
 * \param[out] file_src File descriptor for int_src with write
 */
void create_pipe(int* int_dst, int* int_src, FILE** file_dst, FILE** file_src)
{
  int a_to_b[2];

  if(pipe(a_to_b) != 0)
    return;

  if(int_dst) *int_dst = a_to_b[0];
  if(int_src) *int_src = a_to_b[1];

  if(file_dst) *file_dst = fdopen(a_to_b[0], "r");
  if(file_src) *file_src = fdopen(a_to_b[1], "w");
}

/* ************************************************************************** */
/* **** meta agent function tests ******************************************* */
/* ************************************************************************** */

/**
 * \brief Test for meta_agent_init()
 * \test
 * -# Call meta_agent_init() with appropriate parameters
 * -# Check if the meta_agent_t returned is not null
 * -# Check if agent have appropriate name, max_run, special values, version
 *    raw_cmd and is assigned valid
 * -# Call meta_agent_init() with NULL name, should return null
 * -# Call meta_agent_init() with NULL command, should return null
 */
void test_meta_agent_init()
{
  char* name = "copyright";
  char* cmmd  = name;
  int   max  = 11;
  int   spc  = 0;

  meta_agent_t* ma = meta_agent_init(name, cmmd, max, spc);

  FO_ASSERT_PTR_NOT_NULL_FATAL(ma);
  FO_ASSERT_STRING_EQUAL(ma->name,    "copyright");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "copyright --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, max);
  FO_ASSERT_EQUAL(ma->special, spc);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);

  FO_ASSERT_PTR_NULL(meta_agent_init(NULL, cmmd, max, spc));
  FO_ASSERT_PTR_NULL(meta_agent_init(name, NULL, max, spc));
}

/**
 * \brief Test for add_meta_agent()
 * \test
 * -# Call add_meta_agent() with appropriate parameters, should return true
 * -# Call add_meta_agent() with false parameters, should return false
 * -# check if the meta agent is added to the list and contains proper values
 */
void test_add_meta_agent()
{
  scheduler_t* scheduler;
  meta_agent_t* ma;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);

  FO_ASSERT_TRUE(add_meta_agent(scheduler->meta_agents, "name", "cmd", 11, 1));
  FO_ASSERT_FALSE(add_meta_agent(scheduler->meta_agents, NULL, "cmd", 11, 1));

  ma = g_tree_lookup(scheduler->meta_agents, "name");
  FO_ASSERT_EQUAL(g_tree_nnodes(scheduler->meta_agents), 1);
  FO_ASSERT_PTR_NOT_NULL(ma);
  FO_ASSERT_STRING_EQUAL(ma->name, "name");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "cmd --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, 11);
  FO_ASSERT_EQUAL(ma->special, 1);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);

  g_tree_remove(scheduler->meta_agents, "name");
  scheduler_destroy(scheduler);
}

/*
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
*/

/* ************************************************************************** */
/* **** agent function tests ************************************************ */
/* ************************************************************************** */

/**
 * \brief Test for agent_death_event()
 * \test
 * -# Create an agent
 * -# Add to the scheduler
 * -# Call agent_death_event()
 * -# Check if the agent is removed from the scheduler
 */
void test_agent_death_event()
{
  scheduler_t* scheduler;
  agent_t fagent;
  job_t   fjob;
  int* pid_set = NULL;
  agent_t* a1;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  //meta_agent_t* ma = meta_agent_init("sample", "test_binary", 0, 0);

  fagent.pid    = 10;
  fagent.owner  = &fjob;
  fagent.status = AG_CREATED;
  //fagent.thread = g_thread_create(fake_thread, NULL, TRUE, NULL);

  fjob.id            = 1;
  fjob.status        = JB_STARTED;
  fjob.failed_agents = NULL;

  create_pipe(&fagent.from_child, &fagent.to_parent, NULL, &fagent.write);

  pid_set = g_new0(int, 2);
  pid_set[0] = fagent.pid;
  pid_set[1] = 0;
  fagent.return_code = 0;

  agent_death_event(scheduler, pid_set);
  a1 = g_tree_lookup(scheduler->agents, &fagent.pid);

  FO_ASSERT_EQUAL(fagent.status, AG_CREATED);
  FO_ASSERT_PTR_NULL(a1);

  close(fagent.from_child);
  close(fagent.to_parent);
  fclose(fagent.write);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for agent_create_event()
 * \test
 * -# Create a meta agent and add to the scheduler calling agent_create_event()
 * -# Check if the agent for added to the scheduler and is running
 * -# Call agent_pause() and agent_unpause() and check if the agent status changes
 * -# Call agent_ready_event() and check if the agent status updated
 * -# Call agent_fail_event() and check if the agent status updated
 * -# Call agent_update_event() and check if the agent status is not updated
 * -# Call agent_death_event() and check if the agent status is failed and agent
 *    is removed from scheduler
 */
void test_agent_create_event()
{
  scheduler_t* scheduler;
  agent_t* fagent = NULL;
  job_t*  fjob = NULL;
  agent_t* ag = NULL;
  GList* gl = NULL;
  int* pid_set = NULL;

  static int32_t id_gen = -1;
  GList*  iter;
  host_t* host;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_config_event(scheduler, NULL);

  meta_agent_t* ma = g_tree_lookup(scheduler->meta_agents, "copyright");
  for(iter = scheduler->host_queue; iter != NULL; iter = iter->next)
  {
    host = (host_t*)iter->data;
    fjob = job_init(scheduler->job_list, scheduler->job_queue, ma->name,
        host->name, id_gen--, 0, 0, 0, 0, NULL);
    fagent = agent_init(scheduler, host, fjob);
  }
  fagent->pid    = 10;
  fagent->owner  = fjob;
  fagent->status = AG_CREATED;

  /* test agent_create_event */
  agent_create_event(scheduler, fagent);

  ag = g_tree_lookup(scheduler->agents, &fagent->pid);
  gl = g_list_find(fjob->running_agents, fagent);

  FO_ASSERT_PTR_NOT_NULL(ag);
  FO_ASSERT_PTR_NOT_NULL(gl);
  FO_ASSERT_EQUAL(fagent->status, AG_SPAWNED);
  FO_ASSERT_PTR_EQUAL(ag, gl->data);

  agent_pause(fagent);
  FO_ASSERT_EQUAL(fagent->status, AG_PAUSED);
  agent_unpause(fagent);
  FO_ASSERT_EQUAL(fagent->status, AG_RUNNING);

  //agent_print_status(fagent, stdout);

  /* test agent_ready_event */
  agent_ready_event(scheduler, fagent);
  ag = g_tree_lookup(scheduler->agents, &fagent->pid);

  FO_ASSERT_PTR_NOT_NULL(ag);
  FO_ASSERT_EQUAL(fagent->status, AG_PAUSED);

  /* test agent fail event */
  agent_fail_event(scheduler, fagent);
  ag = g_tree_lookup(scheduler->agents, &fagent->pid);

  FO_ASSERT_PTR_NOT_NULL(ag);
  FO_ASSERT_EQUAL(fagent->status, AG_FAILED);

  /* test agent update event */
  agent_update_event(scheduler, NULL);
  ag = g_tree_lookup(scheduler->agents, &fagent->pid);
  FO_ASSERT_PTR_NOT_NULL(ag);
  FO_ASSERT_EQUAL(fagent->status, AG_FAILED);

  pid_set = g_new0(int, 2);
  pid_set[0] = fagent->pid;
  pid_set[1] = 0;
  fagent->return_code = 0;

  /* test agent death event */
  agent_death_event(scheduler, pid_set);
  ag = g_tree_lookup(scheduler->agents, &fagent->pid);

  FO_ASSERT_EQUAL(fagent->status, AG_FAILED);
  FO_ASSERT_PTR_NULL(ag);

  scheduler_close_event(scheduler, (void*)1);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for agent_init()
 * \todo finish
 */
void test_agent_init()
{
  scheduler_t* scheduler;
  agent_t* fagent;
  job_t* fjob;

  static int32_t id_gen = -1;
  GList*  iter;
  host_t* host;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_agent_config(scheduler);

  meta_agent_t* ma = g_tree_lookup(scheduler->meta_agents, "copyright");
  for(iter = scheduler->host_queue; iter != NULL; iter = iter->next)
  {
    host = (host_t*)iter->data;
    fjob = job_init(scheduler->job_list, scheduler->job_queue, ma->name,
        host->name, id_gen--, 0, 0, 0, 0, NULL);
    fagent = agent_init(scheduler, host, fjob);
    FO_ASSERT_PTR_NOT_NULL(fagent);
  }
  /*
  FO_ASSERT_EQUAL(g_tree_nnodes(scheduler->meta_agents), 9);
  FO_ASSERT_PTR_NOT_NULL(ma);
  FO_ASSERT_STRING_EQUAL(ma->name, "copyright");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "copyright --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, 255);
  FO_ASSERT_EQUAL(ma->special, 0);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);
  */

  scheduler_destroy(scheduler);
  // TODO finish
}
/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_meta_agent[] =
{

    {"Test meta_agent_init",  test_meta_agent_init  },
    {"Test add_meta_agent",   test_add_meta_agent   },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_agent[] =
{
    {"Test agent_init",  test_agent_init  },
    //{"Test agent_death_event", test_agent_death_event },
    {"Test agent_create_event", test_agent_create_event },
    CU_TEST_INFO_NULL
};

