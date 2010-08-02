/***************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

***************************************************************/

/* std library includes */
#include <unistd.h>
#include <string.h>
#include <stdio.h>
#include <ctype.h>

/* other library includes */
#include <libfossagent.h>
#include <libfossdb.h>
#include <libpq-fe.h>

/* local includes */
#include <copyright.h>
#include <cvector.h>
#include <copyright.h>
#include <pair.h>
#include <sql_statements.h>

#define READMAX 1024*1024            ///< farthest into a file to look for copyrights
#define THRESHOLD 10                 ///< tuned threshold for testing purposes
#define TESTFILE_NUMBER 140          ///< the number of files pairs used in tests
#define STDOUT stdout;               ///< the file to write std information to
#define STDERR stderr;               ///< the file to write error information to
#define STDIN stdin;                 ///< the file that will be read from as an agent
#define AGENT_NAME "copyright"       ///< the name of the agent, used to get agent key
#define AGENT_DESC "copyright agent" ///< what program this is

/** the file to print information to */
FILE* cout;
/** the file to print errors to */
FILE* cerr;
/** the file to read from */
FILE* cin;
/** output filestream for debugging purposes */
FILE* mout;

int verbose = 0;                ///< turn on or off dumping to debug files
int db_connected = 0;           ///< indicates if the database is connected

/** the location of the labeled and raw testing data */
char* test_dir = "testdata/testdata";

/**
 * @brief prints the usage statement for the copyright agent
 *
 *
 *
 * @param argv
 */
void copyright_usage(char* arg) {
  // TODO
}

/**
 * @brief find the longest common substring between two strings
 *
 * find the longest common substring between lhs and rhs, copying this substring
 * into dst and returning the length of the substring.
 *
 * @param dst the destination of the loggest common substring
 * @param lhs a string to search within
 * @param rhs a string to search within
 * @return the length of the longest common substring
 */
int longest_common(char* dst, char* lhs, char* rhs) {
  int result[strlen(lhs)][strlen(rhs)], i, j;
  int beg = 0, ths = 0, max = 0;

  memset(result, 0, sizeof(result));
  dst[0] = '\0';

  for(i = 0; i < strlen(lhs); i++) {
    for(j = 0; j < strlen(rhs); j++) {
      if(lhs[i] == rhs[j]) {
        if(i == 0 || j == 0) {
          result[i][j] = 1;
        } else {
          result[i][j] = result[i - 1][j - 1] + 1;
        }

        if(result[i][j] > max) {
          max = result[i][j];
          ths = i - result[i][j] + 1;
          // the current substring is still hte longest found
          if(beg == ths) {
            strncat(dst, lhs + i, 1);
          }
          // a new longest common substring has been found
          else {
            beg = ths;
            strncpy(dst, lhs + beg, (i + 1) - beg);
          }
        }
      }
    }
  }

  return strlen(dst);
}

/**
 * @brief runs the labeled test files to determine accuracy
 *
 * This function will open each pair of files in the testdata directory to
 * analyze how accurate the copyright agent is. This function will respond with
 * the number of false negatives, false positives, and correct answers for each
 * file and total tally of these numbers. This will also produce 3 files, one
 * containing all matches that the copyright agent found, all the things that it
 * didn't find, and all of the false positives.
 */
