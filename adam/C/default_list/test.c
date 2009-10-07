#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "default_list.h"

int main(int argc, char **argv) {
    int i;
    default_list list = NULL;
    default_list list_of_lists = NULL;
    
    //default_list_init();
    
    list = default_list_create(default_list_type_string());
    list_of_lists = default_list_create(default_list_type_default_list());
    
    for (i = 0; i < 10; i++) {
        default_list_append(list, "blah");
    }
    
    default_list_insert(list, 4, "stuff");
    printf("%s\n\n", (char*)default_list_get(list, 4));

    default_list_print(list, stdout);
    fprintf(stdout, "\n\n\n");

    for (i = 0; i < 10; i++) {
        default_list_append(list_of_lists, list);
    }
    
    default_list_print(list_of_lists, stdout);
    fprintf(stdout, "\n");

    FILE *file = fopen("temp","w");
    if (file == NULL) {
        fprintf(stderr,"Could not open file 'temp' for writing.\n");
        exit(-1);
    }

    default_list_dump(list,file);
    default_list_dump(list_of_lists,file);

    fclose(file);

    default_list_destroy(list);
    default_list_destroy(list_of_lists);

    file = fopen("temp","r");
    if (file == NULL) {
        fprintf(stderr,"Could not open file 'temp' for reading.\n");
        exit(-1);
    }

    list = default_list_load(file);
    if (list == NULL) {
        fprintf(stderr, "Error loading list...\n");
        exit(-1);
    }
    
    list_of_lists = default_list_load(file);
    if (list_of_lists == NULL) {
        fprintf(stderr, "Error loading list...\n");
        exit(-1);
    }

    fclose(file);

    fprintf(stdout, "\n'''");
    default_list_print(list, stdout);
    fprintf(stdout, "'''\n\n\n");
    default_list_print(list_of_lists, stdout);
    fprintf(stdout, "\n");
    
    default_list_destroy(list);
    default_list_destroy(list_of_lists);

    return 0;
}
