/***************************************************************
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

 ***************************************************************/

#ifndef  RADIXTREE_H_INCLUDE
#define RADIXTREE_H_INCLUDE

#include <stdio.h>
#include <cvector.h>

#if defined(__cplusplus)
extern "C" {
#endif

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/*!
 * @brief The radix tree datatype
 *
 * Code to create a new radix tree:
 *   radix_tree t;
 *   radix_init(&t);
 */
typedef struct _tree_internal* radix_tree;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void radix_init(radix_tree* tree);
void radix_copy(radix_tree* tree, radix_tree reference);
void radix_destroy(radix_tree tree);

/* ************************************************************************** */
/* *** Modifier Functions *************************************************** */
/* ************************************************************************** */

void radix_insert(radix_tree tree, const char* string);
void radix_insert_all(radix_tree tree, char** first, char** last);

/* ************************************************************************** */
/* *** Accessor Functions *************************************************** */
/* ************************************************************************** */

int radix_contains(radix_tree tree, char* string);
int radix_match(radix_tree tree, char* dst, char* src);
int radix_match_within(radix_tree tree, char* dst, char* src, int threshold);
int radix_copy_to(radix_tree tree, cvector dst);
void radix_print(radix_tree tree, FILE* ostr);

#if defined(__cplusplus)
}
#endif

#endif /* RADIXTREE_H_INCLUDE */

