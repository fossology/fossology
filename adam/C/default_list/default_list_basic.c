#include "default_list.h"
#include "default_list_basic.h"
#include <stdlib.h>
#include <string.h>

/*
   Functions for an integer list
*/
int default_list_type_int(void) {
    default_list_type_int_init();
    return default_list_type_id_by_name("int");
}

void default_list_type_int_init(void) {
    if (default_list_type_id_by_name("int") < 0) {
        default_list_register_type(
            "int",
            &default_list_type_function_int_create,
            &default_list_type_function_int_copy,
            &default_list_type_function_int_destroy,
            &default_list_type_function_int_print,
            &default_list_type_function_int_dump,
            &default_list_type_function_int_load);
    }
}

void* default_list_type_function_int_create(void *v) {
    int *temp = malloc(sizeof(int));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    *temp = 0;
    if (v != NULL) {
        *temp = *(int *)v;
    }
    return (void *)temp;
}

void* default_list_type_function_int_copy(void *v) {
    int *temp = malloc(sizeof(int));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    *temp = 0;
    if (v != NULL) {
        *temp = *(int *)v;
    }
    return (void *)temp;
}

void default_list_type_function_int_destroy(void *v) {
    free(v);
}

void default_list_type_function_int_print(void *v, FILE *f) {
    fprintf(f, "%d", *(int *)v);
}

int default_list_type_function_int_dump(void *v, FILE *f) {
    fwrite(v, sizeof(int), 1, f);
    return 0;
}

void* default_list_type_function_int_load(FILE *f) {
    int *temp = malloc(sizeof(int));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    fread(temp, sizeof(int), 1, f);
    return (void *)temp;
}

/*
   Functions for a double list
*/
int default_list_type_double(void) {
    default_list_type_double_init();
    return default_list_type_id_by_name("double");
}

void default_list_type_double_init(void) {
    if (default_list_type_id_by_name("double") < 0) {
        default_list_register_type(
            "double",
            &default_list_type_function_double_create,
            &default_list_type_function_double_copy,
            &default_list_type_function_double_destroy,
            &default_list_type_function_double_print,
            &default_list_type_function_double_dump,
            &default_list_type_function_double_load);
    }
}

void* default_list_type_function_double_create(void *v) {
    double *temp = malloc(sizeof(double));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    *temp = 0.0;
    if (v != NULL) {
        *temp = *(double *)v;
    }
    return (void *)temp;
}

void* default_list_type_function_double_copy(void *v) {
    double *temp = malloc(sizeof(double));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    *temp = 0.0;
    if (v != NULL) {
        *temp = *(double *)v;
    }
    return (void *)temp;
}

void default_list_type_function_double_destroy(void *v) {
    free(v);
}

void default_list_type_function_double_print(void *v, FILE *f) {
    fprintf(f, "%f", *(double *)v);
}

int default_list_type_function_double_dump(void *v, FILE *f) {
    fwrite(v, sizeof(double), 1, f);
    return 0;
}

void* default_list_type_function_double_load(FILE *f) {
    double *temp = malloc(sizeof(double));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    fread(temp, sizeof(double), 1, f);
    return (void *)temp;
}

/*
   Functions for a string list
*/
int default_list_type_string(void) {
    default_list_type_string_init();
    return default_list_type_id_by_name("string");
}

void default_list_type_string_init(void) {
    if (default_list_type_id_by_name("string") < 0) {
        default_list_register_type(
            "string",
            &default_list_type_function_string_create,
            &default_list_type_function_string_copy,
            &default_list_type_function_string_destroy,
            &default_list_type_function_string_print,
            &default_list_type_function_string_dump,
            &default_list_type_function_string_load);
    }
}

void* default_list_type_function_string_create(void *v) {
    char *str = v;
    char *temp = malloc(strlen(str) + 1);
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    strcpy(temp, str);
    return (void *)temp;
}

void* default_list_type_function_string_copy(void *v) {
    char *str = v;
    char *temp = malloc(strlen(str) + 1);
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    strcpy(temp, str);
    return (void *)temp;
}

void default_list_type_function_string_destroy(void *v) {
    free(v);
}

void default_list_type_function_string_print(void *v, FILE *f) {
    fprintf(f, "'%s'", (char *)v);
}

int default_list_type_function_string_dump(void *v, FILE *f) {
    char *str = v;
    int len = strlen(str) + 1;
    fwrite(&len, sizeof(int), 1, f);
    fwrite(str, 1, len, f);
    return 0;
}

void* default_list_type_function_string_load(FILE *f) {
    char *temp = NULL;
    int len = 0;

    fread(&len, sizeof(int), 1, f);
    temp = malloc(len);
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    fread(temp, 1, len, f);
    return (void *)temp;
}

