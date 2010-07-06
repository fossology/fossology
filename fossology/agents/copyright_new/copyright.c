/***************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

***************************************************************/

/* std library includes */
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <assert.h>
#include <ctype.h>

/* local includes */
#include <cvector.h>
#include <radixtree.h>
#include <copyright.h>

/** max bytes to scan */
#define MAXBUF 1024*1024
/** the max length of a line in a file */
#define LINE_LENGTH 256
/** the threshold over which a match must get to be a name */
#define NAME_THRESHOLD 0

/* ************************************************************************** */
/* **** Private Members ***************************************************** */
/* ************************************************************************** */

/** the internal structure for a copyright */
struct _copyright_internal {
  /// the dictionary to search within
  radix_tree dict;
  /// the list of names to match
  radix_tree name;
  /// the set of copyright found in a particular file
  cvector entries;
};

/**
 * @brief attempts to find any word in the given dictionary in a string
 *
 * Trys to match any word in the given dictionary with a location in the string.
 * If found this will return the location of the word that was found. This
 * function will start looking at bufidx and stop if it reaches the end of the
 * string.
 *
 * @param dict the set of words to look for
 * @param buf the string to look in
 * @param bufidx the location to start looking at
 * @return the index of the word that was found
 */
int _find_index(radix_tree dict, char *buf, int bufidx)
{
  char temp[256];
  memset(temp, '\0', sizeof(temp));

  for(; buf[bufidx]; bufidx++) {
    radix_match(dict, temp, &buf[bufidx]);
    if(radix_contains(dict, temp)) {
      break;
    }
  }

  return bufidx;
}

/**
 * @brief looks for the beginning of a line in the provided string
 *
 * will look at most 50 characters back for the beginning of a line based off of
 * the provided index. If within 50 characters it does not find the beginning of
 * the line, this will return the index 50 characters before the given index.
 *
 * @param ptext the string to search within
 * @param idx the index to base the search from
 * @return the index that is the beginning of the line.
 */
int _find_beginning(char *ptext, int idx)
{
  int maxback = 50;
  int minidx = idx - maxback;

  while (idx-- && (idx > minidx))
  {
    if (ptext[idx] == '\n') return idx;
  }
  return idx;
}


/**
 * @brief looks for the end of a line in ptext
 *
 * Looks at most 200 characters after the given index in the string. If the end
 * of the sentence or line is not within 200 characters, this will return the
 * given index plus 200 characters.
 *
 * @param ptext the string to search through
 * @param idx the index to search from
 * @param bufsize the largest valid index in the string
 * @return the location of the end of the current line or sentence
 */
int _find_end(char *ptext, int idx, int bufsize)
{
  int maxchars = 200;
  int last = idx + maxchars;

  for (; (idx < bufsize) && (idx < last); idx++)
  {
    if (ptext[idx] == '.') return idx;
  }
  return idx;
}

/**
 * @brief Loads a file that is a list of words into a dictionary
 *
 * @param dict the diction to add the strings to
 * @param filename the file to grab the string from
 */