void run_test_files(copyright copy) {
  /* locals */
  cvector compare;
  copyright_iterator iter;
  cvector_iterator curr;
  FILE* istr, * m_out, * n_out, * p_out;
  char buffer[READMAX + 1];
  char file_name[FILENAME_MAX];
  char* first, * last, * loc, tmp;
  int i, matches, correct = 0, falsep = 0, falsen = 0;

  /* create data structures */
  copyright_init(&copy);
  cvector_init(&compare, string_function_registry());

  /* open the logging files */
  m_out = fopen("Matches", "w");
  n_out = fopen("False_Negatives", "w");
  p_out = fopen("False_Positives", "w");

  /* big problem if any of the log files didn't open correctly */
  if(!m_out || !n_out || !p_out) {
    fprintf(cerr, "ERROR: did not successfully open one of the log files\n");
    fprintf(cerr, "ERROR: the files that needed to be opened were:\n");
    fprintf(cerr, "ERROR: Matches, False_Positives, False_Negatives\n");
    exit(-1);
  }

  /* loop over every file in the test directory */
  for(i = 0; i < TESTFILE_NUMBER; i++) {
    sprintf(file_name, "%s%d_raw", test_dir, i);

    /* attempt to open the labeled test file */
    istr = fopen(file_name, "r");
    if(!istr) {
      fprintf(cerr, "ERROR: Must run testing from correct directory. The\n");
      fprintf(cerr, "ERROR: correct directory is installation dependent but\n");
      fprintf(cerr, "ERROR: the working directory should include the folder:\n");
      fprintf(cerr, "ERROR:   %s\n", test_dir);
      exit(-1);
    }

    /* initialize the buffer and read in any information */
    memset(buffer, '\0', sizeof(buffer));
    buffer[fread(buffer, sizeof(char), READMAX, istr)] = '\0';
    matches = 0;

    /* set everything in the buffer to lower case */
    for(first = buffer; *first; first++) {
      *first = tolower(*first);
    }

    /* loop through and find all <s>...</s> tags */
    loc = buffer;
    while((first = strstr(loc, "<s>")) != NULL) {
      last = strstr(loc, "</s>");

      if(last == NULL) {
        fprintf(cerr, "ERROR: unmatched \"<s>\"\n");
        fprintf(cerr, "ERROR: in file: \"%s\"\n", file_name);
        exit(-1);
      }

      if(last <= first) {
        fprintf(cerr, "ERROR: unmatched \"</s>\"\n");
        fprintf(cerr, "ERROR: in file: \"%s\"\n", file_name);
        exit(-1);
      }

      tmp = *last;
      *last = 0;
      cvector_push_back(compare, first + 3);
      *last = tmp;
      loc = last + 4;
    }

    /* close the previous file and open the corresponding raw data */
    fclose(istr);
    file_name[strlen(file_name) - 4] = '\0';
    istr = fopen(file_name, "r");
    if(!istr) {
      fprintf(cerr, "ERROR: Unmatched file in the test directory");
      fprintf(cerr, "ERROR: File with no match: \"%s\"_raw\n", file_name);
      fprintf(cerr, "ERROR: File that caused error: \"%s\"\n", file_name);
    }

    /* perform the analysis on the current file */
    copyright_analyze(copy, istr);
    fclose(istr);

    /* loop over every match that the copyright object found */
    for(iter = copyright_begin(copy); iter != copyright_end(copy); iter++) {
      cvector_iterator best = cvector_begin(compare);
      char score[2048];
      char dst[2048];

      memset(dst, '\0', sizeof(dst));
      memset(score, '\0', sizeof(score));

      /* log the coyright entry */
      fprintf(m_out, "====%s================================\n", file_name);
      fprintf(m_out, "DICT: %s\tNAME: %s\n",copy_entry_dict(*iter), copy_entry_name(*iter));
      fprintf(m_out, "TEXT[%s]\n",copy_entry_text(*iter));

      /* loop over the vector looking for matches */
      for(curr = cvector_begin(compare); curr != cvector_end(compare); curr++) {
        if(longest_common(dst, copy_entry_text(*iter), (char*)*curr) > strlen(score)) {
          strcpy(score, dst);
          best = curr;
        }
      }

      /* log the entry as found if it matched something in compare */
      if(cvector_size(compare) != 0 &&
          (strcmp(copy_entry_dict(*iter), "by") || strlen(score) > THRESHOLD)) {
        cvector_remove(compare, best);
        matches++;
      } else {
        fprintf(p_out, "====%s================================\n", file_name);
        fprintf(p_out, "DICT: %s\tNAME: %s\n",copy_entry_dict(*iter), copy_entry_name(*iter));
        fprintf(p_out, "TEXT[%s]\n",copy_entry_text(*iter));
      }
    }

    /* log all the false negatives */
    for(curr = cvector_begin(compare); curr != cvector_end(compare); curr++) {
      fprintf(n_out, "====%s================================\n", file_name);
      fprintf(n_out, "%s\n", (char*)*curr);
    }

    fprintf(cout, "====%s================================\n", file_name);
    fprintf(cout, "Correct:         %d\n", matches);
    fprintf(cout, "False Positives: %d\n", copyright_size(copy) - matches);
    fprintf(cout, "False Negatives: %d\n", cvector_size(compare));

    /* clean up for the next file */
    correct += matches;
    falsep += copyright_size(copy) - matches;
    falsen += cvector_size(compare);
    cvector_clear(compare);
  }

  fprintf(cout, "==== Totals ================================\n");
  fprintf(cout, "Total Found:     %d\n", correct + falsep);
  fprintf(cout, "Correct:         %d\n", correct);
  fprintf(cout, "False Positives: %d\n", falsep);
  fprintf(cout, "False Negatives: %d\n", falsen);

  fclose(m_out);
  fclose(n_out);
  fclose(p_out);
  copyright_destroy(copy);
  cvector_destroy(compare);
}

