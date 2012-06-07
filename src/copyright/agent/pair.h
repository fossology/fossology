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

#ifndef PAIR_H_INCLUDE
#define PAIR_H_INCLUDE

/* local includes */
#include <cvector.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * The pair data type that allows the creation of an association between two
 * different data types.
 */
typedef struct _pair_internal* pair;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void pair_init(pair* pair_ptr, function_registry* first_mem,
                               function_registry* second_mem);
void pair_destroy(pair curr);

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

void* pair_first(pair curr);
void* pair_second(pair curr);

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

void pair_set_first(pair curr, void* datum);
void pair_set_second(pair curr, void* datum);
function_registry* pair_function_registry();

#endif /* PAIR_H_ */
