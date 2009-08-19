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

#ifndef __TOKEN__H_
#define __TOKEN__H_

#if defined(__cplusplus)
extern "C" {
#endif

// Our simple datatype
typedef struct token {
    char *string;
    int start;
    int end;
    int length;
} token;

int default_list_type_token(void);
int default_list_type_token_init(void);
void* default_list_type_function_token_create(void *v);
void* default_list_type_function_token_copy(void *v);
void default_list_type_function_token_destroy(void *v);
void default_list_type_function_token_print(void *v, FILE *f);
int default_list_type_function_token_dump(void *v, FILE *f);
void* default_list_type_function_token_load(FILE *f);
token* token_create_from_string(char *string, int start, int end);

#if defined(__cplusplus)
}
#endif

#endif
