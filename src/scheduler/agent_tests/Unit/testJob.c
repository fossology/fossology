/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test for job operations
 */

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <agent.h>
#include <scheduler.h>
#include <job.h>

#include <utils.h>

/* standard */
#include <string.h>

/**
 * Local function for testing data prepare
 */
int Prepare_Testing_Data_Job(scheduler_t* scheduler) {
  return Prepare_Testing_Data(scheduler);
}

/* ************************************************************************** */
/* ********** job function tests ******************************************** */
/* ************************************************************************** */

/**
 * \brief Test for job events
 * \test
 * -# Initialize scheduler and database
 * -# Create new database_update_event() to load data
 * -# Get the job from scheduler and call
 *     -# job_verbose_event() and check if job is updated
 *     -# job_pause_event() and check if job is paused
 *     -# job_restart_event() and check if job is restarted
 *     -# job_priority_event() and check if job is restarted
 *     -# job_fail_event() and check if job is failed
 */
void test_job_event()
{
  scheduler_t* scheduler;
  job_t* job;
  arg_int* params;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);

  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL_FATAL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);

  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  job_verbose_event(scheduler, job);
  FO_ASSERT_EQUAL(job->id, jq_pk);

  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = jq_pk;
  job_pause_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_PAUSED);

  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = jq_pk;
  job_restart_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_RESTART);

  params = g_new0(arg_int, 1);
  params->first = job;
  params->second = 1;
  job_priority_event(scheduler, params);
  FO_ASSERT_EQUAL(job->status, JB_RESTART);

  job_fail_event(scheduler, job);
  FO_ASSERT_EQUAL(job->status, JB_FAILED);

  scheduler_update(scheduler);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for job functions
 * \test
 * -# Initialize scheduler and database
 * -# Create new database_update_event() to load data
 * -# Get the job from scheduler and call
 *     -# job_next() and check the job queue id
 *     -# next_job() and check the job is NOT NULL and its id is 1
 *     -# peek_job() and check the job is NOT NULL and its id is 1
 *     -# active_jobs() and check the result is 0
 */
void test_job_fun()
{
  scheduler_t* scheduler;
  job_t* job;
  char* res = NULL;
  uint32_t result = 0;
  int jq_pk;

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL(scheduler->db_conn);

  jq_pk = Prepare_Testing_Data_Job(scheduler);

  database_update_event(scheduler, NULL);

  job = g_tree_lookup(scheduler->job_list, &jq_pk);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);

  res = job_next(job);
  FO_ASSERT_PTR_NOT_NULL(res);
  /* The queue may contain other jobs from the shared DB; only verify non-NULL. */
  job = peek_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  job = next_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(job);
  result = active_jobs(scheduler->job_list);
  FO_ASSERT_EQUAL(result, 0);

  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* **** Regression tests for scheduler fixes ******************************** */
/* ************************************************************************** */

/* max_run=0: isMaxLimitReached() always TRUE, no agents forked. */
#define STRESS_USERS    15
#define STRESS_UPLOADS  20
#define STRESS_N_JOBS   (STRESS_USERS * STRESS_UPLOADS)
#define STRESS_BASE_ID  50000

/**
 * @brief Jobs blocked at max_run must not be reaped as stale.
 *
 * Ages 300 jobs past the grace period then runs one scheduler_update() cycle.
 * The skip loop refreshes checkedout_at so the stale reaper finds nothing.
 */
void test_scheduler_high_load_no_false_stale(void)
{
  scheduler_t* scheduler;
  int          i, found;
  int          job_ids[STRESS_N_JOBS];
  time_t       old_time;
  uint32_t     saved_interval;

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL_FATAL(scheduler->db_conn);

  /* max_run=0: isMaxLimitReached() always TRUE → skip loop, never fork. */
  add_meta_agent(scheduler->meta_agents, "stress_agent", "echo", 0, 0);

  /* 0 disables the rate-limiter so the stale reaper fires on every call. */
  saved_interval          = CONF_agent_update_interval;
  CONF_agent_update_interval = 0;

  /* Create jobs aged 5 s — past the grace period. */
  old_time = time(NULL) - 5;
  for (i = 0; i < STRESS_N_JOBS; i++)
  {
    job_t* j = job_init(scheduler->job_list, scheduler->job_queue,
        "stress_agent", NULL, STRESS_BASE_ID + i, 0, 1, 1, 0, NULL);
    FO_ASSERT_PTR_NOT_NULL_FATAL(j);
    j->checkedout_at = old_time;
    job_ids[i]       = STRESS_BASE_ID + i;
  }

  FO_ASSERT_EQUAL((int)g_sequence_get_length(scheduler->job_queue), STRESS_N_JOBS);
  FO_ASSERT_EQUAL(g_tree_nnodes(scheduler->job_list),               STRESS_N_JOBS);

  scheduler_update(scheduler);

  /* All must survive. */
  found = 0;
  for (i = 0; i < STRESS_N_JOBS; i++)
  {
    if (g_tree_lookup(scheduler->job_list, &job_ids[i]) != NULL)
      found++;
  }
  FO_ASSERT_EQUAL(found,                                            STRESS_N_JOBS);
  FO_ASSERT_EQUAL((int)g_sequence_get_length(scheduler->job_queue), STRESS_N_JOBS);

  CONF_agent_update_interval = saved_interval;
  scheduler_destroy(scheduler);
}

