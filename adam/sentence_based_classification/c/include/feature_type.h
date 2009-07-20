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

#ifndef __FEATURE_TYPE__H_
#define __FEATURE_TYPE__H_

#if defined(__cplusplus)
extern "C" {
#endif

typedef int c_bool;
#define FALSE 0
#define TRUE (1)
#define FT_CHAR_MAP "\n.:;,/\\|~`!@#$%^&*-_=+?()[]{}<>"
#define FT_CHAR_MAP_LEN 31

// Our simple datatype
typedef struct feature_type {
    char *string;
    char *stemmed;
    int start;
    int end;
    int length;
    c_bool word;
    c_bool capped;
    c_bool upper;
    c_bool number;
    c_bool incnum;
    int char_vector[31];
} feature_type;

// This function frees all the internal data in the datatype.
void feature_type_free(void *v);

// This function is used to create the datatype from a substring.
void* feature_type_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void feature_type_print(void *v);

#if defined(__cplusplus)
}
#endif

#endif