/**
 * @brief perform the analysis of a given list of files
 *
 * loops over the file_list given and performs the analysis of all the files,
 * when finished simply check if the results should be entered into the
 * database, this is indicated by the second element of the pair not being NULL
 *
 * @param copy the copyright instance to use to perform the analysis
 * @param file_list the list of files to analyze
 */
void perform_analysis(PGconn* pgConn, copyright copy, cvector file_list, long agent_pk) {
  /* locals */
  char sql[1024];
  cvector_iterator iter;
  copyright_iterator finds;
  FILE* istr;
  PGresult* pgResult;

  /* initialize memory */
  memset(sql, 0, sizeof(sql));
  iter = NULL;
  finds = NULL;
  istr = NULL;

  /* loop over all of the files that have been loaded into the cvector for */
  /* processing. if the pfile_pk (second element in pair) is positive this */
  /* will also enter the results into the database                         */
  for(iter = cvector_begin(file_list); iter != cvector_end(file_list); iter++) {
    pair curr = (pair)*iter;

    /* attempt to open the file */
    istr = fopen((char*)pair_first(curr), "rb");
    if(!istr) {
      fprintf(cerr, "FATAL: pfile %d Copyright Agent unable to open file %s\n",
          (unsigned int)pair_second(curr), (char*)pair_first(curr));
      fflush(cerr);
      exit(-1);
    }

    /* perform the actual analysis */
    copyright_analyze(copy, istr);

    /* if running command line, print file name */
    if(*(int*)pair_second(curr) < 0) {
      fprintf(cout, "%s\n", (char*)pair_first(curr));
    }

    /* loop across the found copyrights */
    if(copyright_size(copy) > 0) {
      for(finds = copyright_begin(copy); finds != copyright_end(copy); finds++) {
        copy_entry entry = (copy_entry)*finds;

        if(verbose) {
          fprintf(mout, "=== %s ==============================================\n",
              (char*)pair_first(curr));
          fprintf(mout, "DICT: %s\nNAME: %s\nTEXT[%s]\n",
              copy_entry_dict(entry),
              copy_entry_name(entry),
              copy_entry_text(entry));
        }

        if(*(int*)pair_second(curr) >= 0) {
          // TODO there are several things in this sql that seem unnecessary now
          sprintf(sql, copyrights_found, agent_pk, *(int*)pair_second(curr),
              copy_entry_start(entry), copy_entry_end(entry),
              copy_entry_text(entry), "statement");
          pgResult = PQexec(pgConn, sql);

          if (PQresultStatus(pgResult) != PGRES_TUPLES_OK) {
            fprintf(cerr, "ERROR: %s:%d, %s\nOn: %s\n",
                  AGENT_DESC, __LINE__, PQresultErrorMessage(pgResult), sql);
            PQclear(pgResult);
            exit(-1);
          }
        } else {
          fprintf(cout, "\t[%d:%d] %s",
              copy_entry_start(entry), copy_entry_end(entry),
              copy_entry_text(entry));
          if(copy_entry_text(entry)[strlen(copy_entry_text(entry)) - 1] != '\n') {
            fprintf(cout, "\n");
          }
        }
      }
    } else if(*(int*)pair_second(curr) >= 0) {
      sprintf(sql, no_copyrights_found, agent_pk, *(int*)pair_second(curr));
      pgResult = PQexec(pgConn, sql);

      if (PQresultStatus(pgResult) != PGRES_TUPLES_OK) {
        fprintf(cerr, "ERROR: %s:%d, %s\nOn: %s\n",
               AGENT_DESC, __LINE__, PQresultErrorMessage(pgResult), sql);
        PQclear(pgResult);
        exit(-1);
      }
    }

    /* we are finished with this file, close it */
    fclose(istr);
  }
}

