/* **************************************************************
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
#include <radixtree.h>
#include <copyright.h>

/** max bytes to scan */
#define MAXBUF 1024*1024
/** the max length of a line in a file */
#define LINE_LENGTH 256
/** the threshold over which a match must get to be a name */
#define NAME_THRESHOLD 5

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

struct _entry_internal {
  /// the code that was identified as a copyright
  char entry[1024];
  /// the name that matched the entry identified as a copyright
  char name_match[256];
  /// the dictionary match that originally identified the entry
  char dict_match[256];
  /// the location in the file that this copyright starts
  unsigned int start_byte;
  /// the location in the file that this copyright ends
  unsigned int end_byte;
};

/*!
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
int _find_index(copyright copy, char* dest, char *buf, int bufidx,
    copy_entry entry) {
  memset(dest, '\0', sizeof(dest));

  for(; buf[bufidx]; bufidx++) {
    radix_match(copy->dict, dest, &buf[bufidx]);
    if(radix_contains(copy->dict, dest)) {
      strcpy(entry->dict_match, dest);
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
    if (ptext[idx] == '\n') return idx;
  }
  return idx;
}

/**
* @brief strips false entries out of a copyright instance
*
* entries that do not contain any dictionary or name information should be
* deleted since they are false entries. This private function will get run
* after analyze has been run.
*
* @param copy the copyright instance to check for false entries
*/
void _strip_empty_entries(copyright copy) {
  copyright_iterator iter;

  for(iter = copyright_begin(copy); iter != copyright_end(copy); iter++) {
    if(*iter == NULL || strlen(copy_entry_dict(*iter)) == 0 ||
        strlen(copy_entry_name(*iter)) == 0) {
      iter = (copyright_iterator)cvector_remove(copy->entries,
          (cvector_iterator)iter);
    }
  }
}

