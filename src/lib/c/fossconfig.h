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
#define PARSE_ERROR    1
#define RETRIEVE_ERROR 2

/** parser error codes */
typedef enum
{
  fo_missing_file,
  fo_missing_group,
  fo_missing_key,
  fo_invalid_key,
  fo_invalid_group,
  fo_invalid_file,
  fo_invalid_join,
  fo_load_config
} fo_error_codes;

typedef struct {
    GTree* group_map;
    GTree* key_sets;
    char** group_set;
    int n_groups;
} fo_conf;

fo_conf* fo_config_load(char* fname, GError** error);
void     fo_config_free(fo_conf* conf);
void     fo_config_join(fo_conf* dst, fo_conf* src, GError** error);

char* fo_config_get(fo_conf* conf, const char* group, const char* key, GError** error);
char* fo_config_get_list(fo_conf* conf, char* group, char* key, int idx, GError** error);
int   fo_config_is_list(fo_conf* conf, char* group, char* key, GError** error);
int   fo_config_list_length(fo_conf* conf, char* group, char* key, GError** error);

char** fo_config_group_set(fo_conf* conf, int* length);
char** fo_config_key_set(fo_conf* conf, char* group, int* length);
int    fo_config_has_group(fo_conf* conf, char* group);
int    fo_config_has_key(fo_conf* conf, char* group, char* key);
char *trim(char *ptext);

#endif /* FOSSCONFIG_H_INCLUDE */
