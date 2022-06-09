/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief FOSSology library to read config file
 */
#ifndef FOSSCONFIG_H_INCLUDE
#define FOSSCONFIG_H_INCLUDE

#include <glib.h>

/** The parser error domain */
#define PARSE_ERROR    1
#define RETRIEVE_ERROR 2

/** Parser error codes */
typedef enum
{
  fo_missing_file,  ///< File is missing
  fo_missing_group, ///< Required group is missing
  fo_missing_key,   ///< Required key is missing
  fo_invalid_key,   ///< Requested key is invalid
  fo_invalid_group, ///< Requested group is invalid
  fo_invalid_file,  ///< File is invalid
  fo_invalid_join,  ///< Join is invalid
  fo_load_config    ///< Unable to load config
} fo_error_codes;

/** FOSSology config file object */
typedef struct
{
  GTree* group_map; ///< Tree of groups in conf file
  GTree* key_sets;  ///< Tree of sets of keys
  char** group_set; ///< Array of groups
  int n_groups;     ///< Number of groups
} fo_conf;

fo_conf* fo_config_load(char* fname, GError** error);
void fo_config_free(fo_conf* conf);
void fo_config_join(fo_conf* dst, fo_conf* src, GError** error);

char* fo_config_get(fo_conf* conf, const char* group, const char* key, GError** error);
char* fo_config_get_list(fo_conf* conf, char* group, char* key, int idx, GError** error);
int fo_config_is_list(fo_conf* conf, char* group, char* key, GError** error);
int fo_config_list_length(fo_conf* conf, char* group, char* key, GError** error);

char** fo_config_group_set(fo_conf* conf, int* length);
char** fo_config_key_set(fo_conf* conf, char* group, int* length);
int fo_config_has_group(fo_conf* conf, char* group);
int fo_config_has_key(fo_conf* conf, char* group, char* key);
char* trim(char* ptext);

#endif /* FOSSCONFIG_H_INCLUDE */
