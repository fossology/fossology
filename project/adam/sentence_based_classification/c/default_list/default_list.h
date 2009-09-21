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

#ifndef _DEFAULT_LIST__h_
#define _DEFAULT_LIST__h_

#if defined(__cplusplus)
extern "C" {
#endif

#define NAME_LEN 255
#define REGISTRY_LIST_LEN 100

#include "default_list_basic.h"

    /* holds the pointers to the list */
    typedef struct default_list_internal {
        unsigned int type;
        unsigned int length;
        struct default_list_node *head;
        struct default_list_node *tail;
        struct default_list_node *current;
        unsigned int index;
    } default_list_internal;

    /* we do this so we dont have to use ** all over */
    typedef struct default_list_internal * default_list;

    typedef struct default_list_node {
        struct default_list_node *next;
        struct default_list_node *prev;
        void *data;
    } default_list_node;

    typedef struct default_list_type_registry {
        char name[NAME_LEN];
        void* (*create)(void *);
        void* (*copy)(void *);
        void (*destroy)(void *);
        void (*print)(void *, FILE *);
        int (*dump)(void *, FILE *);
        void* (*load)(FILE *);
    } default_list_type_registry;


    /* 
       This function adds a type and helper functions to the registry.
       Return a non zero value if something bad happened.
    */
    int default_list_register_type(char *name,
            void* (*create)(void *),
            void* (*copy)(void *),
            void (*destroy)(void *),
            void (*print)(void *,FILE *),
            int (*dump)(void *, FILE *),
            void* (*load)(FILE *));

    /* 
       Given a type name this function will return the type id or -1 if the name was not found.
    */
    int default_list_type_id_by_name(char *name);
    
    // Helper functions for a list of lists.
    int default_list_type_default_list(void);
    void default_list_type_default_list_init(void);
    void* default_list_type_function_default_list_create(void *v);
    void* default_list_type_function_default_list_copy(void *v);
    void default_list_type_function_default_list_destroy(void *v);
    void default_list_type_function_default_list_print(void *v, FILE *f);
    int default_list_type_function_default_list_dump(void *v, FILE *f);
    void* default_list_type_function_default_list_load(FILE *f);

    /*
        Returns the number of elements in the list.
    */
    int default_list_length(default_list list);
    
    /*
        Creates a list based on the provided type_id.
    */
    default_list default_list_create(int type);
    
    /*
        Makes a physical copy of a list.
    */
    default_list default_list_copy(default_list list);
    
    /*
        Appends a copy of the data to the list.
        Returns a non zero if an error occurred.
    */
    int default_list_append(default_list list, void *data);
    
    /*
        Removes an item from the list.
        Returns a non zero if an error occurred.
    */
    int default_list_remove(default_list list, int index);
    
    /*
        Inserts and element at the position provided.
        Returns a non zero if an error occurred.
    */
    int default_list_insert(default_list list, int pos, void *data);
    
    /*
        Prints the list to the file handler provided.
    */
    void default_list_print(default_list list, FILE *f);
    
    /*
        Cleans up the memory used by the list.
    */
    void default_list_destroy(default_list list);
    
    /*
        Writes a binary copy of the list to a file.
        Returns a non zero if an error occurred.
    */
    int default_list_dump(default_list list, FILE *f);
    
    /*
        Loads a binary copy of a list and returns a new default_list.
        Returns NULL if an error occurred.
    */
    default_list default_list_load(FILE *f);
    
    /*
        Returns the element at position i.
        Returns NULL if an error occurred.
    */
    void* default_list_get(default_list list, int pos);

#if defined(__cplusplus)
}
#endif

#endif

