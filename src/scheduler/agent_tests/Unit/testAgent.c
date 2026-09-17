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

/* library includes */
#include <string.h>

/* shell_parse() is an agent.c-internal helper (not declared in agent.h) that
 * agent_spawn() uses to build an agent's exec() argv. It is left with
 * external linkage specifically so this test can call it directly; see the
 * comment above its definition in agent.c. */
void shell_parse(char* confdir, int user_id, int group_id, char* input,
    char* jq_cmd_args, int jobId, int* argc, char*** argv);

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

  pid_set = g_new0(int, 3);  /* [0]=pid [1]=status [2]=retry */
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
  for(iter = scheduler->host_queue; ma && iter != NULL; iter = iter->next)
  {
    host = (host_t*)iter->data;
    fjob = job_init(scheduler->job_list, scheduler->job_queue, ma->name,
        host->name, id_gen--, 0, 0, 0, 0, NULL);
    fagent = agent_init(scheduler, host, fjob);
  }
  if(!fagent)
  {
    scheduler_close_event(scheduler, (void*)1);
    scheduler_destroy(scheduler);
    return;
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

  pid_set = g_new0(int, 3);  /* [0]=pid [1]=status [2]=retry */
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

/**
 * \brief Test for shell_parse()
 *
 * Regression test for issue #3817. agent_spawn() used to build this argv
 * array by calling shell_parse() *after* fork(), inside the forked child.
 * That is unsafe: shell_parse() allocates through GLib, and allocating in
 * the child of a fork() from a multi-threaded process can deadlock forever
 * if some other thread held a GLib/libc allocator lock at the instant of
 * the fork. The fix moves this call to before the fork, in the parent,
 * which relies on every entry shell_parse() puts into argv being a real
 * heap allocation the caller can free with g_strfreev() once the fork is
 * done. Before this fix, the trailing "--scheduler_start" entry was a bare
 * string literal, so g_strfreev() would have crashed trying to free it.
 * This test locks down both the parsed content and that safety property.
 *
 * \test
 * -# Call shell_parse() with a simple command line and extra jq_cmd_args
 * -# Check every parsed token, including the flags shell_parse() appends
 *    itself, shows up in the result
 * -# Check every entry up to argc is non-NULL and that freeing the whole
 *    array with g_strfreev() does not crash
 */
void test_shell_parse()
{
  char confdir[] = "/etc/fossology";
  char input[] = "nomos -S ";
  char jq_cmd_args[] = "extra1 extra2";
  int argc = 0;
  char** argv = NULL;
  int i;
  gboolean found_nomos = FALSE;
  gboolean found_dash_s = FALSE;
  gboolean found_extra1 = FALSE;
  gboolean found_extra2 = FALSE;
  gboolean found_job_id = FALSE;
  gboolean found_config = FALSE;
  gboolean found_user_id = FALSE;
  gboolean found_group_id = FALSE;
  gboolean found_scheduler_start = FALSE;

  shell_parse(confdir, 42, 7, input, jq_cmd_args, 99, &argc, &argv);

  FO_ASSERT_PTR_NOT_NULL(argv);
  FO_ASSERT_TRUE(argc > 0);

  for (i = 0; i < argc; i++)
  {
    FO_ASSERT_PTR_NOT_NULL(argv[i]);
    if (strcmp(argv[i], "nomos") == 0)
      found_nomos = TRUE;
    else if (strcmp(argv[i], "-S") == 0)
      found_dash_s = TRUE;
    else if (strcmp(argv[i], "extra1") == 0)
      found_extra1 = TRUE;
    else if (strcmp(argv[i], "extra2") == 0)
      found_extra2 = TRUE;
    else if (strncmp(argv[i], "--jobId=", 8) == 0)
      found_job_id = TRUE;
    else if (strncmp(argv[i], "--config=", 9) == 0)
      found_config = TRUE;
    else if (strncmp(argv[i], "--userID=", 9) == 0)
      found_user_id = TRUE;
    else if (strncmp(argv[i], "--groupID=", 10) == 0)
      found_group_id = TRUE;
    else if (strcmp(argv[i], "--scheduler_start") == 0)
      found_scheduler_start = TRUE;
  }

  FO_ASSERT_TRUE(found_nomos);
  FO_ASSERT_TRUE(found_dash_s);
  FO_ASSERT_TRUE(found_extra1);
  FO_ASSERT_TRUE(found_extra2);
  FO_ASSERT_TRUE(found_job_id);
  FO_ASSERT_TRUE(found_config);
  FO_ASSERT_TRUE(found_user_id);
  FO_ASSERT_TRUE(found_group_id);
  FO_ASSERT_TRUE(found_scheduler_start);

  /* The actual regression check: every entry up to argc must be a genuine
   * heap allocation, not a string literal. agent_spawn() now builds this
   * argv before fork() and frees it in the parent with g_strfreev() once
   * the child has its own copy, so a stray literal here would crash. */
  g_strfreev(argv);
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
    {"Test shell_parse", test_shell_parse },
    CU_TEST_INFO_NULL
};