/**
* @brief checks to see if an possible entry contains a name
*
* Uses the name dictionary from a copyright instance to check if a possible
* entry contains a name that is in the dictionary. This will check for spaces
* before and after the name during the check to make sure it isn't a
* substring of a normal word.
*
* @return 0 if it does not contain a name, 1 otherwise
*/
void _contains_name(copyright copy, copy_entry entry, char* buf) {
  cvector matches;
  cvector_iterator iter;

  cvector_init(&matches, pointer_cvector_registry());

  radix_match_within(copy->name, matches, entry->entry);

  for(iter = cvector_begin(matches); iter != cvector_end(matches); iter++) {
    char* curr = *(char**)*iter;

    if(*(curr - 1) < '0' ||
        (*(curr - 1) > '9' && *(curr - 1) < 'A') ||
        (*(curr - 1) > 'Z' && *(curr - 1) < 'a') ||
        *(curr - 1) > 'z') {
      printf("%s\n",curr);
      radix_match(copy->name, buf, curr);
      if(*(curr + strlen(buf)) < '0' ||
              (*(curr + strlen(buf)) > '9' && *(curr + strlen(buf)) < 'A') ||
              (*(curr + strlen(buf)) > 'Z' && *(curr + strlen(buf)) < 'a') ||
              *(curr + strlen(buf)) > 'z') {
        break;
      }
    }
  }

  cvector_destroy(matches);
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

/**
* TODO doc
*
* @param to_copy
*/
void* _entry_copy(void* to_copy) {
  copy_entry cpy = (copy_entry)to_copy;
  copy_entry new = (copy_entry)calloc(1,sizeof(struct _entry_internal));

  strcpy(new->entry, cpy->entry);
  strcpy(new->dict_match, cpy->dict_match);
  strcpy(new->name_match, cpy->name_match);
  new->start_byte = cpy->start_byte;
  new->end_byte = cpy->end_byte;

  return new;
}

/**
* TODO doc
*
* @param to_destroy
*/
void  _entry_destroy(void* to_destroy) {
  free(to_destroy);
}

/**
* TODO doc
*
* @param to_print
* @param ostr
*/
void  _entry_print(void* to_print, FILE* ostr) {
  copy_entry prt = (copy_entry)to_print;
  fprintf(ostr, "%s\t%s ==>\n%s\n%d -> %d\n",
      prt->dict_match,
      prt->name_match,
      prt->entry,
      prt->start_byte,
      prt->end_byte);
}

/*!
* @brief creates a function registry for a copyright entry
*
* This function is private to the copyright class since the copyright object is
* responsible for managing all memory relating to these.
*
* @return the new function registry
*/
function_registry* _entry_cvector_registry() {
  function_registry* ret =
      (function_registry*)calloc(1, sizeof(function_registry));

  ret->name = "cvector";
  ret->copy = &_entry_copy;
  ret->destroy = &_entry_destroy;
  ret->print = &_entry_print;

  return ret;
}

/**
* @brief Initialize the data in this entry
*
* clean up the strings and numbers in an entry so that we don't get any
* accidental overlap between the entries.
*
* @param entry the entry to initialize
*/
void _entry_init(copy_entry entry) {
  memset(entry->entry, '\0', sizeof(entry->entry));
  memset(entry->dict_match, '\0', sizeof(entry->dict_match));
  memset(entry->name_match, '\0', sizeof(entry->name_match));
  entry->start_byte = 0;
  entry->end_byte = 0;
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
  cvector_init(&((*copy)->entries), _entry_cvector_registry());

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
void copyright_analyze(copyright copy, FILE* istr) {
  /* local variables */
  char buf[MAXBUF];
  int i, bufidx, bufsize;
  int beg = 0, end = 0;
  char temp[1024];
  copy_entry entry = (copy_entry)calloc(1, sizeof(struct _entry_internal));

  assert(copy);
  assert(istr);

  /* open the relevant file */
  fseek(istr, 0, SEEK_SET);

  /* clear any previous information stored in copy */
  copyright_clear(copy);

  /* read the beginning 1M from the file */
  memset(buf, '\0', sizeof(buf));
  bufsize = fread(buf, sizeof(char), sizeof(buf)/sizeof(char), istr);
  buf[bufsize-1] = 0;

  /* convert file to lower case and validate any characters */
  for (i=0; i<bufsize; i++) {
    buf[i] = tolower(buf[i]);
    if(buf[i] < 0) {
      buf[i] = 127;
    }
  }

  /* look through the whole file for something in the dictionary */
  for(bufidx = 0; bufidx < bufsize; bufidx = end+1) {
    _entry_init(entry);
    bufidx = _find_index(copy, temp, buf, bufidx, entry);
    if(bufidx < bufsize) {
      /* grab the begging and end of the match */
      beg = _find_beginning(buf, bufidx);
      end = _find_end(buf, bufidx, bufsize);

      /* copy the match into a new entry */
      memcpy(entry->entry, &buf[beg+1], end-beg);
      entry->entry[end-beg]=0;
      entry->start_byte = beg;
      entry->end_byte = end;

      memset(temp, '\0', sizeof(temp));
      _contains_name(copy, entry, temp);

      /* push the string onto the list and increment bufidx */
      if(strlen(temp)) {
        strcpy(entry->name_match, temp);
        cvector_push_back(copy->entries, entry);
      }
    }
  }

  _strip_empty_entries(copy);
  free(entry);
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

/**
* @brief gets a cvector containing the elements in the matching dictionary
*
* returns a cvector that contains all of the string contained within the
* dictionary that is used to match copyrights.
*
* @param copy the copygith to get the dictionary from
* @param dict the cvector that will contain the dictionary
*/
void copyright_dictionary(copyright copy, cvector dict) {
  radix_copy_to(copy->dict, dict);
}

/**
* @brief gets a cvector containing the elements in the name dictionary
*
* returns a cvector that contains all of the string contained within the
* dictionary that is used to match names in copyrights.
*
* @param copy the copygith to get the dictionary from
* @param name the cvector that will contain the dictionary
*/
void copyright_names(copyright copy, cvector name) {
  radix_copy_to(copy->name, name);
}

/* ************************************************************************** */
/* **** Entry Accessor Functions ******************************************** */
/* ************************************************************************** */

/**
* @brief gets the text of the copyright entry
*
* @param entry the entry to get the text from
*/
char* copy_entry_text(copy_entry entry) {
  return entry->entry;
}

/**
* @brief gets the name that is associated with this copyright entry
*
* @param entry the entry to get the name from
*/
char* copy_entry_name(copy_entry entry) {
  return entry->name_match;
}

/**
* @brief gets the dictionary entry that matches this entry
*
* @param the entry to get the dictionary entry from
*/
char* copy_entry_dict(copy_entry entry) {
  return entry->dict_match;
}

/**
* @brief gets the number of the start byte for the text of the entry
*
* @param the entry to get the start byte from
*/
unsigned int copy_entry_start(copy_entry entry) {
  return entry->start_byte;
}

/**
* @brief gets the number of the end byte for the text of the entry
*
* @param the entry to get the end byte from
*/
unsigned int copy_entry_end(copy_entry entry) {
  return entry->end_byte;
}
