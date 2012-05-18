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

/* local includes */
#include <fossconfig.h>

/* std library includes */
#include <ctype.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>

/* glib includes */
#include <glib.h>

/* ************************************************************************** */
/* *** utility ************************************************************** */
/* ************************************************************************** */

/**
 * Complicated regular expression for parsing the different elements of the
 * ini file format.
 *
 * The parts:
 *   line 1: matches ini comments             : "; something \n"
 *   line 2: matches ini groups               : "[group_name]\n"
 *   line 3: matches ini key-value pairs      : "key = value\n"
 *   line 4: matches ini key-value list pairs : "key[] = value\n"
 *
 * please refer to the glib regular expression syntax for more information about
 * how the different parts of the regex work.
 */
static const gchar* fo_conf_pattern = "\
    (?<comment>;.*)\n|\
    (?<group>  \\[ (?:[ \t]*) (?<gname>[\\w\\d_]+) (?:[ \t]*) \\]) \n|\
    (?<key>    ([\\.\\w\\d_-]+))      (?:[ \t]*) = (?:[ \t]*)(?<value>.*)\n|\
    (?<klist>  ([\\.\\w\\d_-]+))\\[\\](?:[ \t]*) = (?:[ \t]*)(?<vlist>.*)\n|\
    (?<error>  (?:\\S+)(?:[ \t]*))\n";

/**
 * Regular expression that is used to match variables in the configuration file.
 * THis simply matches a '$' followed by a set of alphabetic characters.
 */
static const gchar* fo_conf_variable = "\\$(\\w+)";

GRegex* fo_conf_parse;
GRegex* fo_conf_replace;

/**
 * A wrapper function for the strcmp function that allows it to mascarade as a
 * GCompareDataFunc
 *
 * @param a c string to be compared
 * @param b c string to be compare
 * @param user_data not used
 * @return an integral value indicating the relationship between strings
 */
static gint str_comp(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return strcmp((char*)a, (char*)b);
}

/**
 * Function that collects all of the keys for a GTree into a single array of
 * strings. This is used to grab the sets of keys for the fo_config_key_set and
 * fo_config_group_set functions  printf("%s\n", (*error)->message);
 *
 *
 * @param key the key for this particular key/value pair
 * @param value the value for this particular key/value pair
 * @param data not used
 * @return always return 0 so that the traversal continues
 */
static gboolean collect_keys(char* key, gpointer* value, char** data)
{
  int idx = 0;

  /* find first empty key */
  while(data[idx])
    idx++;

  data[idx] = key;
  return FALSE;
}

/* ************************************************************************** */
/* *** private functions **************************************************** */
/* ************************************************************************** */

#define BUFFER_SIZE 4096
#define yynext() (c = next()) != EOF
#define throw_error(error, domain, code, ...) \
    { g_set_error(error, domain, code, __VA_ARGS__); \
    return 0; }

/**
 * A glib regex replace callback function. This will get called from the
 * fo_config_key when it call g_regex_replace_evel().
 *
 * @param match  The regex match that was found in the text i.e. '$HI'
 * @param ret    A GString that is the return location for this function
 * @param data   User data passed into the function, currently unused.
 * @return  always return FALSE to continue the traversal
 */
static gboolean fo_config_sub(const GMatchInfo* match, GString* ret,
    gpointer data)
{
  GTree* group = (GTree*)data;
  gchar* key = g_match_info_fetch(match, 1);
  gchar* sub = g_tree_lookup(group, key);

  g_string_append(ret, sub);
  g_free(key);

  return FALSE;
}

/**
 * @brief Inserts a new Key/Value pair into the mapping of keys to values.
 *
 * Since the values need to be strings, if the key that is being is inserted is
 * a list then this uses a system of "[value1][value2]...[valueN]" to store the
 * value. This is done under the assumption that lists are not going to be
 * extremely long.
 *
 * @param group  The Group that this key belongs to
 * @param key    The key that the value is associated with
 * @param val    The value that the key maps to
 * @param list   If the key/value pair is a list
 * @param fname  The name of the file that this key was found in
 * @param line   The line number in the file that this key was found on
 * @param error  GError struct that is used to pass errors out of this function
 * @return  always returns 1
 */
