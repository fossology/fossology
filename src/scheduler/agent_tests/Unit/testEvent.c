/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test for event operations
 */

/* include functions to test */
#include <testRun.h>
#include <event.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

extern struct event_loop_internal vl_singleton;
extern int el_created;

extern event_loop_t* event_loop_get();

void* sample_args;    ///< Sample args to pass
int   call_num;       ///< Number of callback calls
int   samp_num;       ///< Number of sample event calls
char* s_name = NULL;  ///< Sample source file name
uint16_t s_line = 0;  ///< Sample source line number

/**
 * \brief Sample event
 *
 * Increase samp_num on every call
 */
void sample_event(scheduler_t* scheduler, void* args)
{
  samp_num++;
}

/**
 * \brief Sample event 2
 *
 * Decrease samp_num on every call
 */
void other_event(void* args)
{
  samp_num--;
}

/**
 * \brief Sample callback function
 *
 * Increases call_num on every call
 */
void sample_callback(scheduler_t* scheduler)
{
  call_num++;
}

/**
 * \brief Terminate event
 *
 * Calls event_loop_terminate()
 */
void terminate_event(void* args)
{
  event_loop_terminate();
}

/* ************************************************************************** */
/* **** meta agent function tests ******************************************* */
/* ************************************************************************** */
/**
 * \brief Test for event_loop_get()
 * \test
 * -# Check if no event loop is created
 * -# Get the vl_addr and call event_loop_get()
 * -# Check if vl_addr and event loop are equal
 * -# Check if event loop is created and is not occupied or terminated
 * -# Call event_loop_get() one more time
 * -# Check if value return matches previous values
 */
void test_event_loop_get()
{
  FO_ASSERT_FALSE(el_created);

  void* vl_addr = &vl_singleton;
  event_loop_t* vl = event_loop_get();

  FO_ASSERT_PTR_EQUAL(vl, vl_addr);
  FO_ASSERT_TRUE(el_created);
  FO_ASSERT_PTR_NOT_NULL(vl->queue);
  FO_ASSERT_FALSE(vl->occupied);
  FO_ASSERT_FALSE(vl->terminated);

  event_loop_t* ol = event_loop_get();

  FO_ASSERT_PTR_EQUAL(ol, vl_addr);
  FO_ASSERT_PTR_EQUAL(vl, ol);
}

/**
 * \brief Test for event_init()
 * \test
 * -# Call event_init() with appropriate values to create an event
 * -# Check if the event created have appropriate values
 */
void test_event_init()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);

  event_t* e;

  sample_args = &call_num;
  e = event_init(sample_event, sample_args, "sample", s_name, s_line);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample");

  g_free(e);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_signal_ext()
 * \test
 * -# Call event_signal_ext() with appropriate values to create an event
 * -# Check if the event created have appropriate values
 */
void test_event_signal_ext()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);

  event_t* e;

  sample_args = &call_num;
  event_signal_ext(sample_event, sample_args, "sample", s_name, s_line);

  e = g_async_queue_pop(event_loop_get()->queue);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample");

  g_free(e);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_signal()
 * \test
 * -# Use the macro event_signal() to create an event
 * -# Get the new event from event loop
 * -# Check if the event get the name `"sample_event"` and have proper function
 * and argument assigned
 */
void test_event_signal()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_t* e;

  sample_args = &call_num;
  event_signal(sample_event, sample_args);

  e = g_async_queue_pop(event_loop_get()->queue);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample_event");

  g_free(e);
  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_loop_enter()
 * \test
 * -# Generate several events using event_signal()
 * -# Get the return value from event_loop_enter() with sample_callback as argument
 * -# Check the return value and count of callback calls
 */
void test_event_loop_enter()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_agent_config(scheduler);

  event_loop_t* vl = event_loop_get();
  int retval = 0;

  call_num = 0;
  samp_num = 0;
  event_signal(NULL, NULL);
  event_signal(terminate_event, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(call_num,1);
  FO_ASSERT_TRUE(vl->terminated);
  FO_ASSERT_FALSE(vl->occupied);

  event_signal(sample_event, NULL);
  event_signal(terminate_event, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 1);
  FO_ASSERT_EQUAL(call_num, 3);

  vl->occupied = 0;
  vl->terminated = 0;
  event_signal(terminate_event, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 1);
  FO_ASSERT_EQUAL(call_num, 4);

  vl->occupied = 0;
  vl->terminated = 0;
  samp_num = 0;
  call_num = 0;

  event_signal(sample_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(other_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(other_event, NULL);
  event_signal(terminate_event, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 2);
  FO_ASSERT_EQUAL(call_num, 7);

  vl->occupied = 0;
  vl->terminated = 1;
  samp_num = 0;
  call_num = 0;

  event_signal(sample_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(other_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(sample_event, NULL);
  event_signal(other_event, NULL);
  event_signal(terminate_event, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 2);
  FO_ASSERT_EQUAL(call_num, 7);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_loop_enter() with terminate_event
 * \test
 * -# Call event_signal() with terminate_event() callback
 * -# Get the return value from event_loop_enter() with NULL as the parameters
 * -# Check the return value is `0x0`
 * -# Check if the occupied is false for event_loop
 * -# Check if terminated is true for event_loop
 */
void test_event_loop_terminate()
{
  int retval = 0;
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_loop_t* vl = event_loop_get();

  vl->occupied = 0;
  vl->terminated = 1;

  event_signal(terminate_event, NULL);
  retval = event_loop_enter(scheduler, NULL, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_FALSE(vl->occupied);
  FO_ASSERT_TRUE(vl->terminated);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_loop_take()
 * \test
 * -# Get an event_loop from event_loop_get()
 * -# Set the occupied and terminated values for the event_loop
 * -# Pass the event_loop to event_loop_take()
 * -# Check the return value is NULL
 */
void test_event_loop_take()
{
  event_t* retval;
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_loop_t* vl = event_loop_get();

  vl->occupied = 0;
  vl->terminated = 1;

  retval = event_loop_take(vl);
  FO_ASSERT_PTR_NULL(retval);
  FO_ASSERT_FALSE(vl->occupied);
  FO_ASSERT_TRUE(vl->terminated);

  scheduler_destroy(scheduler);
}

/**
 * \brief Test for event_loop_put()
 * \test
 * -# Pass sample_event and sample_args to event_signal()
 * -# Check if the event loop gets the values in the queue
 * -# Call event_loop_put()
 */
void test_event_loop_put()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_t* e;

  sample_args = &call_num;
  event_signal(sample_event, sample_args);

  e = g_async_queue_pop(event_loop_get()->queue);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample_event");

  event_loop_put(event_loop_get(),e);
  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* *** suite decl *********************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_event[] =
{
    {"Test event_loop_get",   test_event_loop_get   },
    {"Test event_init",       test_event_init       },
    {"Test event_signal_ext", test_event_signal_ext },
    {"Test event_signal",     test_event_signal     },
    //{"Test event_loop_enter", test_event_loop_enter },
    {"Test event_loop_terminate", test_event_loop_terminate },
    {"Test event_loop_take", test_event_loop_take },
    {"Test event_loop_put", test_event_loop_put },
    CU_TEST_INFO_NULL
};
