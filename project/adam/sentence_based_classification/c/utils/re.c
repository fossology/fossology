#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "list.h"
#include "re.h"

int __RE__ERROR_OFFSET__ = 0;
const char *__RE_ERROR__ = NULL;

void re_print_error(int id) {
    if (id == 1) {
        printf("PCRE compilation failed at offset %d: %s\n", __RE__ERROR_OFFSET__, __RE_ERROR__);
    } else if (id < 0) {
        printf("Matching error %d\n", id);
    } else if (id == 3) {
        printf("Format Helper function returned NULL;(\n");
    } else {
        printf("Unknown Error: %d.\n", id);
    }
}

int re_compile(char *pattern, int options, cre **re) {
    *re = pcre_compile(
            pattern,                /* the pattern */
            options,                      /* default options */
            &__RE_ERROR__,          /* for error message */
            &__RE__ERROR_OFFSET__,  /* for error offset */
            NULL);                  /* use default character tables */
    if (*re == NULL) {
        return 1;
    }
    return 0;
}

void re_free(cre *re) {
    pcre_free(re);
}

int re_find_all(cre *re, char* subject, default_list **list,void* (*helpFunc)(char*, int, int)) {
    unsigned char *name_table;
    int namecount;
    int name_entry_size;
    int ovector[OVECCOUNT];
    int subject_length;
    int rc, i;
    default_list *l;

    subject_length = (int)strlen(subject);

    ovector[1] = 0;
    
    for (;;)
    {
        int options = 0;                 /* Normally no options */
        int start_offset = ovector[1];   /* Start at end of previous match */

        /* If the previous match was for an empty string, we are finished if we are
           at the end of the subject. Otherwise, arrange to run another match at the
           same point to see if a non-empty match can be found. */

        if (ovector[0] == ovector[1])
        {
            if (ovector[0] == subject_length) break;
            options = PCRE_NOTEMPTY | PCRE_ANCHORED;
        }

        /* Run the next matching operation */

        rc = pcre_exec(
                re,                   /* the compiled pattern */
                NULL,                 /* no extra data - we didn't study the pattern */
                subject,              /* the subject string */
                subject_length,       /* the length of the subject */
                start_offset,         /* starting offset in the subject */
                options,              /* options */
                ovector,              /* output vector for substring information */
                OVECCOUNT);           /* number of elements in the output vector */

        if (rc == PCRE_ERROR_NOMATCH)
        {
            if (options == 0) break;
            ovector[1] = start_offset + 1;
            continue;    /* Go round the loop again */
        }

        /* Other matching errors are not recoverable. */

        if (rc < 0)
        {
            return rc;
        }

        /* The match succeeded, but the output vector wasn't big enough. */

        if (rc == 0)
        {
            rc = OVECCOUNT/3;
            printf("ovector only has room for %d captured substrings\n", rc - 1);
        }

        /* As before, show substrings stored in the output vector by number, and then
           also any named substrings. */


        void *temp = NULL;
        temp = helpFunc(subject,ovector[2*(rc-1)],ovector[2*(rc-1)+1]);
        if (temp==NULL) {
            return 3;
        }
        default_list_append(list, (void**)&temp);

    }      /* End of loop to find second and subsequent matches */

    return 0;
}