static int fo_config_key(GTree* group, gchar* key, gchar* val, gboolean list,
    gchar* fname, guint line, GError** error)
{
  gchar* tmp = g_regex_replace_eval(fo_conf_replace, val, -1, 0, 0,
      fo_config_sub, group, NULL);

  if(group == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[line %d]: key \"%s\" does not have an associated group",
        fname, line, key);

  if(list)
  {
    if((val = g_tree_lookup(group, key)))
    {
      val = g_strdup_printf("%s[%s]", val, tmp);
      g_free(tmp);
    }
    else
    {
      val = g_strdup_printf("[%s]", tmp);
      g_free(tmp);
    }
  }
  else
  {
    val = tmp;
  }

  g_tree_insert(group, g_strdup(key), val);
  return 1;
}

/**
 * @brief decides what to do with any one line of an input file
 *
 * Based upon what part of the regex matches, this chooses what to do with the
 * line from the input file.
 *
 * portion/result:
 *   group: create a new GTree* for the new group and move on
 *   key:   call fo_config_key with the key/value pair and list set to FALSE
 *   klist: call fo_config_key with the key/value pair and list set to TRUE
 *
 * @param match      The regex match that was found
 * @param g_current  The current group that is being read from the file
 * @param dest       The fo_conf struct that all this is being placed in
 * @param yyfile     The name of the file that is being parsed
 * @param yyline     The line of the file that is being parsed
 * @param error      GError struct used to pass errors out
 * @return  FALSE if succesfull.
 */
static gboolean fo_config_eval(const GMatchInfo* match, GTree** g_current,
    fo_conf* dest, gchar* yyfile, guint yyline, GError** error)
{
  gchar* error_t = NULL;

  /* check to make sure we haven't hit an error */
  if((error_t = g_match_info_fetch_named(match, "error")) != NULL)
  {
    g_set_error(error, PARSE_ERROR, fo_invalid_file,
        "%s[line %d]: incorrectly formated line \"%s\".",
        yyfile, yyline, error_t);
    g_free(error_t);
    return TRUE;
  }

  gchar* group = g_match_info_fetch_named(match, "group");
  gchar* gname = g_match_info_fetch_named(match, "gname");
  gchar* key   = g_match_info_fetch_named(match, "key");
  gchar* value = g_match_info_fetch_named(match, "value");
  gchar* klist = g_match_info_fetch_named(match, "klist");
  gchar* vlist = g_match_info_fetch_named(match, "vlist");
  gchar* wrong = g_match_info_fetch_named(match, "error");

  if(group != NULL && group[0])
  {
    *g_current = g_tree_new_full(str_comp, NULL, g_free, g_free);
    g_tree_insert(dest->group_map, g_strdup(gname), *g_current);
  }
  else if(key != NULL && key[0])
  {
    if(!fo_config_key(*g_current, key, value, FALSE, yyfile, yyline, error))
      return TRUE;
  }
  else if(klist != NULL && klist[0])
  {
    if(!fo_config_key(*g_current, klist, vlist, TRUE, yyfile, yyline, error))
      return TRUE;
  }

  g_free(group);
  g_free(gname);
  g_free(key);
  g_free(value);
  g_free(klist);
  g_free(vlist);
  g_free(wrong);

  return FALSE;
}

/* ************************************************************************** */
/* *** public interface ***************************************************** */
/* ************************************************************************** */

/**
 * @brief load the configuration information from the provided file. If the user
 *        has not done a fo_config_free since the last fo_config_load, this will
 *        make sure to call that first. In other words, it is assumed that if
 *        this is called the configuration file has changed and the user would
 *        like to use the new copy.
 *
 * @param fname the name of the configuration file
 * @param error object that allows errors to propagate up the stack
 * @return 0 for failure, 1 for success
 */
