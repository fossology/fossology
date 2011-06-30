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

#include <fossconfig.h>

#include <ctype.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include <glib.h>

/* ************************************************************************** */
/* *** utility ************************************************************** */
/* ************************************************************************** */

GTree* group_map;
GTree* current_group;
char** group_set;
GTree* key_sets;

/**
 * A wrapper function for the strcmp function that allows it to mascarade as a
 * GCompareDataFunc
 *
 * @param a c string to be compared
 * @param b c string to be compare
 * @param user_data not used
 * @return an integral value indicating the relationship between strings
 */
gint str_comp(gconstpointer a, gconstpointer b, gpointer user_data)
{
  return strcmp((char*)a, (char*)b);
}

/**
 * Function that collects all of the keys for a GTree into a single array of
 * strings. This is used to grab the sets of keys for the fo_config_key_set and
 * fo_config_group_set functions
 *
 * @param key the key for this particular key/value pair
 * @param value the value for this particular key/value pair
 * @param data not used
 * @return always return 0 so that the traversal continues
 */
gboolean collect_keys(char* key, gpointer* value, char** data)
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

FILE* yyin;
char lex[1024];
int lex_idx;
int yyline, yyposs;

#define FOSS_CONF "fossology.conf"
#define yynext() (c = next()) != EOF
#define throw_error(error, domain, code, ...) \
    { g_set_error(error, domain, code, __VA_ARGS__); \
    return 0; }

/**
 * Gets the next character from the input file. This function maintains yyline
 * and yyposs.
 *
 * @return the character
 */
int next()
{
  static int c = '\0';

  if(c == '\n')
  {
    yyline++;
    yyposs = 0;
  }

  c = fgetc(yyin);
  lex[lex_idx++] = c;
  yyposs++;

  return c;
}

/**
 * Returns a character to the input file.
 *
 * @param c the character to put back into the stream
 */
int replace(int c)
{
  lex[--lex_idx] = '\0';
  if(ungetc(c, yyin) == EOF)
    return 0;

  if(yyposs == 0)
    yyline--;
  else
    yyposs--;

  return 1;
}

/**
 * gets the string from the current location in file to the end of the line or
 * the end of file is reached.
 *
 * @param dst the location for storing the string
 * @return 0 for invalid data in dst, 1 for valid data
 */
int next_nl()
{
  int c;

  while(yynext() && c != '\n');
  lex[--lex_idx] = '\0';

  return 1;
}

/**
 * gets all characters between the current location and the next non-white sapce
 * character.
 *
 * @return the next non-whitespace character
 */
int next_nws()
{
  int c;

  while(yynext() && isspace(c));
  lex_idx = 0;
  memset(lex, '\0', sizeof(lex));
  lex[lex_idx++] = c;

  return c;
}

/**
 * Parses a group from the input file. This will be called when a '[' appears at
 * the start of a line. The line is then parsed and it expects to find a ']' at
 * the end of the line. If the line does not end in a ']' then it is an error.
 *
 * @param error object that allows errors to be passed out of the parser
 * @return 1 if the parse was successful, 0 otherwise
 */
int group(GError** error)
{
  gchar* key;

  lex[0] = '\0';
  lex_idx = 0;

  next_nl();

  if(lex[lex_idx - 1] != ']')
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_group,
        "%s[%d.%d]: invalid group name, group names end in ']'",
        FOSS_CONF, yyline, yyposs);

  lex[--lex_idx] = '\0';
  key = g_strdup(lex);

  current_group = g_tree_new_full(str_comp, NULL, g_free, g_free);
  g_tree_insert(group_map, key, current_group);

  return 1;
}

/**
 * reads a key from the input file. This will first read the name of the key,
 * then check for the '=' delimiter and finally read to a new line and record
 * the key/value pair. If the key is an array key (i.e. key ends with []) then
 * the value string will be appended to.
 *
 * @param error GError object allowing errors to be created
 * @return 0 of fail, 1 of success
 */
