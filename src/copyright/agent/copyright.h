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

#define myBUFSIZ        2048

#include <stdio.h>

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
typedef struct copyright_internal* copyright;

/**
 * A copyright that the copyright object has identified in a file
 *
 * This contains:
 *   1. the text of the copyright
 *   2. the string in the diction that matched this entry
 *   3. the string in the names that matched this entry
 *   4. the start byte in the file of the copyright text
 *   5. the end byte in the file of the copyright text
 *
 * The user of this class should not need to worry about memory management for
 * this struct. All the memory should be correctly managed by the copyright
 * struct and will get cleaned up when analyze or copyright_destroy is called.
 */
typedef struct copy_entry_internal* copy_entry;

/**
 * An iterator to iterate across the copyright matches within a copyright
 *
 * Since a copyright will store all the matches that it found after a call to
 * analyze(copy), this iterator can be used to access these matches. This is
 * nothing more than an abstraction of a pointer and should work very similarly.
 * Dereferencing this will return a string that can be accessed like any other
 * c-string.
 */
typedef copy_entry* copyright_iterator;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

int  copyright_init(copyright* copy, char* copy_dir, char* name_dir);
void copyright_destroy(copyright copy);

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

void copyright_clear(copyright copy);
void copyright_analyze(copyright copy, FILE* file_name);
void copyright_email_url(copyright copy, char* file);

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

copyright_iterator copyright_begin(copyright copy);
copyright_iterator copyright_end(copyright copy);
copy_entry copyright_at(copyright copy, int index);
copy_entry copyright_get(copyright copy, int index);
int copyright_size(copyright copy);

/* ************************************************************************** */
/* **** Copy Entry Accessor Functions *************************************** */
/* ************************************************************************** */
char* copy_entry_text(copy_entry entry);
char* copy_entry_name(copy_entry entry);
char* copy_entry_dict(copy_entry entry);
char* copy_entry_type(copy_entry entry);
int copy_entry_start(copy_entry entry);
int copy_entry_end(copy_entry entry);

#endif /* COPYRIGHT_H_INCLUDE */