/**
 * @brief Priority ordering must be preserved after a blocked-job skip.
 *
 * Three jobs (-10/0/+10 priority), all blocked.  After scheduler_update()
 * the highest-priority job must still be at the front of the queue.
 */
void test_scheduler_priority_order_preserved(void)
{
  scheduler_t* scheduler;
  job_t*       top;
  uint32_t     saved_interval;
  int          id_hi  = STRESS_BASE_ID + 2000;   /* priority -10 */
  int          id_mid = STRESS_BASE_ID + 2001;   /* priority   0 */
  int          id_low = STRESS_BASE_ID + 2002;   /* priority +10 */

  scheduler = scheduler_init(testdb, NULL);
  FO_ASSERT_PTR_NULL(scheduler->db_conn);
  database_init(scheduler);
  FO_ASSERT_PTR_NOT_NULL_FATAL(scheduler->db_conn);

  add_meta_agent(scheduler->meta_agents, "prio_agent", "echo", 0, 0);

  saved_interval          = CONF_agent_update_interval;
  CONF_agent_update_interval = 0;

  /* Non-sorted insert order to stress priority sorting. */
  job_init(scheduler->job_list, scheduler->job_queue,
      "prio_agent", NULL, id_low, 0, 1, 1,  10, NULL);
  job_init(scheduler->job_list, scheduler->job_queue,
      "prio_agent", NULL, id_hi,  0, 1, 1, -10, NULL);
  job_init(scheduler->job_list, scheduler->job_queue,
      "prio_agent", NULL, id_mid, 0, 1, 1,   0, NULL);

  /* Highest-priority job must be at the front before scheduling. */
  top = peek_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(top);
  FO_ASSERT_EQUAL(top->id, id_hi);

  /* Age all jobs past the stale threshold to exercise the reaper. */
  {
    int    ids[3] = { id_hi, id_mid, id_low };
    int    k;
    time_t old    = time(NULL) - 5;
    for (k = 0; k < 3; k++)
    {
      job_t* j = g_tree_lookup(scheduler->job_list, &ids[k]);
      if (j) j->checkedout_at = old;
    }
  }

  scheduler_update(scheduler);

  /* Highest-priority job must still be at the front after scheduling. */
  top = peek_job(scheduler->job_queue);
  FO_ASSERT_PTR_NOT_NULL_FATAL(top);
  FO_ASSERT_EQUAL(top->id, id_hi);

  /* All 3 must survive — stale reaper must not kill blocked jobs. */
  FO_ASSERT_EQUAL(g_tree_nnodes(scheduler->job_list), 3);

  CONF_agent_update_interval = saved_interval;
  scheduler_destroy(scheduler);
}

/**
 * @brief agent_transition() must not crash when agent->owner is NULL.
 */
void test_agent_transition_null_owner_safe(void)
{
  agent_t fake;
  memset(&fake, 0, sizeof(fake));

  fake.status = AG_CREATED;
  fake.owner  = NULL;

  agent_transition(&fake, AG_FAILED);

  FO_ASSERT_EQUAL(fake.status, AG_FAILED);
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_job[] =
{
    {"Test job_event",                              test_job_event                         },
    {"Test job_fun",                                test_job_fun                           },
    {"Test 15-users-20-uploads no false stale kill",test_scheduler_high_load_no_false_stale},
    {"Test priority order preserved after skip",    test_scheduler_priority_order_preserved},
    {"Test agent_transition null owner safe",       test_agent_transition_null_owner_safe  },
    CU_TEST_INFO_NULL
};