int key(GError** error) {
  int c;
  gchar* key;
  gchar* tmp;
  gchar* val;
  int    len;

  while(yynext() && c != '=' && c != '\n' && !isspace(c));
  replace(c);
  key = g_strdup(lex);

  while(yynext() && c != '=' && c != '\n' && isspace(c));
  if(c != '=')
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[%d.%d] invalid key/value expression \"%s\"",
        FOSS_CONF, yyline, yyposs, key);

  len = strlen(key);
  if(key[len - 1] == ']' && key[len - 2] != '[')
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[%d.%d] invalid key/value expression \"%s\"",
        FOSS_CONF, yyline, yyposs, key);

  next_nws();
  next_nl();

  if(key[len - 1] == ']')
  {
    key[len - 2] = '\0';
    val = g_tree_lookup(current_group, key);
    if(val)
    {
      tmp = g_strdup_printf("%s[%s]", val, lex);
      g_tree_insert(current_group, key, tmp);
    }
    else
    {
      tmp = g_strdup_printf("[%s]", lex);
      g_tree_insert(current_group, key, tmp);
    }
  }
  else
  {
    val = g_strdup(lex);
    g_tree_insert(current_group, key, val);
  }

  return 1;
}

/* ************************************************************************** */
/* *** public interface ***************************************************** */
/* ************************************************************************** */

/**
 * load the configuration information from the fossology.conf file. If the user
 * has not done a fo_config_free since the last fo_config_load, this will make
 * sure to call that first. In other words, it is assumed that if this is called
 * the config file has changed and the user would like to use the new copy.
 *
 * @param error object that allows errors to propagate up the stack
 * @return 0 for failure, 1 for success
 */
int fo_config_load(GError** error) {
  gchar fname[FILENAME_MAX];
  char lexeme[1024];
  int c;

  g_snprintf(fname, sizeof(fname), "%s/%s", DEFAULT_SETUP, FOSS_CONF);
  if((yyin = fopen(fname, "r")) == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_missing_file,
        "unable to open config file \"%s\"",
        fname);

  if(group_map)
    fo_config_free();

  memset(lexeme, '\0', sizeof(lexeme));
  group_map = g_tree_new_full(str_comp, NULL, g_free,
      (GDestroyNotify)g_tree_destroy);
  yyline = 1;
  yyposs = 0;

  while((c = next_nws()) != EOF)
  {
    lex[0] = c;
    lex_idx = 1;

    switch(c)
    {
      case ';': c = next_nl(); break;
      case '[': c = group(error); break;
      default:  c = key(error); break;
    }

    if(!c || c == EOF)
      return 0;

    memset(lex, '\0', sizeof(lex));
  }

  fclose(yyin);
  return 1;
}

/**
 * Gets an element based on its group name and key name. If the group or key is
 * not found, the error object is set and NULL is returned.
 *
 * @param group c string that is the name of the group
 * @param key c string that is the name of the key for the key/value pair
 * @return the c string representation of the value
 */
char* fo_config_get(char* group, char* key, GError** error)
{
  GTree* tree;

  if(group_map == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_load_config,
        "ERROR: you must call fo_config_load before any other calls");

  if((tree = g_tree_lookup(group_map, group)) == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_missing_group,
        "ERROR: unknown group \"%s\"", group);

  return g_tree_lookup(tree, key);
}

/**
 * Keys can be associated with multiple values. If this is the case for a
 * particular key, use this function instead of fo_config_get. This also takes
 * the index of the element in the list. Index work identically to standard
 * c-array indices. It is important to note event though keys will appear as
 * "key[]" in the config file this function just takes "key" as the key
 *
 * @param group c string that is the name of the group
 * @param key c string that is the name of the key for the key/value pair
 * @param idx the index of the value in the value list
 * @param error object that allows errors to propagate up the stack
 * @return c string representation of the value, once returned the caller owns
 *         this pointer. make sure to call g_free on it
 */