/**
 * @brief main function for the copyright agent
 *
 * The copyright agent is used to automatically locate copyright statements
 * found in code.
 *
 * There are 3 ways to use the copyright agent:
 *   1. Command Line Analysis :: test a file from the command line
 *   2. Agent Based Analysis :: waits for commands from stdin
 *   3. Accuracy Test :: tests the accuracy of the copyright agent
 *
 * +-----------------------+
 * | Command Line Analysis |
 * +-----------------------+
 *
 * To analyze a file from the command line:
 *   ./copyright -c <filename 1> -c <filename 2> ... -c <filename N>
 *
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 *
 * To run the copyright agent as an agent simply run with no command line args
 *   ./copyright
 *
 * In either Agent Based Analysis or Command Line Analysis, the copyright agent
 * can be run with a -d to turn on the debugging information. This will create
 * a file named "Matches" with all the copyright match information conatined
 * within.
 *
 * +---------------+
 * | Accuracy Test |
 * +---------------+
 *
 * Running the accuracy test for the copyright agent will trump all other
 * command line arguments, and will only run the accuracy test as result. To
 * test the accuracy of the copyright agent run with a -t:
 *   ./copyright -t
 *
 *
 *
 * @param argc
 * @param argv
 * @return
 */
int main(int argc, char** argv)
{
  /* primitives */
  char input[FILENAME_MAX];     // input buffer
  char sql[512];                // buffer for database access
  int c;                        // holds return from getop()
  int i = -1;                   // TODO find a way to rid of this variable
  long upload_pk = 0;           // used for database access
  long agent_pk = 0;            // used for database access

  /* Database structs */
  void* DataBase = NULL;        // the connection to the database
  PGconn* pgConn = NULL;        // cursor for the database
  PGresult* pgResult = NULL;    // result of a database access

  /* copyright structs */
  cvector file_list;            // the list of files to be analyzed
  copyright copy;               // the workhdorse of the copyrigth agent
  pair curr;                    // pair to push into the file list

  /* set the output streams */
  cout = STDOUT;
  cerr = STDERR;
  cin = STDIN;

  /* initialize complex data strcutres */
  cvector_init(&file_list, pair_function_registry());
  copyright_init(&copy);

  /* parse the command line options */
  while((c = getopt(argc, argv, "dc:t")) != -1) {
    switch(c) {
      case 'd':
        verbose = 1;
        break;
      case 'c':
        pair_init(&curr, string_function_registry(), int_function_registry());

        pair_set_first(curr, optarg);
        pair_set_second(curr, &i);
        cvector_push_back(file_list, curr);

        pair_destroy(curr);
        break;
      case 't':
        run_test_files(copy);
        cvector_destroy(file_list);
        copyright_destroy(copy);
        return 0;
      default:
        copyright_usage(argv[0]);
        break;
    }
  }

  /* if there are no files in the file list then the agent is begin run from */
  /* the scheduler, open the database and grab the files to be analyzed      */
  if(cvector_size(file_list) == 0) {
    DataBase = DBopen();
    if(!DataBase) {
      fprintf(cerr, "FATAL: Copyright agent unable to connect to database.\n");
      exit(-1);
    }

    /* book keeping */
    pgConn = DBgetconn(DataBase);
    pair_init(&curr, string_function_registry(), int_function_registry());
    db_connected = 1;
    agent_pk = GetAgentKey(DataBase, AGENT_NAME, 0, "", AGENT_DESC);

    while(fgets(input, FILENAME_MAX, cin) != NULL) {
      upload_pk = atol(input);

      sprintf(sql, fetch_pfile, upload_pk, agent_pk);
      pgResult = PQexec(pgConn, sql);
      i = PQntuples(pgResult);

      for(c = 0; c < i; c++) {
        pair_set_first(curr, PQgetvalue(pgResult, c, PQfnumber(pgResult, "pfilename")));
        pair_set_second(curr, PQgetvalue(pgResult, c, PQfnumber(pgResult, "pfile_pk")));
        cvector_push_back(file_list, curr);
      }

      perform_analysis(pgConn, copy, file_list, agent_pk);
      cvector_clear(file_list);
    }

    pair_destroy(curr);
  }

  /* if the verbose flag has been set, we need to open the relevant files */
  if(verbose) {
    mout = fopen("Matches", "w");
    if(!mout) {
      fprintf(cerr, "FATAL: could not open Matches for logging\n");
      fflush(cerr);
      exit(-1);
    }
  }

  perform_analysis(pgConn, copy, file_list, agent_pk);

  if(verbose) {
    fclose(mout);
  }

  if(db_connected) {
    pair_destroy(curr);
    DBclose(DataBase);
  }

  cvector_destroy(file_list);
  copyright_destroy(copy);

  return 0;
}






