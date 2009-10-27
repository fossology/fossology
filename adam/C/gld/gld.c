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
#include "tokenizer.h"
#include "default_list.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"
#include "file_utils.h"
#include <limits.h>
#include <sparsevect.h>
#include <math.h>
#include "config.h"
#include "hash.h"
#include <getopt.h>
#include <signal.h>


#include <libfossdb.h>       /* database functions */
#include <libfossrepo.h>     /* repository functions */
#include <libfossagent.h>    /* general agent functions (heartbeat, ReadLine, GetAgentKey, ...)*/

void	*DB=NULL;
int Agent_pk=-1;    /* agent ID */ 
#define MAXLINE	1024

/***********************************************
  Usage():
  Command line options allow you to write the agent so it works 
  stand alone, in addition to working with the scheduler.
  This simplifies code development and testing.
  So if you have options, have a Usage().
  Here are some suggested options (in addition to the program
  specific options you may already have).
 ***********************************************/
void  Usage (char *Name)
{ 
    printf("Usage: %s [options] [file [file [...]]\n",Name);
    printf("  -i   :: initialize the database, then exit.\n");
    printf("  -v   :: verbose (-vv = more verbose)\n");
    printf("  -t   :: training mode.\n");
    printf("  -m   :: name of model file. Defaults to gld.dat\n");
    printf("  file :: if files are listed, report license locations.\n");
    printf("          if in training mode files will be used for training.\n");
    printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

int build_model(int argc, char **argv, char *savefile) {
    FILE *file;
    char *buffer;
    default_list feature_type_list = NULL;
    default_list label_list = NULL;
    default_list list = NULL;
    token_feature *ft = NULL;
    token *t = NULL;
    int i, j, k, c;
    char lics_re[] = "<LICENSE_SECTION>(?P<text>.*?)</LICENSE_SECTION>";
    cre *re;

    sv_vector pos_vect = sv_new(ULONG_MAX);
    sv_vector neg_vect = sv_new(ULONG_MAX);

    re_compile(lics_re,RE_DOTALL,&re);

    for (i = 1; i < argc; i++) {
        printf("Working on %s...\n", argv[i]);
        
        buffer = NULL;
        label_list = default_list_create(default_list_type_string());
        list = default_list_create(default_list_type_token());

        readtomax(argv[i],&buffer,64000);

        re_find_all(re,buffer,list,&token_create_from_string);

        printf("\tfound %d licenses sections...\n", default_list_length(list));
        for (j = 0; j < default_list_length(list); j++) {
            t = default_list_get(list,j);
            for (k = t->start-17; k < t->end+18; k++) {
                buffer[k] = ' ';
            }
            
            feature_type_list = default_list_create(default_list_type_token_feature());
            create_features_from_buffer(t->string,feature_type_list);
            c = 0;
            for (k = 0; k < default_list_length(feature_type_list); k++) {
                double v = 0;
                unsigned long int index = 0;
                ft = default_list_get(feature_type_list,k);
                if (ft->word && ft->length > 1) {
                    c++;
                    index = sdbm(ft->stemmed);
                    v = sv_get_element_value(pos_vect,index);
                    sv_set_element(pos_vect,index,v+1.0);
                }
                if (c%100 == 0) { printf("."); }
            }
            default_list_destroy(feature_type_list);
        }
        printf("\n");

        feature_type_list = default_list_create(default_list_type_token_feature());
        create_features_from_buffer(buffer,feature_type_list);
        c = 0;
        for (k = 0; k < default_list_length(feature_type_list); k++) {
            double v = 0;
            unsigned long int index = 0;
            ft = default_list_get(feature_type_list,k);
            if (ft->word && ft->length > 1) {
                c++;
                index = sdbm(ft->stemmed);
                v = sv_get_element_value(pos_vect,index);
                sv_set_element(pos_vect,index,v+1.0);
            }
            if (c%100 == 0) { printf("."); }
        }
        default_list_destroy(feature_type_list);
        printf("\n");

        free(buffer);
        default_list_destroy(label_list);
        default_list_destroy(list);
    }

    re_free(re);
    
    file = fopen("gld.dat", "w");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }
    
    sv_dump(pos_vect,file);
    sv_dump(neg_vect,file);

    fclose(file);

    return 0;
}


int main(int argc, char **argv) {
    int arg;
    char Parm[MAXLINE];
    char *Path;
    int c;
    char *agent_desc = "Pulls metadata out of RPM .spec files";

    /* I'm going to write to the database, so open it.  DBopen() 
     * is in libfossdb and knows how to access Db.conf */
    DB = DBopen();
    if (!DB)
    {
        /* Note: writing the FATAL message below to stdout
         * does exactly what you expect (writes to stdout).
         * However, if the scheduler spawned this agent, then
         * FATAL (also ERROR, WARNING, and LOG) messages are also
         * written to the fossology log file.  Hint, the scheduler
         * is reading stdout.  */
        printf("FATAL: Unable to connect to database\n");
        fflush(stdout);
        exit(-1);
    }


    /* Data written to the DB needs to be tagged with a unique identifier specifying 
     * what agent wrote the data.  GetAgentKey() from libfossagent, gets you the key. */
    Agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
    // BOBG said to do this!!!
    Agent_pk = 1;

    /* Process command-line */
    while((c = getopt(argc,argv,"itm")) != -1)
    {
        switch(c)
        {
            case 'i':
                DBclose(DB);  /* DB was opened above, now close it and exit */
                exit(0);
            default:
                Usage(argv[0]);
                DBclose(DB);
                exit(-1);
        }
    }



    /* If no args, run from scheduler! */
    if (argc == 1)
    {
        /* set the heartbeat signal handler.  ShowHeartbeat is in libfossagent */
        /* The heartbeat is used by the scheduler to verify the agent is still alive */
        signal(SIGALRM,ShowHeartbeat);
        alarm(60);

        printf("OK\n"); /* inform scheduler that we are ready */
        fflush(stdout);

        /* THE MAIN READ STDIN LOOP */
        /* This is where the agent reads in what to process and does the work */
        /* ReadLine() is in libfossagent */
        while(ReadLine(stdin,Parm,MAXLINE) >= 0)
        {
            int pfile_fk = 0;
            char filename[MAXLINE];

            // read a pfile_fk and a filename from stdin
            // a successful read is 2.
            if (sscanf(Parm,"%d, %s", &pfile_fk, filename) == 2) {
                printf("%d, %s\n", pfile_fk, filename);
            }

            printf("OK\n"); /* inform scheduler that we are ready for more data */
            fflush(stdout);
        }
    }
    else
    {
        /* read and process data not delivered by the scheduler */
    }

    DBclose(DB);
    return(0);

}
