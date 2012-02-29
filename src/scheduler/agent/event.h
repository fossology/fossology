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

#ifndef EVENT_H_INCLUDE
#define EVENT_H_INCLUDE

#include <glib.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define EVENT_LOOP_SIZE 1024

/** interanl structure for an event */
struct event_internal {
    void(*func)(void*); ///< the function that will be executed for this event
    void* argument;     ///< the arguments for the function
    char* name;         ///< name of the event, used for debugging
};

/** internal structure for the event loop */
struct event_loop_internal {
    GAsyncQueue* queue; ///< the queue that is the core of the event loop
    int terminated;     ///< flag that signals the end of the event loop
    int occupied;       ///< does this loop already have a worker thread
};

/**
 * structure used to hold of the information associated with an event. This was
 * created to essentially allow for a functor in C. This will store a function
 * pointer and the argument that will be passed to the function.
 */
typedef struct event_internal* event;

/**
 * structure used to pass an argument and an integer to an event.
 */
typedef struct
{
    void* first;
    int second;
} arg_int;

/**
 * An event loop that can be waited on. This essentially implements a concurrent
 * queue that a the creation thread will wait on. events can be added to the
 * event loop and will be executed and destroy correctly by the thread waiting
 * on the queue.
 */
typedef struct event_loop_internal* event_loop;

typedef void(*event_function)(void*);

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

event event_init(void(*func)(void*), void* arg, char* name);
void event_destroy(event e);

/* ************************************************************************** */
/* **** EventLoop Functions ************************************************* */
/* ************************************************************************** */

#define event_signal(func, args) event_signal_ext(func, args, #func)

void event_signal_ext(void* func, void* args, char* name);
void event_loop_terminate();
int  event_loop_enter(void(*)(void));
void event_loop_destroy();

#endif /* EVENT_H_INCLUDE */
