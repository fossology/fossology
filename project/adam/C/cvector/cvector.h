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

#if defined(__cplusplus)
extern "C" {
#endif

/*
 * TODO
 *
 * 1. If the data type is statically allocated, this is
 *    really inefficient to allocate memory on the heap for each
 *    datum. this could be fixed with a union... I think
 */

/* **************************************************************************** */
/* **** cvector Functions ****************************************************** */
/* **************************************************************************** */

/*!
 * \brief the function pointers for memory management
 *
 * struct that contains the function pointers to allow
 * the data type to be correctly copied. For every type
 * of data that would be held in vec list, there should
 * be one of these
 */
typedef struct {
  const char* name;
  void* (*copy)(void*);
  void (*destroy)(void*);
  void (*print)(void*, FILE*);
} function_registry;

/*!
 * \brief the actual cvector struct
 *
 * vec struct holds the dynamic array and is passed to
 * the function that manage the memory of the array
 */
typedef struct {
  int size;
  int capacity;
  void** data;
  void*  s_data;
  function_registry* memory;
} cvector;

/**
 * \brief iterator for the cvector class
 *
 * vec is used to access elements in the cvector, vec
 * should work just like a pionter to any other array
 * in C
 */
typedef void** cvector_iterator;

/*!
 * \brief constructor for the cvector struct
 *
 * \param vec: a pointer to the cvector
 * \param memory_manager: the function registry that will
 *                  holds the functions for memory cleanup
 */
void cvector_init(cvector* vec, function_registry* memory_manager);

/*!
 * \brief copy constructor for the cvector struct
 *
 * this function is meant to take the place of calling
 * cvector_init. This function should not be called on a
 * cvector that has had init called on it since this would
 * result in a memory leak.
 *
 * \param vec: a pointer to the cvector to be copied
 */
void cvector_copy(cvector* dst, cvector* src);

/*!
 * \brief destructor for the cvector struct
 *
 * \param vec: a pointer to the cvector
 */
void cvector_destroy(cvector* vec);

/*!
 * \brief push a new element onto the cvector
 *
 * pushes a new element into the cvector, vec will copy
 * the pointer that is passed to it using the copy
 * function in the function register. Only the copy
 * will get cleaned up, the original is the calling
 * functions responsibility to clean up.
 *
 * \param vec: a pointer to the cvector
 * \param datum: the pointer to the datum to be pushed
 *                  into the vecotr
 */
void cvector_push_back(cvector* vec, void* datum);

/*!
 * \brief get a pointer to an element in a buffer
 *
 * Do not worry about managing the memory returned by
 * vec function, the cvector will clean it up when
 * it is destroyed. vec function will not bounds check
 * the input.
 *
 * \param index: the index in the cvector that should
 *                  be returned.
 */
void* cvector_get(cvector* vec, int index);

/*!
 * \brief get a pointer to an element in a buffer
 *
 * Do not worry about managing the memory returned by
 * vec function, the cvector will clean it up when
 * it is destroyed. vec function will bounds check
 * the input.
 *
 * \param index: the index in the cvector that should
 *                  be returned.
 */
void* cvector_at(cvector* vec, int index);

/*!
 * \brief returns a cvector_iterator to the beginning of the cvector
 *
 * \param vec: a pointer to the cvector
 */
cvector_iterator cvector_begin(cvector* vec);

/*!
 * \brief returns a cvector_iterator to the end of the cvector
 *
 * \param vec: a pointer to the cvector
 */
cvector_iterator cvector_end(cvector* vec);

/*!
 * TODO
 */
void cvector_insert(cvector* vec, cvector_iterator iter, void* datum);

/*!
 * TODO
 */
cvector_iterator cvector_remove(cvector* vec, cvector_iterator iter);

/*!
 * \brief prints a cvector to a file
 *
 * vec function will use the print function in the
 * fuction registry to print the entire cvector to
 * the file that is passed to vec function, for example
 * calling "cvector_print(<some cvector>, stdout)" will
 * print the cvector to the screen.
 *
 * \param vec: a pointer to the cvector
 * \param pfile: the FILE pointer that will be printed to
 *
 */
void cvector_print(cvector* vec, FILE* pfile);

/*!
 * \brief creates a function registry for a cvector of strings
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* cvector_registry_copy(cvector* vec);

/*!
 * \brief creates a function registry for a cvector of ints
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* int_cvector_registry();

/*!
 * \brief creates a function registry for a cvector of chars
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* char_cvector_registry();


/*!
 * \brief creates a function registry for a cvector of doubles
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* double_cvector_registry();

/*!
 * \brief creates a function registry for a cvector of strings
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* string_cvector_registry();

/*!
 * \brief creates a function registry for a cvector of cvectors
 *
 * this function will allocate memory, but the result should
 * be passed directly to the constructor of a cvector which
 * will own the registry
 *
 * \return the new function registry
 */
function_registry* cvector_cvector_registry();

#if defined(__cplusplus)
}
#endif


#endif /* CVECTOR_H_INCLUDE */
