/*********************************************************************
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
*********************************************************************/

#ifndef CVECTOR_H_INCLUDE
#define CVECTOR_H_INCLUDE

/* std library includes */
#include <stdlib.h>
#include <stdio.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/*!
* @brief the function pointers for memory management
*
* struct that contains the function pointers to allow the data type to be
* correctly copied. For every type of data that would be held in vec list, there
* should be one of these. Despite having access to this struct, the only times
* when a user should create one of these is if they are writing a new data type
* for the cvector.
*/
struct _function_registry {
  const char* name;
  void* (*copy)(void*);
  void (*destroy)(void*);
};

/*!
 * @brief the cvector datatype
 *
 * Code to create a new cvector:
 *   cvector vec;
 *   radix_init(&vec);
 */
typedef struct cvector_internal* cvector;

/*!
 * @brief the cvector function registry
 *
 * this is used in the creation of a cvector to enable it to manage memory
 * correctly for whatever datatype the cvector is storing
 */
typedef struct _function_registry function_registry;

/*!
 * \brief iterator for the cvector class
 *
 * this is used to access elements in the cvector, this should work just like a
 * pointer to any other array in C in that ++,-- should work correctly and the
 * * operator will dereference the iterator. It is important to note that
 * dereferencing a cvector_iterator will return a pointer to the element not the
 * element itself.
 */
typedef void** cvector_iterator;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void cvector_init(cvector* vec, function_registry* memory_manager);
void cvector_copy(cvector* dst, cvector src);
void cvector_destroy(cvector vec);

/* ************************************************************************** */
/* **** Insertion Functions ************************************************* */
/* ************************************************************************** */

void cvector_push_back(cvector vec, void* datum);
cvector_iterator cvector_insert(cvector vec, cvector_iterator iter, void* datum);

/* ************************************************************************** */
/* **** Removal Functions *************************************************** */
/* ************************************************************************** */

void cvector_clear(cvector vec);
void cvector_pop_back(cvector vec);
cvector_iterator cvector_remove(cvector vec, cvector_iterator iter);

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

void* cvector_get(cvector vec, int index);
void* cvector_at(cvector vec, int index);
cvector_iterator cvector_begin(cvector vec);
cvector_iterator cvector_end(cvector vec);
int cvector_size(cvector vec);
int cvector_capacity(cvector vec);

/* ************************************************************************** */
/* **** Print Functions ***************************************************** */
/* ************************************************************************** */

//void cvector_print(cvector vec, FILE* pfile);

/* ************************************************************************** */
/* **** Registery Creation Functions **************************************** */
/* ************************************************************************** */

function_registry* function_registry_copy(function_registry* vec);
function_registry* int_function_registry();
function_registry* char_function_registry();
function_registry* double_function_registry();
function_registry* pointer_function_registry();
function_registry* string_function_registry();
function_registry* cvector_function_registry();

#endif /* CVECTOR_H_INCLUDE */
