/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Event handling operations
 */

#ifndef EVENT_H_INCLUDE
#define EVENT_H_INCLUDE

/* scheduler includes */
#include <scheduler.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define EVENT_LOOP_SIZE 1024

/** interanl structure for an event */
typedef struct {
  void(*func)(scheduler_t*, void*); ///< The function that will be executed for this event
  void* argument;                   ///< The arguments for the function
  char* name;                       ///< Name of the event, used for debugging
  char*    source_name;             ///< Name of the source file creating the event
  uint16_t source_line;             ///< Line in the source file creating the event
} event_t;

/** internal structure for the event loop */
typedef struct event_loop {
  GAsyncQueue* queue; ///< The queue that is the core of the event loop
  int terminated;     ///< Flag that signals the end of the event loop
  int occupied;       ///< Does this loop already have a worker thread
} event_loop_t;


typedef void(*event_function)(scheduler_t*, void*);

/**
 * structure used to pass an argument and an integer to an event.
 */
typedef struct
{
  void* first;
  int second;
} arg_int;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

event_t* event_init(void(*func)(scheduler_t*, void*), void* arg, char* name, char* source_name, uint16_t source_line);
void     event_destroy(event_t* e);
int event_loop_put(event_loop_t* event_loop, event_t* event);
event_t* event_loop_take(event_loop_t* event_loop);

void     event_loop_destroy();

/* ************************************************************************** */
/* **** EventLoop Functions ************************************************* */
/* ************************************************************************** */

#define event_signal(func, args) event_signal_ext(func, args, #func, __FILE__, __LINE__)

void event_signal_ext(void* func, void* args, char* name, char* s_name, uint16_t s_line);
int  event_loop_enter(scheduler_t* scheduler, void(*)(scheduler_t*), void(*)(scheduler_t*));
void event_loop_terminate();

#endif /* EVENT_H_INCLUDE */
