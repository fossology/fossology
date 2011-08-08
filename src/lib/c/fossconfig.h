/* **************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

************************************************************** */

#ifndef FOSSCONFIG_H_INCLUDE
#define FOSSCONFIG_H_INCLUDE

#include <glib.h>

/** the parser error domain */
#define PARSE_ERROR    0
#define RETRIEVE_ERROR 1

/** parser error codes */
typedef enum
{
  fo_missing_file,
  fo_invalid_file,
  fo_invalid_key,
  fo_invalid_group,
  fo_missing_group,
  fo_missing_key,
  fo_load_config
} fo_error_codes;

int   fo_config_is_open();
int   fo_config_load_default(GError** error);
int   fo_config_load(char* fname, GError** error);
char* fo_config_get(char* group, char* key, GError** error);
char* fo_config_get_list(char* group, char* key, int idx, GError** error);
int   fo_config_is_list(char* group, char* key, GError** error);
int   fo_config_list_length(char* group, char* key, GError** error);
void  fo_config_free(void);

char** fo_config_group_set(int* length);
char** fo_config_key_set(char* group, int* length);
int    fo_config_has_group(char* group);
int    fo_config_has_key(char* group, char* key);

#endif /* FOSSCONFIG_H_INCLUDE */
