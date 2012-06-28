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

/* std library */
#include <string.h>

/* local includes */
#include <cvector.h>

/* ************************************************************************** */
/* **** Private Members ***************************************************** */
/* ************************************************************************** */

/*!
* @brief the actual cvector struct
*
* vec struct holds the dynamic array and is passed to the function that manage
* the memory of the array, also holds the size of the cvector and the possible
* capacity of the cvecotr.
*/
struct cvector_internal
{
  int size;                   ///< the number of element in the cvector
  int capacity;               ///< the number of elements that data can store
  void** data;                ///< the array that controls access to the data
  function_registry* memory;  ///< the memory management functions employed by cvector
};

/**
* @brief changes the size of the array the cvector uses
*
* doubles the size of the array that cvector uses as the underlying data
* storage medium. cvector simply doubles the size of its storage space each
* time it needs to access an element and as a result this function does
* not take a size as an argument
*
* @param vec the vector to alter the size of
*/
void cvector_resize(cvector vec)
{
  /* local variables */
  int i;

  /* vec will double the size and copy over the data */
  vec->capacity = vec->capacity*2;

  /* allocate the new array to hold the data */
  vec->data = (void**)realloc(vec->data, vec->capacity * sizeof(void*));

  /* fill the rest of the array with NULL */
  for(i = vec->size; i < vec->capacity; i++)
  {
    vec->data[i] = NULL;
  }
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/*!
 * @brief constructor for the cvector struct
 *
 * @param vec: a pointer to the cvector
 * @param memory_manager: the function registry that will
 *                  holds the functions for memory cleanup
 */
void cvector_init(cvector* vec, function_registry* memory_manager)
{
  (*vec) = (cvector)calloc(1, sizeof(struct cvector_internal));
  (*vec)->data = (void**)calloc(1, sizeof(void*));
  (*vec)->size = 0;
  (*vec)->capacity = 1;
  (*vec)->memory = memory_manager;
}

/*!
 * @brief copy constructor for the cvector struct
 *
 * this function is meant to take the place of calling
 * cvector_init. This function should not be called on a
 * cvector that has had init called on it since this would
 * result in a memory leak.
 *
 * @param vec: a pointer to the cvector to be copied
 */
void cvector_copy(cvector* dst, cvector src)
{
  /* locals */
  cvector_iterator iter = NULL;

  /* initialize the new cvector */
  cvector_init(dst, function_registry_copy(src->memory));

  /* loop over the old cvector and copy everything over */
  for(iter = cvector_begin(src); iter != cvector_end(src); iter++)
  {
    cvector_push_back(*dst, *iter);
  }
}

/*!
 * @brief destructor for the cvector struct
 *
 * @param vec: a pointer to the cvector
 */
void cvector_destroy(cvector vec)
{
  cvector_iterator iter;

  /* loop through  every datum and call the delete on it */
  for(iter = cvector_begin(vec); iter != cvector_end(vec); iter++)
  {
    vec->memory->destroy(*iter);
  }

  /* free the data in the cvector and free the functions*/
  free(vec->data);
  free(vec->memory);

  vec->size = 0;
  vec->capacity = 0;
  vec->data = NULL;
  vec->memory = NULL;

  free(vec);
}

/* ************************************************************************** */
/* **** Insertion Functions ************************************************* */
/* ************************************************************************** */

/*!
 * @brief push a new element onto the cvector
 *
 * pushes a new element into the cvector, vec will copy
 * the pointer that is passed to it using the copy
 * function in the function register. Only the copy
 * will get cleaned up, the original is the calling
 * functions responsibility to clean up.
 *
 * @param vec: a pointer to the cvector
 * @param datum: the pointer to the datum to be pushed
 *                  into the vecotr
 */
void cvector_push_back(cvector vec, void* datum)
{
  /* test if the cvector needs resizing */
  if(vec->size == vec->capacity)
  {
    cvector_resize(vec);
  }

  /* there is enough room for a new element,  */
  /* increase the size and store the element  */
  /* using the copy function                  */
  if(datum != NULL)
  {
    vec->data[vec->size++] = vec->memory->copy(datum);
  }
  else
  {
    vec->data[vec->size++] = NULL;
  }
}

/*!
 * @brief insert an element into the vector at iter
 *
 * inserts the provided element at the location of iter into the vector. All
 * elements before the iterator will remain unchanged, and all elements after
 * iterator will be moved down by one element.
 *
 * @param vec the vector to alter
 * @param iter the location in the vector to insert the element
 * @param datum the element to insert
 */
cvector_iterator cvector_insert(cvector vec,
    cvector_iterator iter, void* datum)
{
  /* variable to store the return value */
  cvector_iterator ret;

  /* since vec function does bounds checking, do so */
  if(iter < cvector_begin(vec) || iter > cvector_end(vec))
  {
    return NULL;
  }

  /* test if the cvector needs to be bigger */
  if(vec->size == vec->capacity)
  {
    int offset = iter - cvector_begin(vec);
    cvector_resize(vec);
    iter = cvector_begin(vec) + offset;
  }

  /* do the actual insert function */
  vec->size++;
  ret = iter;
  datum = vec->memory->copy(datum);
  while(iter != cvector_end(vec))
  {
    void* temp = *iter;
    *iter = datum;
    datum = temp;
    iter++;
  }

  return ret;
}

/* ************************************************************************** */
/* **** Removal Functions *************************************************** */
/* ************************************************************************** */

/*!
 * @brief clears the contents of a vector
 *
 * completely empties a cvector removing all elements from the cvector. It is
 * important to note that this will not change the size of the cvector, it will
 * simple perform all the necessary actions to make the size zero.
 *
 * @param vec the cvector to clear
 */
void cvector_clear(cvector vec)
{
  /* iterator for deletion */
  cvector_iterator iter;

  /* delete everything currently in the cvector */
  for(iter = cvector_begin(vec); iter != cvector_end(vec); iter++)
  {
    vec->memory->destroy(*iter);
    *iter = NULL;
  }

  for(iter = cvector_end(vec); iter != vec->data + vec->capacity; iter++)
  {
    *iter = NULL;
  }

  /* set the size to zero since this cvector contains nothing */
  vec->size = 0;
}

/*!
 * @brief removes the last element from the vector.
 *
 * This method removes the final element from the cvector . Since the final
 * element is invalidated by this function, it is not returned.
 *
 * @param vec the cvector to remove the element from
 */
void cvector_pop_back(cvector vec)
{
  if(vec->size > 0)
  {
    vec->size--;
    vec->memory->destroy(vec->data[vec->size]);
    vec->data[vec->size] = NULL;
  }
}

/*!
 * @brief removes the element at the position of the given iterator
 *
 * removes the element that is at the position of the iterator. All elements
 * that come before the position remain unchanged, all elements that come after
 * the postition will move up one position.
 *
 * @param vec the cvector to remove the element from
 * @param iter the position that should be removed
 * @return an iterator to the new position
 */
cvector_iterator cvector_remove(cvector vec, cvector_iterator iter)
{
  /* save the input iterator so that it can be returned */
  cvector_iterator old_pos = iter;

  /* since vec function does bounds checking, do so */
  if(iter < cvector_begin(vec) || iter >= cvector_end(vec))
  {
    return NULL;
  }

  /* delete the element */
  vec->memory->destroy(*iter);

  /* do the actual remove function */
  vec->size--;
  while(iter != cvector_end(vec))
  {
    *iter = *(iter+1);
    iter++;
  }
  *iter = NULL;

  return old_pos - 1;
}

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

/*!
 * @brief get a pointer to an element in a buffer
 *
 * Do not worry about managing the memory returned by
 * vec function, the cvector will clean it up when
 * it is destroyed. vec function will not bounds check
 * the input.
 *
 * @param index: the index in the cvector that should
 *                  be returned.
 */
void* cvector_get(cvector vec, int index)
{
  return vec->data[index];
}

/*!
 * @brief get a pointer to an element in a buffer
 *
 * Do not worry about managing the memory returned by
 * vec function, the cvector will clean it up when
 * it is destroyed. vec function will bounds check
 * the input.
 *
 * @param index: the index in the cvector that should
 *                  be returned.
 */
void* cvector_at(cvector vec, int index)
{
  /* since vec function does bounds checking, do so */
  if(index < 0 || index >= vec->size)
  {
    return NULL;
  }
  return vec->data[index];
}

/*!
 * @brief returns a cvector_iterator to the beginning of the cvector
 *
 * @param vec: a pointer to the cvector
 */
cvector_iterator cvector_begin(cvector vec)
{
  return vec->data;
}

/*!
 * @brief returns a cvector_iterator to the end of the cvector
 *
 * @param vec: a pointer to the cvector
 */
cvector_iterator cvector_end(cvector vec)
{
  return vec->data + vec->size;
}

/*!
 * @brief returns the size of the cvector
 *
 * @param vec the relevant cvector
 * @return the size of vec
 */
int cvector_size(cvector vec)
{
  return vec->size;
}

/*!
 * @brief returns the number of elements that could fit in this cvector
 *
 * @param vec the cvector that is begin queried
 * @return the capacity of the cvector
 */
int cvector_capacity(cvector vec)
{
  return vec->capacity;
}

/* ************************************************************************** */
/* **** Print Functions ***************************************************** */
/* ************************************************************************** */

/*!
 * @brief prints a cvector to a file
 *
 * vec function will use the print function in the
 * fuction registry to print the entire cvector to
 * the file that is passed to vec function, for example
 * calling "cvector_print(<some cvector>, stdout)" will
 * print the cvector to the screen.
 *
 * @param vec: a pointer to the cvector
 * @param pfile: the FILE pointer that will be printed to
 *
 */
/*void cvector_print(cvector vec, FILE* pfile)
{
   local variables
  int length = strlen(vec->memory->name);
  cvector_iterator iter;

   write the name and size of the list to the file
  fwrite(&length, sizeof(int), 1, pfile);
  fwrite(vec->memory->name, sizeof(char), strlen(vec->memory->name), pfile);
  fwrite(&vec->size, sizeof(int), 1, pfile);

   loop through the data and print every element
  for(iter = cvector_begin(vec); iter != cvector_end(vec); iter++)
  {
    vec->memory->print(*iter, pfile);
  }
}*/

/* ************************************************************************** */
/* **** Memory Functions (Private) ****************************************** */
/* ************************************************************************** */

/* ********** integer memory management functions ********** */
/*!
 * @brief allocated memory for an int and store the int
 * @param to_copy
 */
void* int_copy(void* to_copy)
{
  int* i = (int*)calloc(1, sizeof(int));
  *i = *(int*)to_copy;
  return i;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  int_destroy(void* to_delete)
{
  free(to_delete);
}

/* ********** character memory management functions ********** */
/**
 * @brief allocated memory for a char and store the char
 * @param to_copy
 */
void* char_copy(void* to_copy)
{
  char* c = (char*)calloc(1, sizeof(char));
  *c = *(char*)to_copy;
  return c;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  char_destroy(void* to_delete)
{
  free(to_delete);
}

/* ********** double memory management functions  ********** */
/**
 * @brief allocated memory for a double and store the double
 * @param to_copy
 */
void* double_copy(void* to_copy)
{
  double* d = (double*)calloc(1, sizeof(double));
  *d = *(double*)to_copy;
  return d;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  double_destroy(void* to_delete)
{
  free(to_delete);
}

/* ********** pointer memory management functions ********** */
/**
 * @brief allocated memory for a pointer and store the pointer
 * @param to_copy
 */
void* pointer_copy(void** to_copy)
{
  void** v = (void**)calloc(1, sizeof(void*));
  *v = *(int**)to_copy;
  return v;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  pointer_destroy(void* to_delete)
{
  free(to_delete);
}

/* ********** string memory management functions ********** */
/**
 * @brief allocated memory for an int and store the int
 * @param to_copy
 */
void* string_copy(void* to_copy)
{
  char* s = (char*)calloc(strlen((char*)to_copy) + 1, sizeof(char));
  strcpy(s, (char*)to_copy);
  return s;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  string_destroy(void* to_delete)
{
  free(to_delete);
}

/* ********** cvector memory management functions ********** */
/**
 * @brief allocated memory for an int and store the int
 * @param to_copy
 */
void* cvector_copy_reg(void* to_copy)
{
  cvector v;
  cvector_copy(&v, (cvector)to_copy);
  return v;
}

/*!
 * @brief deallocate the memory for to_delete
 * @param to_delete
 */
void  cvector_destroy_reg(void* to_delete)
{
  cvector_destroy((cvector)to_delete);
  free(to_delete);
}

/* ************************************************************************** */
/* **** Memory Functions (Public) ******************************************* */
/* ************************************************************************** */

/*!
 * @brief copies a function registry
 *
 * This function is used when creating a new cvector that is of the same type as
 * an existing cvector. This is mostly used by the copy constructor for the
 * cvector and should rarely be used in other code.
 *
 * @return the new function registry
 */
function_registry* function_registry_copy(function_registry* vec)
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = vec->name;
  ret->copy = vec->copy;
  ret->destroy = vec->destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of ints
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* int_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "integer";
  ret->copy = &int_copy;
  ret->destroy = &int_destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of chars
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* char_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "character";
  ret->copy = &char_copy;
  ret->destroy = &char_destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of doubles
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* double_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "float";
  ret->copy = &double_copy;
  ret->destroy = &double_destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of pointers
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* pointer_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "pointer";
  ret->copy = (void* (*)(void*))&pointer_copy;
  ret->destroy = &pointer_destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of strings
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* string_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "string";
  ret->copy = &string_copy;
  ret->destroy = &string_destroy;

  return ret;
}

/*!
 * @brief creates a function registry for a cvector of cvectors
 *
 * This function should be called as one of the parameters for the init function
 * of the cvector i.e.
 *
 * @code cvector example;
 * @code cvector_init(&example, int_cvector_registry());
 *
 * @return the new function registry
 */
function_registry* cvector_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "cvector";
  ret->copy = &cvector_copy_reg;
  ret->destroy = &cvector_destroy_reg;

  return ret;
}
