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

fo_conf* dest;
GTree*   current_group;

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
 * fo_config_group_set functions
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

FILE* yyin;
int   lex_idx;
char  lex[BUFFER_SIZE];
int   yyline, yyposs;
char  fname[FILENAME_MAX];

/**
 * @brief Gets the next character from the input file. This function maintains
 *        yyline and yyposs.
 *
 * @return the character
 */
static int next()
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
 * @brief Returns a character to the input file.
 *
 * @param c the character to put back into the stream
 */
static int replace(int c)
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
 * @brief gets the string from the current location in file to the end of the
 *        line or the end of file is reached.
 *
 * @param dst the location for storing the string
 * @return 0 for invalid data in dst, 1 for valid data
 */
static int next_nl()
{
  int c;

  while(yynext() && c != '\n');
  lex[--lex_idx] = '\0';

  return 1;
}

/**
 * @brief gets all characters between the current location and the next
 *        non-white space character.
 *
 * @return the next non-whitespace character
 */
static int next_nws()
{
  int c;

  while(yynext() && isspace(c));
  lex_idx = 0;
  memset(lex, '\0', sizeof(lex));
  lex[lex_idx++] = c;

  return c;
}

/**
 * @brief Parses a group from the input file. This will be called when a '['
 *        appears at the start of a line. The line is then parsed and it expects
 *        to find a ']' at the end of the line. If the line does not end in a
 *        ']' then it is an error.
 *
 * @param error object that allows errors to be passed out of the parser
 * @return 1 if the parse was successful, 0 otherwise
 */
static int group(GError** error)
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
        "%s[line %d]: invalid group name",
        fname, yyline);

  lex[--lex_idx] = '\0';
  key = g_strdup(lex);

  current_group = g_tree_new_full(str_comp, NULL, g_free, g_free);
  g_tree_insert(dest->group_map, key, current_group);

  return 1;
}

/**
 * @brief Takes the value associated with a key and substitues any other variables
 *        for the ones in the value string.
 *
 * i.e.
 * if:
 *   DUMMY = something
 * then:
 *   ECHO = something $DUMMY
 * becomes:
 *   ECHO = something something
 *
 * @param src the value to do the replacement for
 * @return a new string that the caller must free
 */
static char* sub(char* src) {
  int src_idx;
  int dst_idx;
  int dst_size;
  int src_size;
  char* dst;
  char* sub;
  char buf[256];

  src_size = strlen(src);
  dst_size = 0;

  for(src_idx = 0; src_idx < src_size; src_idx++) {
    if(src[src_idx] == '$') {
      dst_idx = 0;
      sub = NULL;

      memset(buf, '\0', sizeof(buf));
      while(src[src_idx]) {
        buf[dst_idx++] = src[++src_idx];

        if((sub = g_tree_lookup(current_group, buf)) != NULL) {
          dst_size += strlen(sub);
          break;
        }
      }
      continue;
    }

    dst_size++;
  }

  dst = g_new0(char, dst_size + 1);
  dst_idx = 0;
  for(src_idx = 0; src_idx < src_size; src_idx++) {
    if(src[src_idx] == '$') {
      dst_size = 0;
      sub = NULL;

      memset(buf, '\0', sizeof(buf));
      while(src[src_idx]) {
        buf[dst_size++] = src[++src_idx];

        if((sub = g_tree_lookup(current_group, buf)) != NULL) {
          strcpy(dst + strlen(dst), sub);
          dst_idx = strlen(dst);
          break;
        }
      }

      continue;
    }

    dst[dst_idx++] = src[src_idx];
  }

  return dst;
}

/**
 * @brief reads a key from the input file. This will first read the name of the
 *        key, then check for the '=' delimiter and finally read to a new line
 *        and record the key/value pair. If the key is an array key (i.e. key
 *        ends with []) then the value string will be appended to.
 *
 * @param error GError object allowing errors to be created
 * @return 0 of fail, 1 of success
 */
static int key(GError** error) {
  int c;
  gchar* key;
  gchar* tmp;
  gchar* val;
  int    len;

  while(yynext() && c != '=' && c != '\n' && !isspace(c));
  replace(c);
  key = g_strdup(lex);
  len = strlen(key);
  while(yynext() && c != '=' && c != '\n' && isspace(c));

  if(current_group == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[line %d]: key \"%s\" does not have an associated group",
        fname, yyline, key);

  if(c != '=')
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[line %d]: invalid key/value expression \"%s\"",
        fname, yyline, key);

  if(key[len - 1] == ']' && key[len - 2] != '[')
    throw_error(
        error,
        PARSE_ERROR,
        fo_invalid_key,
        "%s[line %d]: invalid key/value expression \"%s\"",
        fname, yyline, key);

  next_nws();
  next_nl();

  if(key[len - 1] == ']')
  {
    key[len - 2] = '\0';
    val = g_tree_lookup(current_group, key);
    if(val)
    {
      tmp = sub(lex);
      val = g_strdup_printf("%s[%s]", val, tmp);
      g_tree_insert(current_group, key, tmp);
      g_free(tmp);
    }
    else
    {
      val = sub(lex);
      tmp = g_strdup_printf("[%s]", val);
      g_tree_insert(current_group, key, tmp);
      g_free(val);
    }
  }
  else
  {
    val = sub(lex);
    g_tree_insert(current_group, key, val);
  }

  return 1;
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
  int c;

  if(rawname == NULL)
    return NULL;

  memset(fname, '\0', sizeof(fname));
  strncpy(fname, rawname, sizeof(fname));
  if((yyin = fopen(fname, "r")) == NULL)
    throw_error(
        error,
        PARSE_ERROR,
        fo_missing_file,
        "unable to open config file \"%s\"",
        fname);

  dest = g_new0(fo_conf, 1);
  dest->group_map = NULL;
  dest->key_sets = NULL;
  dest->group_set = NULL;
  dest->n_groups = 0;
  dest->group_map = g_tree_new_full(str_comp, NULL, g_free,
      (GDestroyNotify)g_tree_unref);
  yyline = 1;
  yyposs = 0;
  current_group = NULL;

  while((c = next_nws()) != EOF)
  {
    lex[0] = c;
    lex_idx = 1;

    switch(c)
    {
      case ';': c = next_nl(); break;
      case '[': c = group(error); break;
      default:
        if(isalpha(c))
          c = key(error);
        else
        {
          fo_config_free(dest);
          dest = NULL;
          throw_error(
              error,
              PARSE_ERROR,
              fo_invalid_file,
              "%s[line %d]: invalid char '%c', keys must start with alpha char",
              fname, yyline, c);
        }
        break;
    }

    if(*error)
    {
      fo_config_free(dest);
      dest = NULL;
      return NULL;
    }

    if(!c || c == EOF)
      return 0;

    memset(lex, '\0', sizeof(lex));
  }

  ret = dest;
  current_group = NULL;
  dest = NULL;
  fclose(yyin);
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

