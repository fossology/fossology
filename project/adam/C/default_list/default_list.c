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

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "default_list.h"
#include "default_list_basic.h"

static int next_list_type = 0;
default_list_type_registry default_list_type_registry_list[REGISTRY_LIST_LEN];

int default_list_register_type(char *name,
                               void* (*create)(void *),
                               void* (*copy)(void *),
                               void (*destroy)(void *),
                               void (*print)(void *, FILE *),
                               int (*dump)(void *, FILE *),
                               void* (*load)(FILE *))
{
    int type = next_list_type;
    char *tmp;

    if (type == sizeof(default_list_type_registry_list)) {
        fprintf(stderr, "Too many registered types!\n");
        exit(EXIT_FAILURE);
    }
    strncpy(default_list_type_registry_list[type].name, name, NAME_LEN);
    tmp = &default_list_type_registry_list[type].name[NAME_LEN - 1];
    if (*tmp != '\0') {
        fprintf(stderr, "WARNING: String name too long in %s\n", __func__);
        /* Terminate the string */
        *tmp = '\0';
    }
    default_list_type_registry_list[type].create = create;
    default_list_type_registry_list[type].copy = copy;
    default_list_type_registry_list[type].destroy = destroy;
    default_list_type_registry_list[type].print = print;
    default_list_type_registry_list[type].dump = dump;
    default_list_type_registry_list[type].load = load;

    next_list_type++;

    return type;
}

int default_list_type_id_by_name(char *name) {
    int i = 0;
    for (i = 0; i < next_list_type; i++) {
        if (strncmp(name, default_list_type_registry_list[i].name,
                    NAME_LEN) == 0) {
            return i;
        }
    }
    return -1;
}

int default_list_type_default_list(void) {
    default_list_type_default_list_init();
    return default_list_type_id_by_name("default_list");
}

void default_list_type_default_list_init(void) {
    if (default_list_type_id_by_name("default_list") < 0) {
        default_list_register_type(
            "default_list",
            &default_list_type_function_default_list_create,
            &default_list_type_function_default_list_copy,
            &default_list_type_function_default_list_destroy,
            &default_list_type_function_default_list_print,
            &default_list_type_function_default_list_dump,
            &default_list_type_function_default_list_load);
    }
}

void* default_list_type_function_default_list_create(void *v) {
    default_list list = default_list_create(*(int *)v);
    return (void *)list;
}

void* default_list_type_function_default_list_copy(void *v) {
    default_list list = default_list_copy((default_list)v);
    return (void *)list;
}

void default_list_type_function_default_list_destroy(void *v) {
    default_list_destroy((default_list)v);
}

void default_list_type_function_default_list_print(void *v, FILE *f) {
    default_list_print((default_list)v, f);
}

int default_list_type_function_default_list_dump(void *v, FILE *f) {
    return default_list_dump((default_list)v, f);
}

void* default_list_type_function_default_list_load(FILE *f) {
    return (void *)default_list_load(f);
}

/* some helper functions for moving the current pointer */

int default_list_move_current_left(default_list list) {
    if (list->current == NULL) {
        list->current = list->head;
        list->index = 0;
    } else if (list->index == 0) {
        list->current = list->tail;
        list->index = list->length - 1;
    } else {
        list->current = list->current->prev;
        list->index--;
    }
    return list->index;
}

int default_list_move_current_right(default_list list) {
    if (list->current == NULL) {
        list->current = list->head;
        list->index = 0;
    } else if (list->index == list->length - 1) {
        list->current = list->head;
        list->index = 0;
    } else {
        list->current = list->current->next;
        list->index++;
    }
    return list->index;
}

/* the work horse functions. */
int default_list_length(default_list list) {
    return list->length;
}

