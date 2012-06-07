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

/* local includes */
#include <pair.h>

/* ************************************************************************** */
/* **** Internal to pair.c ************************************************** */
/* ************************************************************************** */

/** internal structure for the pair. */
struct _pair_internal
{
  void* first;                   ///< the first element in the pair
  void* second;                  ///< the second element in the pair
  function_registry* first_mem;  ///< memory management for first data type
  function_registry* second_mem; ///< memory management for second data type
};

/**
 * @brief function to allocate and copy a new pair
 *
 * This is used by containers such at the cvector to create a new pair. This
 * function will be placed in a function registry so that other files can
 * access it.
 *
 * @param to_copy the pair that will be copied
 */
void* _pair_copy(void* to_copy)
{
  pair new = (pair)calloc(1, sizeof(struct _pair_internal));

  new->first_mem = function_registry_copy(((pair)to_copy)->first_mem);
  new->second_mem = function_registry_copy(((pair)to_copy)->second_mem);
  new->first = new->first_mem->copy(((pair)to_copy)->first);
  new->second = new->second_mem->copy(((pair)to_copy)->second);

  return new;
}

/**
 * @brief function to deallocate memory for a pair
 *
 * This is used by containers such the cvector to destroy an old pair. This
 * function will be placed in a function registry so other files can access
 * it.
 *
 * @param to_destroy the pair to be deallocated
 */
void  _pair_destroy(void* to_destroy)
{
  ((pair)to_destroy)->first_mem->destroy(((pair)to_destroy)->first);
  ((pair)to_destroy)->second_mem->destroy(((pair)to_destroy)->second);
  free(((pair)to_destroy)->first_mem);
  free(((pair)to_destroy)->second_mem);
  free(to_destroy);
}

/**
 *
 * @param to_print
 * @param ostr
 */
void  _pair_print(void* to_print, FILE* ostr)
{
  fprintf(ostr, "FIRST[");
  ((pair)to_print)->first_mem->print(((pair)to_print)->first, ostr);
  fprintf(ostr, "]\nSECOND[");
  ((pair)to_print)->second_mem->print(((pair)to_print)->second, ostr);
  fprintf(ostr, "]\n");
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * @brief constructor for the pair data type
 *
 * @param pair_ptr
 * @param first_mem
 * @param second_mem
 */
void pair_init(pair* pair_ptr, function_registry* first_mem,
                               function_registry* second_mem)
{
  (*pair_ptr) = (pair)calloc(1, sizeof(struct _pair_internal));
  (*pair_ptr)->first = NULL;
  (*pair_ptr)->second = NULL;
  (*pair_ptr)->first_mem = first_mem;
  (*pair_ptr)->second_mem = second_mem;
}

/**
 * @brief destructor for the pair data type
 *
 * @param curr
 */
void pair_destroy(pair curr)
{
  curr->first_mem->destroy(curr->first);
  curr->second_mem->destroy(curr->second);
  free(curr->first_mem);
  free(curr->second_mem);
  free(curr);
}

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

/**
 * @brief access the first element out of a pair
 *
 * @param curr the pair to be accessed
 * @return a pointer to the first element contained in the pair
 */
void* pair_first(pair curr)
{
  return curr->first;
}

/**
 * @brief access the second element out of a pair
 *
 * @param curr the pair to be accessed
 * @return a pointer to the second element contained in the pair
 */
void* pair_second(pair curr)
{
  return curr->second;
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

/**
 * @brief sets the first element in the pair
 *
 * @param curr
 * @param datum
 */
void pair_set_first(pair curr, void* datum)
{
  if(curr->first != NULL)
    curr->first_mem->destroy(curr->first);

  curr->first = curr->first_mem->copy(datum);
}

/**
 * @brief sets the first element in the pair
 *
 * @param curr
 * @param datum
 */
void pair_set_second(pair curr, void* datum)
{
  if(curr->second != NULL)
    curr->second_mem->destroy(curr->second);

  curr->second = curr->second_mem->copy(datum);
}

/**
 * @brief creates memory menagement for a pair struct
 *
 * this function allows this data type to be stored in other datatypes that use
 * the same function registry such as cvector.
 *
 * @return a function registry to do memory management for a pair.
 */
function_registry* pair_function_registry()
{
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "pair";
  ret->copy = &_pair_copy;
  ret->destroy = &_pair_destroy;
  ret->print = &_pair_print;

  return ret;
}
