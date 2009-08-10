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

#include <sparsevect.h>

#ifndef __SENTENCE_TYPE__H_
#define __SENTENCE_TYPE__H_

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct sentence_type {
    unsigned char *string;
    int start;
    int end;
    int position;
    unsigned char *filename;
    unsigned char *licensename;
    sv_vector *vector;
} sentence_type;

// This function frees all the internal data in the datatype.
void sentence_type_free(void *v);

// This function is used to create the datatype.
void* sentence_type_create(unsigned char *string, int start, int end, int position, unsigned char *filename, unsigned char *licensename, sv_vector *vector);

// This function is used to print the datatype.
void sentence_type_print(void *v);

void sentence_type_dump(void *v, FILE *file);
void* sentence_type_load(FILE *file);

#if defined(__cplusplus)
}
#endif

#endif