fo_conf* fo_config_load(char* rawname, GError** error) {
  fo_conf* ret;
  gchar text[BUFFER_SIZE];
  guint yyline = 1;
  FILE* fd;
  GMatchInfo* match;

  if(rawname == NULL)
    return NULL;

  if(fo_conf_parse == NULL)
    fo_conf_parse = g_regex_new(fo_conf_pattern,
        G_REGEX_EXTENDED | G_REGEX_OPTIMIZE, 0, NULL);
  if(fo_conf_replace == NULL)
    fo_conf_replace = g_regex_new(fo_conf_variable,
        G_REGEX_EXTENDED | G_REGEX_OPTIMIZE, 0, NULL);

  if((fd = fopen(rawname, "r")) == NULL)
    throw_error(error, PARSE_ERROR, fo_missing_file,
        "unable to open configuration file \"%s\"", rawname);

  ret = g_new0(fo_conf, 1);
  ret->group_map = NULL;
  ret->key_sets = NULL;
  ret->group_set = NULL;
  ret->n_groups = 0;
  ret->group_map = g_tree_new_full(str_comp, NULL, g_free,
      (GDestroyNotify)g_tree_destroy);

  GTree* g_current = NULL;

  while(fgets(text, sizeof(text), fd) != NULL)
  {
    if(g_regex_match(fo_conf_parse, text, 0, &match))
    {
      fo_config_eval(match, &g_current, ret, rawname, yyline, error);

      if(*error)
        return NULL;
    }

    g_match_info_free(match);
    match = NULL;
    yyline++;
  }

  fclose(fd);

  return ret;
}

/**
 * @brief Gets an element based on its group name and key name. If the group or
 *        key is not found, the error object is set and NULL is returned.
 *
 * @param group c string that is the name of the group
 * @param key c string that is the name of the key for the key/value pair
 * @return the c string representation of the value
 */
char* fo_config_get(fo_conf* conf, char* group, char* key, GError** error)
{
  GTree* tree;
  char* ret = NULL;

  if(!conf || conf->group_map == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_load_config,
        "ERROR: invalid fo_conf object passed to fo_config_get");

  if((tree = g_tree_lookup(conf->group_map, group)) == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_missing_group,
        "ERROR: unknown group \"%s\"", group);

  if((ret = g_tree_lookup(tree, key)) == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_missing_key,
        "ERROR: unknown key=\"%s\" for group=\"%s\"", key, group);

  return g_tree_lookup(tree, key);
}

/**
 * @brief Keys can be associated with multiple values. If this is the case for a
 *        particular key, use this function instead of fo_config_get. This also
 *        takes the index of the element in the list. Index work identically to
 *        standard c-array indices. It is important to note event though keys
 *        will appear as "key[]" in the config file this function just takes
 *        "key" as the key
 *
 * @param group c string that is the name of the group
 * @param key c string that is the name of the key for the key/value pair
 * @param idx the index of the value in the value list
 * @param error object that allows errors to propagate up the stack
 * @return c string representation of the value, once returned the caller owns
 *         this pointer. make sure to call g_free on it
 */
char* fo_config_get_list(fo_conf* conf, char* group, char* key, int idx, GError** error)
{
  char* val;
  int depth;
  char* curr;


  if(!conf || conf->group_map == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_load_config,
        "ERROR: invalid fo_conf object passed to fo_config_get_list");

  if(!fo_config_is_list(conf, group, key, error))
    if(!(*error))
      throw_error(
          error,
          RETRIEVE_ERROR,
          fo_invalid_key,
          "ERROR: %s[%s] must be of type list to get list element", group, key);

  if(idx < 0 || idx >= fo_config_list_length(conf, group, key, error))
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_invalid_key,
        "ERROR: %s[%s] %d is out of range", group, key, idx);

  if(*error)
    return NULL;

  val = g_tree_lookup(
      g_tree_lookup(conf->group_map, group), key);

  curr = val;
  for(depth = 0; depth < idx;)
  {
    while(*(++curr) != '[');
    depth++;
  }

  val = curr + 1;
  while(*(++curr) != ']');
  val = g_strndup(val, curr - val);

  return val;
}

/**
 * @brief Checks if a particular value is a list or just a normal value.
 *
 * @param group c string name of the group
 * @param key c string name of the key
 * @return 0 if it isn't a list, 1 if it is
 */
int fo_config_is_list(fo_conf* conf, char* group, char* key, GError** error)
{
  GTree* tree;
  char* val;

  if(!conf || conf->group_map == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_load_config,
        "ERROR: invalid fo_conf object passed to fo_config_is_list");

  if((tree = g_tree_lookup(conf->group_map, group)) == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_missing_group,
        "ERROR: unknown group \"%s\"", group);

  if((val = g_tree_lookup(tree, key)) == NULL)
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_missing_key,
        "ERROR: unknown key/value expression \"%s\"", key);

  return val[0] == '[';
}

/**
 * @brief gets the length of the list associated with a particular list key
 *
 * @param group c string name of the group
 * @param key c string name of the key
 * @return the number of elements in the list, on error returns 0
 */
