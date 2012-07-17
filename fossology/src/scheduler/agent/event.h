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

/* scheduler includes */
#include <scheduler.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

#define EVENT_LOOP_SIZE 1024

/** interanl structure for an event */
typedef struct {
    void(*func)(scheduler_t*, void*); ///< the function that will be executed for this event
    void* argument;                   ///< the arguments for the function
    char* name;                       ///< name of the event, used for debugging
    char*    source_name;
    uint16_t source_line;
} event_t;

/** internal structure for the event loop */
typedef struct event_loop {
    GAsyncQueue* queue; ///< the queue that is the core of the event loop
    int terminated;     ///< flag that signals the end of the event loop
    int occupied;       ///< does this loop already have a worker thread
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

void     event_loop_destroy();

/* ************************************************************************** */
/* **** EventLoop Functions ************************************************* */
/* ************************************************************************** */

#define event_signal(func, args) event_signal_ext(func, args, #func, __FILE__, __LINE__)

void event_signal_ext(void* func, void* args, char* name, char* s_name, uint16_t s_line);
int  event_loop_enter(scheduler_t* scheduler, void(*)(scheduler_t*), void(*)(scheduler_t*));
void event_loop_terminate();

#endif /* EVENT_H_INCLUDE */
