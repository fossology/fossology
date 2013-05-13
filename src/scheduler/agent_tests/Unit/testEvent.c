/*********************************************************************
Copyright (C) 2011~2013 Hewlett-Packard Development Company, L.P.

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
#include <event.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

extern struct event_loop_internal vl_singleton;
extern int el_created;

extern event_loop_t* event_loop_get();

void* sample_args;
int   call_num;
int   samp_num;
char* s_name = NULL;
uint16_t s_line = 0;


void sample_event(scheduler_t* scheduler, void* args)
{
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  samp_num++;
  scheduler_destroy(scheduler);
}

void other_event(void* args)
{
  samp_num--;
}

void sample_callback(scheduler_t* scheduler)
{
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  call_num++;
  scheduler_destroy(scheduler);
}

void terminate_event(void* args)
{
  event_loop_terminate();
}

/* ************************************************************************** */
/* **** meta agent function tests ******************************************* */
/* ************************************************************************** */

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

void test_event_init()
{
  event_t* e;

  sample_args = &call_num;
  e = event_init(sample_event, sample_args, "sample", s_name, s_line);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample");

  g_free(e);
}

void test_event_signal_ext()
{
  event_t* e;

  sample_args = &call_num;
  event_signal_ext(sample_event, sample_args, "sample", s_name, s_line);

  e = g_async_queue_pop(event_loop_get()->queue);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample");

  g_free(e);
}

void test_event_signal()
{
  event_t* e;

  sample_args = &call_num;
  event_signal(sample_event, sample_args);

  e = g_async_queue_pop(event_loop_get()->queue);

  FO_ASSERT_PTR_EQUAL(   e->func,     sample_event);
  FO_ASSERT_PTR_EQUAL(   e->argument, sample_args);
  FO_ASSERT_STRING_EQUAL(e->name,     "sample_event");

  g_free(e);
}

void test_event_loop_enter()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_loop_t* vl = event_loop_get();
  int retval = 0;

  call_num = 0;
  samp_num = 0;
  event_signal(NULL, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(call_num, 0);
  FO_ASSERT_FALSE(vl->terminated);
  FO_ASSERT_TRUE(vl->occupied);

  event_signal(sample_event, NULL);
  event_signal(NULL, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x1);
  FO_ASSERT_EQUAL(samp_num, 0);
  FO_ASSERT_EQUAL(call_num, 0);

  vl->occupied = 0;
  vl->terminated = 0;

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 1);
  FO_ASSERT_EQUAL(call_num, 1);

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
  event_signal(NULL, NULL);

  retval = event_loop_enter(scheduler, sample_callback, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 2);
  FO_ASSERT_EQUAL(call_num, 6);

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
  event_signal(NULL, NULL);

  retval = event_loop_enter(scheduler, NULL, NULL);
  FO_ASSERT_EQUAL(retval, 0x0);
  FO_ASSERT_EQUAL(samp_num, 2);
  FO_ASSERT_EQUAL(call_num, 0);

  scheduler_destroy(scheduler);
}

void test_event_loop_terminate()
{
  scheduler_t* scheduler;
  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  event_loop_t* vl = event_loop_get();

  event_signal(terminate_event, NULL);

  vl->occupied = 0;
  vl->terminated = 1;

  FO_ASSERT_EQUAL(event_loop_enter(scheduler, NULL, NULL), 0x0);
  FO_ASSERT_FALSE(vl->occupied);
  FO_ASSERT_TRUE(vl->terminated);
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
    CU_TEST_INFO_NULL
};
