/* **************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 ************************************************************** */

/* local includes */
#include <event.h>
#include <logging.h>
#include <scheduler.h>

/* std libaray includes */
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

/* ************************************************************************** */
/* **** Local(private) fields *********************************************** */
/* ************************************************************************** */

/* the event loop is a singleton, this is the only actual event loop */
event_loop_t vl_singleton;

/* flag used to check if the event loop has been created */
int el_created = 0;

/**
 * There is only one instance of an event loop in any program. This function
 * will control access to that event loop. If the event loop hasn't been created
 * yet, it will create and return the event loop. If it has been created, this
 * function will simple return it to the caller. This first call to this
 * function should be made from the thread that will be running the event loop.
 * This is to prevent confusion on if the event loop has been created.
 *
 * @return
 */
event_loop_t* event_loop_get()
{

  /* if the event loop has already been created, return it */
  if(el_created)
  {
    return &vl_singleton;
  }

  vl_singleton.queue = g_async_queue_new_full((GDestroyNotify)event_destroy);
  vl_singleton.occupied   = 0;
  vl_singleton.terminated = 0;
  el_created = 1;

  return &vl_singleton;
}

/**
 * puts a new item into the event queue. The event queue acts as a circular,
 * concurrent queue, and as a result this function must correct synchronize on
 * the queue to prevent race conditions. This will lock the queue, and wait if
 * the queue is full.
 *
 * @param vl the event loop to add the event to
 * @param e the event to put into the event loop
 * @return true if the item was succesfully added, false otherwise
 */
int event_loop_put(event_loop_t* event_loop, event_t* e)
{
  g_async_queue_push(event_loop->queue, e);
  return 1;
}

/**
 * Takes the next item out of the queue. The event queue acts as a circular,
 * concurrent queue, and as a result this function must correctly synchronize on
 * the queue to prevent race conditions. This will lock the queue, and wait if
 * the queue is full.
 *
 * @param vl the event loop to get the event out of
 * @return the next event in the event loop, NULL if the event loop has ended
 */
event_t* event_loop_take(event_loop_t* event_loop)
{
  GTimeVal timeout;
  event_t* ret;

  if(event_loop->terminated)
  {
    return NULL;
  }

  /* wait for 1 second */
  timeout.tv_sec  = 1;
  timeout.tv_usec = 0;

#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
  if((ret = g_async_queue_timeout_pop(event_loop->queue,
      timeout.tv_sec * 1000000 + timeout.tv_usec)) == NULL)
    return ret;
#else
  if((ret = g_async_queue_timed_pop(event_loop->queue, &timeout)) == NULL)
    return ret;
#endif

  if(ret->func == NULL)
  {
    event_destroy(ret);
    ret = NULL;
  }

  return ret;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * Allocates and initializes a new event. An event consists of a function and
 * the arguments for that function. The arguments for the function should be
 * taken in by the function as a sinlge void* and then parsed inside the
 * function. This interface provides a simple and generic interface for getting
 * a function to be called within the main thread.
 *
 * @param func the function to call when the event is executed
 * @param arg the arguements for the function.
 * @return the new event wrapper for the function and arguments
 */
event_t* event_init(void(*func)(scheduler_t*, void*), void* arg, char* name, char* source_name, uint16_t source_line)
{
  event_t* e = g_new(event_t, 1);

  e->func = func;
  e->argument = arg;
  e->name = name;
  e->source_name = source_name;
  e->source_line = source_line;

  return e;
}

/**
 * free any memory associated with an event
 *
 * @param e the event to destroy
 */
void event_destroy(event_t* e)
{
  e->func     = NULL;
  e->argument = NULL;
  e->name     = NULL;

  g_free(e);
}

/**
 * frees any memeory associated with the event queue. Any events that are in the
 * queue when this gets called will be freed as well.
 */
void event_loop_destroy()
{
  g_async_queue_unref(event_loop_get()->queue);
  el_created = 0;
}

/* ************************************************************************** */
/* **** EventLoop Functions ************************************************* */
/* ************************************************************************** */

/**
 * public interface for creating new events. Simple call this function, with the
 * first argument being a function pointer ( void(*)(void*) ) and the second
 * being the arguments for the function.
 *
 * @param func
 * @param args
 */
void event_signal_ext(void* func, void* args, char* name, char* s_name, uint16_t s_line)
{
  V_EVENT("EVENT: creating event: [%p, %p, %s, %s, %d]", func, args, name, s_name, s_line);
  event_loop_put(event_loop_get(), event_init((event_function)func, args, name, s_name, s_line));
}

/**
 * Enters the event loop. This function will not return until another threads
 * chooses to terminate the event loop. Essentially this function should not
 * return until the program is ready to exit. There should also only be one
 * thread working on this part of the event loop.
 *
 * @param update_call a function that is called every time an event is processed
 * @param signal_call a function that is called once a second
 * @return this function will return an error code:
 *          0x0:   successful execution
 *          0x1:   attempt to enter a loop that is occupied
 */
int event_loop_enter(scheduler_t* scheduler,
    void(*update_call)(scheduler_t*),
    void(*signal_call)(scheduler_t*))
{
  event_t* e;
  event_loop_t* event_loop = event_loop_get();

  /* start by checking to make sure this is the only thread in this loop */
  g_async_queue_lock(event_loop->queue);
  if(event_loop->occupied)
  {
    g_async_queue_unlock(event_loop->queue);
    return 0x1;
  }
  event_loop->occupied = 1;
  event_loop->terminated = 0;
  g_async_queue_unlock(event_loop->queue);

  main_thread = g_thread_self();

  /* from here on out, this is the only thread in this event loop     */
  /* the loop to execute events is very simple, grab event, run event */
  while(!event_loop->terminated)
  {
    e = event_loop_take(event_loop);

    if(signal_call)
      signal_call(scheduler);
    if(e == NULL)
      continue;

    if(TVERB_EVENT && strcmp(e->name, "log_event") != 0)
      log_printf("EVENT: calling %s, source[%s.%d] \n", e->name, e->source_name, e->source_line);
    e->func(scheduler, e->argument);

    if(TVERB_EVENT && strcmp(e->name, "log_event") != 0)
      log_printf("EVENT: finished %s, source[%s.%d] \n", e->name, e->source_name, e->source_line);

    event_destroy(e);

    if(update_call)
      update_call(scheduler);
  }

  return 0x0;
}

/**
 * stops the event loop from executing. This will wake up any threads that are
 * waiting on either a push into the event loop, or are trying to take from
 * the event loop and the put() and take() functions will return errors.
 *
 * @param vl the event loop to terminate
 */
void event_loop_terminate()
{
  event_loop_t* event_loop = event_loop_get();

  event_loop->terminated = 1;
  event_loop->occupied = 0;
  event_signal(NULL, NULL);
}


