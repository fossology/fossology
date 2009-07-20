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

#ifndef __STEM__H_
#define __STEM__H_

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct stem {
    char *string;
    char *stemmed;
    int start;
    int end;
} stem;

// This function frees all the internal data in the datatype.
void stem_free(void *v);

// This function is used to create the datatype from a substring.
void* stem_create_from_string(char *string, int start, int end);

// This function is used to print the datatype.
void stem_print(void *v);

#if defined(__cplusplus)
}
#endif

#endif
