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

/* std libaray includes */
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

/* unix library includes */
#include <pthread.h>

/* local includes */
#include <event.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define EVENT_LOOP_SIZE 1024

/** interanl structure for an event */
struct event_internal {
  void(*func)(void*);           ///< the function that will be executed for this event
  void* argument;               ///< the arguments for the function
};

/** internal structure for the event loop */
struct event_loop_internal {
  pthread_cond_t wait_t;        ///< the wait condition used for threads taking from the queue
  pthread_cond_t wait_p;        ///< the wait condition used for threads placing into the queue
  pthread_mutex_t lock;         ///< the mutex that will lockt he concurrent queue
  event queue[EVENT_LOOP_SIZE]; ///< the circular queue for the event loop
  int head, tail;               ///< the front and back of the queue
  int terminated;               ///< flag that signals the end of the event loop
  int occupied;                 ///< flag that determines if there is already a thread in this loop
};

/* ************************************************************************** */
/* **** Local(private) fields *********************************************** */
/* ************************************************************************** */

/* the event loop is a singleton, this is the only actual event loop */
struct event_loop_internal vl_singleton;
/* flag used to check if the event loop has been created */
int created = 0;

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
event_loop event_loop_get()
{

  /* if the event loop has already been created, return it */
  if(created) {
    return &vl_singleton;
  }

  pthread_cond_init( &vl_singleton.wait_t, NULL);
  pthread_cond_init( &vl_singleton.wait_p, NULL);
  pthread_mutex_init(&vl_singleton.lock,   NULL);

  memset(vl_singleton.queue, 0, sizeof(vl_singleton.queue));
  vl_singleton.head = vl_singleton.tail = 0;
  vl_singleton.terminated = 0;
  created = 1;

  return &vl_singleton;
}

/**
 * TODO
 *
 * @param vl
 */
void event_loop_destroy(event_loop vl)
{
  int i;

  for(i = 0; i < EVENT_LOOP_SIZE; i++) {
    if(vl->queue[i] != NULL) {
      event_destroy(vl->queue[i]);
    }
  }
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
int event_loop_put(event_loop vl, event e)
{
  if(vl->terminated)
  {
    return 0;
  }
  pthread_mutex_lock(&vl->lock);

  /* check to see if the queue is full */
  while((vl->tail + 1) % EVENT_LOOP_SIZE == vl->head)
  {
    pthread_cond_wait(&vl->wait_p, &vl->lock);
    if(vl->terminated)
    {
      pthread_mutex_unlock(&vl->lock);
      return 0;
    }
  }

  /* queue has space, add the event */
  vl->queue[vl->tail] = e;
  vl->tail = (vl->tail + 1) % EVENT_LOOP_SIZE;

  /* clean up for the end of the function */
  pthread_cond_signal(&vl->wait_t);
  pthread_mutex_unlock(&vl->lock);
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
event event_loop_take(event_loop vl)
{
  /* locals */
  event ret;
  if(vl->terminated)
  {
    return NULL;
  }
  pthread_mutex_lock(&vl->lock);

  /* check if the queue is empty, if so wait for something */
  while(vl->head == vl->tail)
  {
    pthread_cond_wait(&vl->wait_t, &vl->lock);
    if(vl->terminated)
    {
      pthread_mutex_unlock(&vl->lock);
      return NULL;
    }
  }

  /* there is an item in the queue, take it */
  ret = vl->queue[vl->head];
  vl->queue[vl->head] = NULL;
  vl->head = (vl->head + 1) % EVENT_LOOP_SIZE;

  /* clean up for the end of the function */
  pthread_cond_signal(&vl->wait_p);
  pthread_mutex_unlock(&vl->lock);
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
event event_init(void(*func)(void*), void* arg)
{
  event e = (event)calloc(1, sizeof(struct event_internal));

  e->func = func;
  e->argument = arg;

  return e;
}

/**
 * free any memory associated with an event
 *
 * @param e the event to destroy
 */
void event_destroy(event e)
{
  free(e);
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
void event_signal(void* func, void* args) {
  event_loop_put(event_loop_get(), event_init((event_function)func, args));
}

/**
 * destroys the old event loop singleton. This function should really only be
 * called when the program is about to exit. However, it could be called during
 * program execution as long as it is understood that all instances of
 * event_loop will be invalidated until another call to event_loop_get is made.
 * This function is also not thread safe and should only be called if main is
 * the only thread currently running.
 */
void event_loop_reset()
{
  event_loop vl;
  int i;

  if(created)
  {
    vl = event_loop_get();
    for(i = 0; i < EVENT_LOOP_SIZE; i++)
    {
      if(vl->queue[i] != NULL)
      {
        event_destroy(vl->queue[i]);
      }
    }
    created = 0;
  }
}

/**
 * Enters the event loop. This function will not return until another thread
 * chooses to terminate the event loop. Essentially this function should not
 * return until the program is ready to exit. There should also only be one
 * thread working on this part of the event loop.
 *
 * @param vl the event loop to start executing
 * @return this function will return an error code:
 *          0x0:   successful execution
 *          0x01:  attempt to enter a loop that is occupied
 */
int event_loop_enter()
{
  event e;
  event_loop vl = event_loop_get();

  /* start by checking to make sure this is the only thread in this loop */
  pthread_mutex_lock(&vl->lock);
  if(vl->occupied)
  {
    pthread_mutex_unlock(&vl->lock);
    return 0x01;
  }
  vl->occupied = 1;
  pthread_mutex_unlock(&vl->lock);

  /* from here on out, this is the only thread in this event loop     */
  /* the loop to execute events is very simple, grab event, run event */
  while((e = event_loop_take(vl)) != NULL) {
    e->func(e->argument);
    event_destroy(e);
  }

  return 0x0;
}

/**
 * stops the event loop from executing. This will wake up and threads that are
 * waiting on either a push into the event loop, or are trying to take from
 * the event loop and the put() and take() functions will return errors.
 *
 * @param vl the event loop to terminate
 */
void event_loop_terminate()
{
  event_loop vl = event_loop_get();
  pthread_mutex_lock(&vl->lock);

  vl->terminated = 1;
  pthread_cond_broadcast(&vl->wait_p);
  pthread_cond_broadcast(&vl->wait_t);

  pthread_mutex_unlock(&vl->lock);
}


