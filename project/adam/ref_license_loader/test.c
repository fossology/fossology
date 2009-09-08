#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <dirent.h>
#include <string.h>

#define MAX_PATHNAME 512
#define MAX_FILENAME 256
#define MAX_FILES 1000
#define MAX_LINE 1000
#define MAX_FIELD 32

typedef struct meta_data {
    char date[MAX_LINE+1];
    char URL[MAX_LINE+1];
    char shortname[MAX_LINE+1];
    char fullname[MAX_LINE+1];
    char OSIapproved[MAX_LINE+1];
    char FSFfree[MAX_LINE+1];
    char GPLv2compatible[MAX_LINE+1];
    char GPLv3compatible[MAX_LINE+1];
    char copyleft[MAX_LINE+1];
    char Fedora[MAX_LINE+1];
    char notes[MAX_LINE+1];
} meta_data;

typedef struct field {
    char key[MAX_FIELD+1];
    char value[MAX_LINE+1];
} field;

// reads a field and it value from a file
// a field should look like this:
// key: value\n
field* read_field(FILE *fptr) {
    field *f = malloc(sizeof(field));
    int n = 0;
    int c;

    if (f==NULL) {
        free(f);
        return NULL;
    }

    n = 0;
    while ((c = getc(fptr)) != EOF && n<MAX_FIELD) {
        f->key[n] = c;
        if (c == ':') {
            f->key[n] = '\0';
            break;
        }
        n++;
    }
    if (n >= MAX_FIELD) {
        f->key[MAX_FIELD] = '\0';
        fprintf(stderr, "READ ERROR: %d character without reaching a ':'. Stopping read process. Got '%s'.\n", MAX_FIELD, f->key);
        free(f);
        return NULL;
    }
    if (c == EOF) {
        free(f);
        return NULL;
    }

    n = 0;
    while ((c = getc(fptr)) != EOF && n<MAX_LINE) {
        if (c == ' ') {
            continue;
        }
        f->value[n] = c;
        if (c == '\n') {
            f->value[n] = '\0';
            break;
        }
        n++;
    }
    if (n >= MAX_LINE) {
        f->value[MAX_LINE] = '\0';
        fprintf(stderr, "READ ERROR: %d character without reaching a '\n'. Stopping read process. Got '%s'.\n", MAX_LINE, f->value);
        free(f);
        return NULL;
    }
    if (c == EOF) {
        f->value[n] = '\0';
    }

    return f;
}

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
        meta_data data;
        FILE *meta_fptr;
        FILE *file_fptr;
        char *file_buffer;
        int len = strlen(file_list[i]);
        field *f = NULL;

        if (strcmp(file_list[i]+(len-5),".meta") != 0) {
            continue;
        }

        strcpy(license_meta, path);
        strcat(license_meta, file_list[i]);
        
        meta_fptr = fopen(license_meta,"rb");
        if (meta_fptr == NULL) {
            fprintf(stderr, "ERROR: Could not open %s for reading.\n", license_meta);
            continue;
        }
        
        while (1) {
            f = read_field(meta_fptr);
            if (f == NULL) {
                break;
            } else {
                printf("%s: %s\n", f->key, f->value);
                if (strcmp(f->key,"Date") == 0) {
                    strcpy(data.date,f->value);
                } else if (strcmp(f->key,"URL") == 0) {
                    strcpy(data.URL,f->value);
                } else if (strcmp(f->key,"shortname") == 0) {
                    strcpy(data.shortname,f->value);
                } else if (strcmp(f->key,"fullname") == 0) {
                    strcpy(data.fullname,f->value);
                } else if (strcmp(f->key,"OSIapproved") == 0) {
                    strcpy(data.OSIapproved,f->value);
                } else if (strcmp(f->key,"FSFfree") == 0) {
                    strcpy(data.FSFfree,f->value);
                } else if (strcmp(f->key,"GPLv2compatible") == 0) {
                    strcpy(data.GPLv2compatible,f->value);
                } else if (strcmp(f->key,"GPLv3compatible") == 0) {
                    strcpy(data.GPLv3compatible,f->value);
                } else if (strcmp(f->key,"copyleft") == 0) {
                    strcpy(data.copyleft,f->value);
                } else if (strcmp(f->key,"Fedora") == 0) {
                    strcpy(data.Fedora,f->value);
                } else if (strcmp(f->key,"notes") == 0) {
                    strcpy(data.notes,f->value);
                } else {
                    fprintf(stderr, "Unknown META field in %s\n\t%s\n", license_meta, f->key);
                }
            }
        }
    }

    return 0;
}

