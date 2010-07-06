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

#ifndef COPYRIGHT_H_INCLUDE
#define COPYRIGHT_H_INCLUDE

#if defined(__cplusplus)
extern "C" {
#endif

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * The copyright datatype
 *
 * to create:
 *   copyright copy;
 *   copyright_init(copy)
 */
typedef struct _copyright_internal* copyright;

/**
 * An iterator to iterate across the copyright matches within a copyright
 *
 * Since a copyright will store all the matches that it found after a call to
 * analyze(copy), this iterator can be used to access these matches. This is
 * nothing more than an abstraction of a pointer and should work very similarly.
 * Dereferencing this will return a string that can be accessed like any other
 * c-string.
 */
typedef char** copyright_iterator;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void copyright_init(copyright* copy);
void copyright_copy(copyright* copy, copyright reference);
void copyright_destroy(copyright copy);

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

void copyright_clear(copyright copy);
void copyright_analyze_file(copyright copy, const char* file_name);
void copyright_add_name(copyright copy, const char* name);
void copyright_add_entry(copyright copy, const char* entry);

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

copyright_iterator copyright_begin(copyright copy);
copyright_iterator copyright_end(copyright copy);
char* copyright_at(copyright copy, int index);
char* copyright_get(copyright copy, int index);
int copyright_size(copyright copy);

#if defined(__cplusplus)
}
#endif

#endif /* COPYRIGHT_H_INCLUDE */
