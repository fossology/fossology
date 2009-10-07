#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <libstemmer.h>
#include "token_feature.h"

/*
   Functions for a token_feature list
*/

struct sb_stemmer * stemmer = NULL;

int char_count(char *str, char c) {
    char *char_ptr;
    int count = 0;
    for (char_ptr = str; *char_ptr != '\0'; char_ptr++) {
        if (c==*char_ptr) {
            count++;
        }
    }
    return count;
}

int default_list_type_token_feature(void) {
    default_list_type_token_feature_init();
    return default_list_type_id_by_name("token_feature");
}

int default_list_type_token_feature_init(void) {
    if (default_list_type_id_by_name("token_feature") < 0) {
        default_list_register_type(
            "token_feature",
            &default_list_type_function_token_feature_create,
            &default_list_type_function_token_feature_copy,
            &default_list_type_function_token_feature_destroy,
            &default_list_type_function_token_feature_print,
            &default_list_type_function_token_feature_dump,
            &default_list_type_function_token_feature_load);
    }
}

void* default_list_type_function_token_feature_create(void *v) {
    int i;
    token_feature *tf = v;
    token_feature *temp = malloc(sizeof(token_feature));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }

    if (tf != NULL) {
        temp->stemmed = malloc(strlen(tf->stemmed)+1);
        if (temp->stemmed == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp);
            return NULL;
        }
        strcpy(temp->stemmed,tf->stemmed);
        temp->string = malloc(strlen(tf->string)+1);
        if (temp->string == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp->stemmed);
            free(temp);
            return NULL;
        }
        strcpy(temp->string,tf->string);
        temp->word = tf->word;
        temp->start = tf->start;
        temp->end = tf->end;
        temp->length = tf->length;
        temp->capped = tf->capped;
        temp->upper = tf->upper;
        temp->number = tf->number;
        temp->incnum = tf->incnum;
        for (i = 0; i < FT_CHAR_MAP_LEN; i++) {
            temp->char_vector[i] = tf->char_vector[i];
        }
    } else {
        temp->string = NULL;
        temp->stemmed = NULL;
        temp->start = 0;
        temp->end = 0;
        temp->length = 0;
        temp->word = FALSE;
        temp->capped = FALSE;
        temp->upper = FALSE;
        temp->number = FALSE;
        temp->incnum = FALSE;
        for (i = 0; i < FT_CHAR_MAP_LEN; i++) {
            temp->char_vector[i] = 0;
        }
    }

    return (void *)temp;
}

void* default_list_type_function_token_feature_copy(void *v) {
    int i;
    token_feature *tf = v;
    token_feature *temp = malloc(sizeof(token_feature));
    if (temp == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }

    if (tf != NULL) {
        temp->stemmed = malloc(strlen(tf->stemmed)+1);
        if (temp->stemmed == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp);
            return NULL;
        }
        strcpy(temp->stemmed,tf->stemmed);
        temp->string = malloc(strlen(tf->string)+1);
        if (temp->string == NULL) {
            fprintf(stderr, "Memory error at line %d in file %s.\n",
                    __LINE__, __FILE__);
            free(temp->stemmed);
            free(temp);
            return NULL;
        }
        strcpy(temp->string,tf->string);
        temp->word = tf->word;
        temp->start = tf->start;
        temp->end = tf->end;
        temp->length = tf->length;
        temp->capped = tf->capped;
        temp->upper = tf->upper;
        temp->number = tf->number;
        temp->incnum = tf->incnum;
        for (i = 0; i < FT_CHAR_MAP_LEN; i++) {
            temp->char_vector[i] = tf->char_vector[i];
        }
    } else {
        free(temp);
        temp = NULL;
    }
    return (void *)temp;
}

void default_list_type_function_token_feature_destroy(void *v) {
    token_feature *tf = v;
    free(tf->stemmed);
    free(tf->string);
    free(v);
}

void default_list_type_function_token_feature_print(void *v, FILE *f) {
    token_feature *tf = v;
    fprintf(f, "'%s'", tf->string);
}