default_list default_list_create(int type) {
    default_list list = NULL;
    list = malloc(sizeof(default_list_internal));
    if (list == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    list->type = type;
    list->length = 0;
    list->head = NULL;
    list->tail = NULL;
    list->current = NULL;
    list->index = 0;

    return list;
}

default_list default_list_copy(default_list list) {
    default_list copy = NULL;
    default_list_node *a = NULL;
    if (list == NULL) {
        return NULL;
    }
    copy = malloc(sizeof(default_list_internal));
    if (copy == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    copy->type = list->type;
    copy->length = 0;
    copy->head = NULL;
    copy->tail = NULL;
    copy->current = NULL;
    copy->index = 0;

    a = list->head;
    while (a != NULL) {
        int ret = default_list_append(copy, a->data);
        if (ret != 0) {
            default_list_destroy(copy);
            return NULL;
        }
        a = a->next;
    }

    return copy;
}

int default_list_append(default_list list, void *data) {
    default_list_node *a = malloc(sizeof(default_list_node));
    if (a == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return -1;
    }
    a->data = default_list_type_registry_list[list->type].copy(data);
    if (a->data == NULL) {
        return -1;
    }
    
    if (list->head == NULL) {
        a->next = NULL;
        a->prev = NULL;
        list->tail = a;
        list->head = a;
    } else {
        a->next = NULL;
        a->prev = list->tail;
        a->prev->next = a;
        list->tail = a;
    }

    list->length++;
    return 0;
}

int default_list_remove(default_list list, int pos) {
    default_list_node *a = NULL;
    
    if (list->length == 0) {
        return -1;
    }

    if (pos >= list->length || pos < 0) {
        return 1;
    }

    if (abs(pos - list->index) > abs(list->index - pos)) {
        while(default_list_move_current_right(list) != pos);        
    } else {
        while(default_list_move_current_left(list) != pos);
    }

    a = list->current;

    if (a->prev != NULL) {
        a->prev->next = a->next;
    } else {
        list->head = a->next;
        if (a->next != NULL) {
            a->next->prev = NULL;
        }
    }
    if (a->next != NULL) {
        a->next->prev = a->prev;
    } else {
        list->tail = a->prev;
        if (a->prev != NULL) {
            a->prev->next = NULL;
        }
    }

    list->current = list->head;

    default_list_type_registry_list[list->type].destroy(a->data);
    free(a);

    list->length--;
    
    return 0;
}
int default_list_insert(default_list list, int pos, void *data) {
    default_list_node *a = NULL;
    default_list_node *b = NULL;
    int i;
    if (pos > list->length) {
        return -1;
    }
    a = malloc(sizeof(default_list_node));
    if (a == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return -1;
    }
    a->data = default_list_type_registry_list[list->type].copy(data);
    if (a->data == NULL) {
        free(a);
        return -1;
    }

    b = list->head;
    for (i = 0; i < pos; i++) {
        b = b->next;
    }
    
    if (list->head == NULL) {
        a->next = NULL;
        a->prev = NULL;
        list->tail = a;
        list->head = a;
    } else if (pos == 0) {
        list->head = a;
        a->next = b;
        a->prev = NULL;
        b->prev = a;
    } else {
        a->next = b;
        a->prev = b->prev;
        a->prev->next = a;
        a->next->prev = a;
    }

    list->length++;
    return 0;
}


void default_list_print(default_list list, FILE *f) {
    fprintf(f, "( ");
    default_list_node *a = list->head;
    while(a != NULL) {
        default_list_type_registry_list[list->type].print(a->data, f);
        fprintf(f, ", ");
        a = a->next;
    }
    fprintf(f, ")");
}

void default_list_destroy(default_list list) {
    default_list_node *a = list->head;

    while(a != NULL) {
        default_list_node *next = a->next;
        default_list_type_registry_list[list->type].destroy(a->data);
        free(a);
        a = next;
    }

    free(list);
}

int default_list_dump(default_list list, FILE *f) {
    default_list_node *a = list->head;
    int temp = strlen(default_list_type_registry_list[list->type].name) + 1;
    fwrite(&temp, sizeof(int), 1, f);
    fwrite(default_list_type_registry_list[list->type].name, 1, temp, f);
    fwrite(&list->length,sizeof(int),1,f);
    while(a != NULL) {
        int ret = default_list_type_registry_list[list->type].dump(a->data,f);
        if (ret != 0) {
            return ret;
        }
        a = a->next;
    }
    return 0;
}

default_list default_list_load(FILE *f) {
    int temp = 0;
    int i;
    char *name;
    default_list list = NULL;
    void *v = NULL;

    fread(&temp, sizeof(int), 1, f);
    if (temp > NAME_LEN) {
        fprintf(stderr, "WARNING: Given name is longer than allowed!\n");
    }
    name = malloc(temp);

    fread(name, 1, temp, f);
    temp = default_list_type_id_by_name(name);
    free(name);

    if (temp < 0) {
        fprintf(stderr, "Type %s was not registered before loading.\n", name);
        exit(-1);
    }
    list = default_list_create(temp);
    fread(&temp, sizeof(int), 1, f);
    for (i = 0; i < temp; i++) {
        v = default_list_type_registry_list[list->type].load(f);
        if (v == NULL) {
            default_list_destroy(list);
            return NULL;
        }
        default_list_append(list, v);
    }
    return list;
}

void* default_list_get(default_list list, int pos) {
    default_list_node *b = NULL;
    if (pos > list->length) {
        return NULL;
    }

    if (abs(pos - list->index) > abs(list->index - pos)) {
        while(default_list_move_current_right(list) != pos);        
    } else {
        while(default_list_move_current_left(list) != pos);
    }
    b = list->current;
    return b->data;
}