char* fo_config_get_list(char* group, char* key, int idx, GError** error)
{
  char* val;
  int depth;
  char* curr;

  if(group_map == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_load_config,
        "ERROR: you must call fo_config_load before any other calls\n");

  if(!fo_config_is_list(group, key, error))
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_group,
        "ERROR: %s[%s] must be of type list to get length", group, key);
  if(*error)
    return NULL;

  val = g_tree_lookup(
      g_tree_lookup(group_map, group), key);

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
 * Checks if a particular value is a list or just a normal value.
 *
 * @param group c string name of the group
 * @param key c string name of the key
 * @return 0 if it isn't a list, 1 if it is
 */
int fo_config_is_list(char* group, char* key, GError** error)
{
  GTree* tree;
  char* val;

  if(group_map == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_load_config,
        "ERROR: you must call fo_config_load before any other calls");

  if((tree = g_tree_lookup(group_map, group)) == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_missing_group,
        "ERROR: unknown group \"%s\"", group);

  if((val = g_tree_lookup(tree, key)) == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_missing_key,
        "ERROR: unknown key/value expression \"%s\"", key);

  return val[0] == '[';
}

/**
 * gets the length of the list associated with a particular list key
 *
 * @param group c string name of the group
 * @param key c string name of the key
 * @return the number of elements in the list, on error returns 0
 */
int fo_config_list_length(char* group, char* key, GError** error)
{
  char* val;
  char* curr;
  int count = 0;

  if(!fo_config_is_list(group, key, error))
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_group,
        "ERROR: %s[%s] must be of type list to get length", group, key);
  if(*error)
    return 0;

  val = g_tree_lookup(
      g_tree_lookup(group_map, group), key);

  for(curr = val; *curr; curr++)
    if(*curr == '[')
      count++;

  return count;
}

/**
 * Frees the memory associated with the internal configuration data structures.
 */
void fo_config_free()
{
  if(group_map) g_tree_destroy(group_map);
  if(key_sets)  g_tree_destroy(key_sets);
  if(group_set) g_free(group_set);

  group_map = NULL;
  key_sets = NULL;
  group_set = NULL;
}

/* ************************************************************************** */
/* *** special interface **************************************************** */
/* ************************************************************************** */

/**
 * Gets the set of group names. This returns an array of strings that the user
 * can iterate to get all group names. The user does not own the return of this
 * function and should not free any of the memory associated with it.
 *
 * @param length pointer allowing the number of groups to be returned
 * @return array of strings containing all the group names
 */
char** fo_config_group_set(int* length)
{
  if(group_set)
    return group_set;

  if(group_map == NULL)
  {
    *length = 0;
    return NULL;
  }

  *length = g_tree_nnodes(group_map);
  group_set = g_new0(char*, *length);
  g_tree_foreach(group_map, (GTraverseFunc)collect_keys, group_set);

  return group_set;
}

/**
 * Gets the set of key names for a particular group. This returns an array of
 * strings that the user can iterate to get all of the key's for a particular
 * group. This is useful if the keys are not known for a particular group. The
 * array returned by this is owned by the config library and should not be freed
 * by the caller.
 *
 * @param group c string name of the group
 * @param length pointer allowing the number of keys to be returned
 * @return array of string containing all the key names for a group
 */
char** fo_config_key_set(char* group, int* length)
{
  GTree* tree;
  char** ret;

  if(!key_sets)
    key_sets = g_tree_new_full(str_comp, NULL, g_free, g_free);

  if(group_map == NULL)
    return NULL;

  if((ret = g_tree_lookup(key_sets, group)))
    return ret;

  if((tree = g_tree_lookup(group_map, group)) == NULL)
    return NULL;

  *length = g_tree_nnodes(tree);
  ret = g_new0(char*, *length);
  g_tree_foreach(tree, (GTraverseFunc)collect_keys, ret);
  g_tree_insert(key_sets, g_strdup(group), ret);

  return ret;
}