void _load_dictionary(radix_tree dict, char* filename) {
  FILE* pfile;
  char str[256];

  pfile = fopen(filename, "r");
  assert(pfile);

  while(fgets(str, LINE_LENGTH, pfile) != NULL) {
    str[strlen(str) - 1] = '\0';
    radix_insert(dict, str);
  }

  fclose(pfile);
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * the constructor for a copyright object
 *
 * @param copy the copyright object to initialize
 */
void copyright_init(copyright* copy) {
  /* call constructor for all sub objects */
  (*copy) = (copyright)calloc(1,sizeof(struct _copyright_internal));
  radix_init(&((*copy)->dict));
  radix_init(&((*copy)->name));
  cvector_init(&((*copy)->entries), string_cvector_registry());

  /* load the dictionaries */
  _load_dictionary((*copy)->dict, "copyright.dic");
  _load_dictionary((*copy)->name, "names.dic");
}

/**
 * The copy constructor for the copyright object
 *
 * @param copy the copyright instance to copy into
 * @param reference the instance to copy from
 */
void copyright_copy(copyright* copy, copyright reference) {
  (*copy) = (copyright)calloc(1, sizeof(struct _copyright_internal));
  radix_copy(&((*copy)->dict), reference->dict);
  radix_copy(&((*copy)->name), reference->name);
  cvector_copy(&((*copy)->entries), reference->entries);
}

/**
 * the destructor for a copyright object
 *
 * @param copy
 */
void copyright_destroy(copyright copy) {
  radix_destroy(copy->dict);
  radix_destroy(copy->name);
  cvector_destroy(copy->entries);
  free(copy);
}

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

/**
 * @brief clear all copyright specific information
 *
 * clears the set of matches found by any previous calls to analyze and clears
 * the file_name that will be opened if analyze is called
 *
 * @param copy the copyright object to clear
 */
void copyright_clear(copyright copy) {
  cvector_clear(copy->entries);
}

/**
 * @brief analyze a file for the copyright information
 *
 * This will open and analyze the file provided. It is important to note that
 * this function will clear any information that was previously stored in copy.
 * All information that is needed should be extracted from copy after a call to
 * analyze before calling analyze again.
 *
 * @param copy the copyright instance that will be analyzed
 * @param file_name the name of the file to be openned and analyzed
 */
void copyright_analyze_file(copyright copy, const char* file_name) {
  /* local variables */
  FILE* istr;
  char buf[MAXBUF];
  int i, bufidx, bufsize;
  int beg = 0, end = 0;
  char found[256];

  assert(copy);
  assert(file_name);

  /* open the relevant file */
  istr = fopen(file_name, "r");
  assert(istr);

  /* clear any previous information stored in copy */
  copyright_clear(copy);

  /* read the beginning 1M from the file */
  bufsize = fread(buf, sizeof(char), sizeof(buf), istr);
  buf[bufsize-1] = 0;

  /* convert file to lower case and valibate any characters */
  for (i=0; i<bufsize; i++) {
    buf[i] = tolower(buf[i]);
    if(buf[i] < 0) {
      buf[i] = 127;
    }
  }

  /* look through the whole file for something in the dictionary */
  for(bufidx = 0; bufidx < bufsize; bufidx = end+1) {
    bufidx = _find_index(copy->dict, buf, bufidx);
    if(bufidx < bufsize) {
      /* grab the begging and end of the match */
      beg = _find_beginning(buf, bufidx);
      end = _find_end(buf, bufidx, bufsize);

      /* copy the match into a new string */
      memcpy(found, &buf[beg+1], end-beg);
      found[end-beg]=0;

      /* push the string onto the list and increment bufidx */
      cvector_push_back(copy->entries, found);
    }
  }

  cvector_pop_back(copy->entries);
  fclose(istr);
}

/**
 * @brief adds a new name to the diction of names
 *
 * @param copy the copyright instance to add to
 * @param name the name to add to the copyright instance
 */
void copyright_add_name(copyright copy, const char* name) {
  radix_insert(copy->name, name);
}

/**
 * @brief adds a new entry to the search dictionary
 *
 * @param copy the copyright instance to add to
 * @param entry the string entry to add to the copyright instance
 */
void copyright_add_entry(copyright copy, const char* entry) {
  radix_insert(copy->dict, entry);
}

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

/**
 * @brief gets an iterator to the beginning of the set of matches
 *
 * gets an iterator to the beginning of the set of matches that this copyright
 * has found.
 *
 * @param copy the copyright to get the beginning of
 * @return an iterator to the beginning of the copyright's matches
 */
copyright_iterator copyright_begin(copyright copy) {
  return (copyright_iterator)cvector_begin(copy->entries);
}

/**
 * @brief gets an iterator just past the end of the set of matches
 *
 * gets an iterator just pas the end of the set of matches that this copyright
 * has found.
 *
 * @param copy the copyright to get the beginning of
 * @return an iterator just past the end of the copyright's matches
 */
copyright_iterator copyright_end(copyright copy) {
  return (copyright_iterator)cvector_end(copy->entries);
}

/**
 * @brief bounds checked access function, gets the match at index
 *
 * @param copy the copyright object to grab from
 * @param index the index to grab
 * @return the match at index
 */
char* copyright_at(copyright copy, int index) {
  return (char*)cvector_at(copy->entries, index);
}

/**
 * @brief simple access function, gets the match at index
 *
 * @param copy the copyright object to grab from
 * @param index the index to grab
 * @return the match at index
 */
char* copyright_get(copyright copy, int index) {
  return (char*)cvector_get(copy->entries, index);
}

/**
 * @brief gets the number of copyrights
 *
 * retrieves the number of copyrights that were found in the previous call to
 * copyright analyze(). If a call to copyright_analyze() has not been made this
 * will return 0.
 *
 * @param copy the relevant copyright instance
 * @return the number of copyrights
 */
int copyright_size(copyright copy) {
  return cvector_size(copy->entries);
}
