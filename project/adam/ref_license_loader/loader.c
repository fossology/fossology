#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <dirent.h>
#include <string.h>
#include <libpq-fe.h>

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

// returns 1 if the field is true otherwise 0
int convert_bool_field(char *value) {
    if (strcmp(value,"True") == 0 || strcmp(value,"true") == 0 ||
            strcmp(value,"Yes") == 0 || strcmp(value,"yes") == 0 ||
            strcmp(value,"1") == 0)
    {
        return 1;
    }
    return 0;
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
        long lSize;
        size_t result;
        field *f = NULL;
        int errors = 0;

        if (strcmp(file_list[i]+(len-5),".meta") != 0) {
            continue;
        }

        strcpy(license_meta, path);
        strcat(license_meta, file_list[i]);
        strncpy(license_file, license_meta, strlen(license_meta)-5);
        license_file[strlen(license_meta)-5] = '\0';
       
        printf("Starting on:\n\t%s\n\t%s\n", license_meta, license_file);

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
                if (strcmp(f->key,"Date") == 0) {
                    char format[11] = "####-##-##";
                    if (strlen(f->value) == 0) {
                        data.date[0] = '\0';
                    } else {
                        if (strlen(f->value) != 10) {
                            fprintf(stderr, "WARNING: date field is incorrect format. Should be %s, trying to continue...\n", format);
                        }
                        for (j = 0; j<10; j++) {
                            if (format[j] == '#') {
                                if (f->value[j] < '0' || f->value[j] > '9') {
                                    fprintf(stderr, "Error processing Date field: Incorrect format. Should be '%s', got '%s'.\n", format, f->value);
                                    errors++;
                                    break;
                                }
                            } else {
                                if (f->value[j] != '-') {
                                    fprintf(stderr, "Error processing Date field: Incorrect format. Should be '%s', got '%s'.\n", format, f->value);
                                    errors++;
                                    break;

                                }
                            }
                        }

                        if (j < 10) {
                            continue;
                        }
                    }
                    strncpy(data.date,f->value,10);
                    data.date[10] = '\0';
                } else if (strcmp(f->key,"URL") == 0) {
                    strcpy(data.URL,f->value);
                } else if (strcmp(f->key,"shortname") == 0) {
                    strcpy(data.shortname,f->value);
                } else if (strcmp(f->key,"fullname") == 0) {
                    strcpy(data.fullname,f->value);
                } else if (strcmp(f->key,"OSIapproved") == 0) {
                    if (convert_bool_field(f->value)==1) {
                        strcpy(data.OSIapproved,"1");
                    } else {
                        strcpy(data.OSIapproved,"0");
                    }
                } else if (strcmp(f->key,"FSFfree") == 0) {
                    if (convert_bool_field(f->value)==1) {
                        strcpy(data.FSFfree,"1");
                    } else {
                        strcpy(data.FSFfree,"0");
                    }
                } else if (strcmp(f->key,"GPLv2compatible") == 0) {
                    if (convert_bool_field(f->value)==1) {
                        strcpy(data.GPLv2compatible,"1");
                    } else {
                        strcpy(data.GPLv2compatible,"0");
                    }
                } else if (strcmp(f->key,"GPLv3compatible") == 0) {
                    if (convert_bool_field(f->value)==1) {
                        strcpy(data.GPLv3compatible,"1");
                    } else {
                        strcpy(data.GPLv3compatible,"0");
                    }
                } else if (strcmp(f->key,"copyleft") == 0) {
                    if (convert_bool_field(f->value)==1) {
                        strcpy(data.copyleft,"1");
                    } else {
                        strcpy(data.copyleft,"0");
                    }
                } else if (strcmp(f->key,"Fedora") == 0) {
                    strcpy(data.Fedora,f->value);
                } else if (strcmp(f->key,"notes") == 0) {
                    strcpy(data.notes,f->value);
                } else {
                    fprintf(stderr, "Unknown META field in %s\n\t%s\n", license_meta, f->key);
                    errors++;
                }
            }
        }

        file_fptr = fopen(license_file, "rb");
        if (file_fptr==NULL) {
            fprintf(stderr, "File error, opening %s.\n", license_file);
            exit(1);
        }

        fseek(file_fptr, 0, SEEK_END);
        lSize = ftell(file_fptr);
        rewind(file_fptr);

        file_buffer = malloc(lSize+1);
        if (file_buffer == NULL) {
            fputs("Memory error.\n",stderr);
            exit(2);
        }

        result = fread(file_buffer, 1, lSize, file_fptr);
        if (result != lSize) {
            fputs("Reading error",stderr);
            exit(3);
        }

        file_buffer[result] = '\0';
        fclose(file_fptr);

        if (errors > 0) {
            fprintf(stderr, "Not writing data to database due to errors.\n");
        } else {
            char *conninfo = "dbname = 'fossology' user = 'fossy' password = 'fossy'";
            PGconn     *conn;
            PGresult   *res;
            const char *paramValues[12];

            char sql_text[] = "INSERT INTO \"public\".\"license_ref\" (\"rf_pk\", \"rf_shortname\", \"rf_text\", \"rf_url\", \"rf_add_date\", \"rf_copyleft\", \"rf_OSIapproved\", \"rf_fullname\", \"rf_FSFfree\", \"rf_GPLv2compatible\", \"rf_GPLv3compatible\", \"rf_notes\", \"rf_Fedora\") VALUES (nextval('license_ref_rf_pk_seq'::regclass), $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12);";

            conn = PQconnectdb(conninfo);

            if (PQstatus(conn) != CONNECTION_OK)
            {
                fprintf(stderr, "Connection to database failed: %s",
                        PQerrorMessage(conn));
            }

            paramValues[0]  = data.shortname; // rf_shortname
            paramValues[1]  = file_buffer; // rf_text
            paramValues[2]  = data.URL; // rf_url
            if (strlen(data.date)==0) {
                paramValues[3]  = NULL; // rf_add_date
            } else {
                paramValues[3]  = data.date; // rf_add_date
            }
            paramValues[4]  = data.copyleft; // rf_copyleft
            paramValues[5]  = data.OSIapproved; // rf_OSIapproved
            paramValues[6]  = data.fullname; // rf_fullname
            paramValues[7]  = data.FSFfree; // rf_FSFfree
            paramValues[8]  = data.GPLv2compatible; // rf_GPLv2compatible
            paramValues[9]  = data.GPLv3compatible; // rf_GPLv3compatible
            paramValues[10] = data.notes; // rf_notes
            paramValues[11] = data.Fedora; // rf_Fedora

            res = PQexecParams(conn,
                    sql_text,
                    12,       /* one param */
                    NULL,    /* let the backend deduce param type */
                    paramValues,
                    NULL,    /* don't need param lengths since text */
                    NULL,    /* default to all text params */
                    1);      /* ask for binary results */

            if (PQresultStatus(res) != PGRES_COMMAND_OK)
            {
                fprintf(stderr, "INSERT failed: %s\n", PQerrorMessage(conn));
                PQclear(res);
            }

            PQfinish(conn);
        }
    }

    return 0;
}

