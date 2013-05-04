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

/* scheduler includes */
#include <agent.h>
#include <job.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

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

gpointer fake_thread(gpointer data) {
  /* no-op */
  return NULL;
}

/* ************************************************************************** */
/* **** meta agent function tests ******************************************* */
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

  ma = g_tree_lookup(meta_agents, "name");
  FO_ASSERT_EQUAL(g_tree_nnodes(meta_agents), 1);
  FO_ASSERT_PTR_NOT_NULL(ma);
  FO_ASSERT_STRING_EQUAL(ma->name, "name");
  FO_ASSERT_STRING_EQUAL(ma->raw_cmd, "cmd --scheduler_start");
  FO_ASSERT_EQUAL(ma->max_run, 11);
  FO_ASSERT_EQUAL(ma->special, 1);
  FO_ASSERT_PTR_NULL(ma->version);
  FO_ASSERT_TRUE(ma->valid);

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

/* ************************************************************************** */
/* **** agent function tests ************************************************ */
/* ************************************************************************** */

// TODO add to suite
void test_agent_death_event()
{
  struct agent_internal fagent;
  struct job_internal   fjob;
  int* pid_set = NULL;
  agent a1, a2;

  meta_agent_init("sample", "test_binary", 0, 0);

  fagent.pid    = 10;
  fagent.owner  = &fjob;
  fagent.status = AG_CREATED;
  fagent.thread = g_thread_create(fake_thread, NULL, TRUE, NULL);

  fjob.id            = -1;
  fjob.status        = JB_STARTED;
  fjob.failed_agents = NULL;

  create_pipe(&fagent.from_child, &fagent.to_parent, NULL, &fagent.write);

  /* correctly finished agent */
  pid_set = g_new0(int, 2);
  pid_set[0] = fagent.pid;
  pid_set[1] = 0;
  fagent.return_code = 0;

  agent_death_event(pid_set);
  a1 = g_tree_lookup(agents, &fagent.pid);

  FO_ASSERT_EQUAL(fagent.status, AG_PAUSED);
  FO_ASSERT_TRUE(fagent.meta_data->valid);
  FO_ASSERT_PTR_NULL(a1);

  close(fagent.from_child);
  close(fagent.to_parent);
  fclose(fagent.write);
}

// TODO add to suite
void test_agent_create_event()
{
  struct agent_internal fagent;
  struct job_internal   fjob;
  agent  ag = NULL;
  GList* gl = NULL;

  fagent.pid    = 10;
  fagent.owner  = &fjob;
  fagent.status = AG_CREATED;

  agent_create_event(&fagent);

  ag = g_tree_lookup(agents, &fagent.pid);
  gl = g_list_find(fjob.running_agents, &fagent);

  FO_ASSERT_PTR_NOT_NULL(ag);
  FO_ASSERT_PTR_NOT_NULL(gl);
  FO_ASSERT_EQUAL(fagent.status, AG_SPAWNED);
  FO_ASSERT_PTR_EQUAL(ag, gl->data);
}

// TODO add to suite
void test_agent_init()
{
  agent_list_clean();

  sysconfigdir = "../agents/";

  add_meta_agent("simple", "test_binary", 10, 0);

  struct host_internal fhost;
  struct job_internal  fjob;
  agent ag;

  fhost.address   = "localhost";
  fhost.agent_dir = "AGENT_DIR";
  fhost.running   = 0;
  fjob.agent_type = "simple";
  fjob.data       = "";
  fjob.db_result  = NULL;
  fjob.id         = 1;
  fjob.status     = JB_CHECKEDOUT;
  fjob.idx        = 0;

  g_tree_insert(job_list, &fjob.id, &fjob);

  ag = agent_init(&fhost, &fjob);

  printf("%d\n", ag->pid);

  // TODO finish
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_meta_agent[] =
{

    {"Test agent_list_init",  test_agent_list_init  },
    {"Test agent_list_clear", test_agent_list_clear },
    {"Test meta_agent_init",  test_meta_agent_init  },
    {"Test add_meta_agent",   test_add_meta_agent   },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_agent[] =
{
    {"Test agent_death_event", test_agent_death_event },
    CU_TEST_INFO_NULL
};

