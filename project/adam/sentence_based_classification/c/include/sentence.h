/*********************************************************************
Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

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

#ifndef __SENTENCE_TYPE__H_
#define __SENTENCE_TYPE__H_

#include <sparsevect.h>

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct sentence {
    char *string;
    int start;
    int end;
    int position;
    char *filename;
    char *licensename;
    sv_vector vector;
} sentence;

int default_list_type_sentence(void);
int default_list_type_sentence_init(void);
void* default_list_type_function_sentence_create(void *v);
void* default_list_type_function_sentence_copy(void *v);
void default_list_type_function_sentence_destroy(void *v);
void default_list_type_function_sentence_print(void *v, FILE *f);
int default_list_type_function_sentence_dump(void *v, FILE *f);
void* default_list_type_function_sentence_load(FILE *f);

// This function is used to create the datatype.
sentence* sentence_create(char *string, int start, int end, int position, char *filename, char *licensename, sv_vector vector);

#if defined(__cplusplus)
}
#endif

#endif