int default_list_type_function_token_feature_dump(void *v, FILE *f) {
    token_feature *tf = v;
    int len = strlen(tf->string) + 1;
    fwrite(&len, sizeof(int), 1, f);
    fwrite(tf->string, 1, len, f);
    len = strlen(tf->stemmed) + 1;
    fwrite(&len, sizeof(int), 1, f);
    fwrite(tf->stemmed, 1, len, f);
    fwrite(&tf->start, sizeof(int), 1, f);
    fwrite(&tf->end, sizeof(int), 1, f);
    fwrite(&tf->length, sizeof(int), 1, f);
    fwrite(&tf->word, sizeof(c_bool), 1, f);
    fwrite(&tf->capped, sizeof(c_bool), 1, f);
    fwrite(&tf->upper, sizeof(c_bool), 1, f);
    fwrite(&tf->number, sizeof(c_bool), 1, f);
    fwrite(&tf->incnum, sizeof(c_bool), 1, f);
    fwrite(tf->char_vector, sizeof(int), FT_CHAR_MAP_LEN, f);
    return 0;
}

void* default_list_type_function_token_feature_load(FILE *f) {
    token_feature *tf = malloc(sizeof(token_feature));
    if (tf == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        return NULL;
    }
    int len;
    fread(&len, sizeof(int), 1, f);
    tf->string = malloc(len);
    if (tf->string == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(tf);
        return NULL;
    }
    fread(tf->string, 1, len, f);
    fread(&len, sizeof(int), 1, f);
    tf->stemmed = malloc(len);
    if (tf->stemmed == NULL) {
        fprintf(stderr, "Memory error at line %d in file %s.\n",
                __LINE__, __FILE__);
        free(tf->string);
        free(tf);
        return NULL;
    }
    fread(tf->stemmed, 1, len, f);
    fread(&tf->start, sizeof(int), 1, f);
    fread(&tf->end, sizeof(int), 1, f);
    fread(&tf->length, sizeof(int), 1, f);
    fread(&tf->word, sizeof(c_bool), 1, f);
    fread(&tf->capped, sizeof(c_bool), 1, f);
    fread(&tf->upper, sizeof(c_bool), 1, f);
    fread(&tf->number, sizeof(c_bool), 1, f);
    fread(&tf->incnum, sizeof(c_bool), 1, f);
    fread(tf->char_vector, sizeof(int), FT_CHAR_MAP_LEN, f);
    
    return (void *)tf;
}

token_feature* token_feature_create_from_string(char *string, int start, int end) {
    int i = 0;
    token_feature *t = malloc(sizeof(token_feature));
    sb_symbol * b = (sb_symbol *) malloc(end-start * sizeof(sb_symbol));
    
    if (end<=start) {
        return NULL;
    }
    if (stemmer==NULL) {
        stemmer = sb_stemmer_new("english", NULL);
    }

    t->string = malloc((end-start)+1);
    strncpy(t->string,string+start,end-start);
    t->string[end-start] = '\0';

    t->capped = isupper(t->string[0]);
    t->upper = TRUE;
    t->number = TRUE;
    t->incnum = FALSE;
    t->word = TRUE;

    for (i = 0; i<end-start; i++) {
        t->upper = t->upper && isupper(t->string[i]);
        if (isupper(t->string[i])) {
            b[i] = tolower(t->string[i]);
        } else {
            b[i] = t->string[i];
        }
        if (('0' <= t->string[i] && t->string[i] <= '9') || ('a' <= b[i] && b[i] <= 'z')) {
            if ('0' <= t->string[i] && t->string[i] <= '9') {
                t->incnum = t->incnum || TRUE;
                t->number = t->number && TRUE;
            } else {
                t->number = FALSE;
            }
            t->word = t->word && TRUE;
        } else {
            t->number = FALSE;
            t->word = FALSE;
            t->incnum = t->incnum || FALSE;
        }
    }
    const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, end-start);
    t->stemmed = (char*)malloc(sizeof(char)*(end-start)+1);
    for (i = 0; stemmed[i] != 0; i++) {
        t->stemmed[i] = stemmed[i];
    }
    t->stemmed[i] = '\0';

    t->start = start;
    t->end = end;
    t->length = end-start;

    if (t->word==FALSE) {
        for (i=0; i<FT_CHAR_MAP_LEN; i++) {
            t->char_vector[i] = char_count(t->string,FT_CHAR_MAP[i]);
        }
    } else {
        for (i=0; i<FT_CHAR_MAP_LEN; i++) {
            t->char_vector[i] = 0;
        }        
    }
    
    return t;
}