int fo_config_list_length(fo_conf* conf, char* group, char* key, GError** error)
{
  char* val;
  char* curr;
  int count = 0;

  if(!fo_config_is_list(conf, group, key, error))
    throw_error(
        error,
        RETRIEVE_ERROR,
        fo_invalid_group,
        "ERROR: %s[%s] must be of type list to get length", group, key);
  if(*error)
    return 0;

  val = g_tree_lookup(
      g_tree_lookup(conf->group_map, group), key);

  for(curr = val; *curr; curr++)
    if(*curr == '[')
      count++;

  return count;
}

/**
 * @brief Frees the memory associated with the internal configuration data
 *        structures.
 *
 * @param conf the fo_conf struct to free
 */
void fo_config_free(fo_conf* conf)
{
  if(!conf) return;
  if(conf->group_map) g_tree_unref(conf->group_map);
  if(conf->key_sets)  g_tree_unref(conf->key_sets);
  if(conf->group_set) g_free(conf->group_set);

  conf->group_map = NULL;
  conf->key_sets = NULL;
  conf->group_set = NULL;

  g_free(conf);
}

/* ************************************************************************** */
/* *** special interface **************************************************** */
/* ************************************************************************** */

/**
 * @brief Gets the set of group names. This returns an array of strings that the
 *        user can iterate to get all group names. The user does not own the
 *        return of this function and should not free any of the memory
 *        associated with it.
 *
 * @param length pointer allowing the number of groups to be returned
 * @return array of strings containing all the group names
 */
char** fo_config_group_set(fo_conf* conf, int* length)
{
  if(!conf)
  {
    *length = 0;
    return NULL;
  }

  if(conf->group_set)
  {
    *length = conf->n_groups;
    return conf->group_set;
  }

  if(conf->group_map == NULL)
  {
    *length = 0;
    return NULL;
  }

  *length = g_tree_nnodes(conf->group_map);
  conf->n_groups = *length;
  conf->group_set = g_new0(char*, *length);
  g_tree_foreach(conf->group_map, (GTraverseFunc)collect_keys, conf->group_set);

  return conf->group_set;
}

/**
 * @brief Gets the set of key names for a particular group. This returns an
 *        array of strings that the user can iterate to get all of the key's for
 *        a particular group. This is useful if the keys are not known for a
 *        particular group. The array returned by this is owned by the config
 *        library and should not be freed by the caller.
 *
 * @param group c string name of the group
 * @param length pointer allowing the number of keys to be returned
 * @return array of string containing all the key names for a group
 */
char** fo_config_key_set(fo_conf* conf, char* group, int* length)
{
  GTree* tree;
  char** ret;
  *length = 0;

  if(!conf)
    return NULL;

  if(!conf->key_sets)
    conf->key_sets = g_tree_new_full(str_comp, NULL, g_free, g_free);

  if(conf->group_map == NULL)
    return NULL;

  if((tree = g_tree_lookup(conf->group_map, group)) == NULL)
      return NULL;
  *length = g_tree_nnodes(tree);

  if((ret = g_tree_lookup(conf->key_sets, group)))
    return ret;

  ret = g_new0(char*, *length);
  g_tree_foreach(tree, (GTraverseFunc)collect_keys, ret);
  g_tree_insert(conf->key_sets, g_strdup(group), ret);

  return ret;
}

/**
 * @brief Checks if the currently parsed configuration file has a specific group
 *
 * @param group the name of the group to check for
 * @return 1 if the group exists, 0 if it does not
 */
int fo_config_has_group(fo_conf* conf, char* group)
{
  if(conf == NULL)
    return 0;
  if(!conf->group_map)
    return 0;
  return g_tree_lookup(conf->group_map, group) != NULL;
}

/**
 * @brief Checks if the a specific group in the currrently parsed configuration
 *        file has a specific key
 *
 * @param group the group to check for the key
 * @param key the key to check for
 * @return 1 if the group has the key, 0 if it does not
 */
int fo_config_has_key(fo_conf* conf, char* group, char* key)
{
  GTree* tree;

  if(conf == NULL)
    return 0;
  if(!conf->group_map)
    return 0;
  if((tree = g_tree_lookup(conf->group_map, group)) == NULL)
    return 0;
  return g_tree_lookup(tree, key) != NULL;
}

