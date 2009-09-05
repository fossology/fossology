#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <dirent.h>
#include <string.h>

#define MAX_PATHNAME 512
#define MAX_FILENAME 256
#define MAX_FILES 1000

int main (int argc, char **argv) {
    DIR *dp;
    struct dirent *ep;
    char path[MAX_FILENAME];
    char file_list[MAX_FILES][MAX_FILENAME];
    int file_count = 0;
    int i,j;

    if (argc < 2) {
        fprintf(stderr, "Please provide a directory to import reference licenses from.\n");
        exit(1);
    }

    strncpy(path, argv[1], MAX_FILENAME-2);
    path[MAX_FILENAME-2] = '\0';
    if (path[strlen(path)-1] != '/') {
        path[strlen(path)] = '/';
        path[strlen(path)+1] = '\0';
    }

    // Get a list of the directory
    dp = opendir(path);
    if (dp != NULL)
    {
        while (ep = readdir (dp)) {
            strncpy(file_list[file_count],ep->d_name,MAX_FILENAME-1);
            file_list[file_count][MAX_FILENAME-1] = '\0';
            file_count++;
        }
        (void) closedir (dp);
    } else {
        perror ("Couldn't open the directory");
    }

    // search for .meta files and their corresponding license files.
    // print an error if we cant find a license file.
    for (i = 0; i < file_count; i++) {
        char license_file[MAX_PATHNAME];
        char license_meta[MAX_PATHNAME];
        FILE *meta_fptr;
        FILE *file_fptr;
        char *meta_buffer;
        char *file_buffer;
        int len = strlen(file_list[i]);
        if (strcmp(file_list[i]+(len-5),".meta") != 0) {
            continue;
        }
        strcpy(license_meta, path);
        strcat(license_meta, file_list[i]);
        strcpy(license_file, path);
        strncat(license_file, file_list[i], (len-5));
        license_file[(len-5)] = '\0';
        printf("%s -> %s\n", license_meta, license_file);
        meta_fptr = fopen(license_meta,"rb");
        if (meta_fptr == NULL) {
            fprintf(stderr, "ERROR: Could not open %s for reading.\n", license_meta);
            continue;
        }
        fseek(meta_fptr, 0, SEEK_END);
        len = ftell(meta_fptr);
        rewind(meta_fptr);
        meta_buffer = malloc(len+1);
        if (meta_buffer == NULL) {
            fprintf(stderr, "Memory error.\n");
            continue;
        }
        j = fread(meta_buffer, 1, len, meta_fptr);
        if (j != len) {
            fprintf(stderr, "Reading error");
            continue;
        }
        meta_buffer[len] = '\0';
        
        free(meta_buffer);
    }

    return 0;
}

