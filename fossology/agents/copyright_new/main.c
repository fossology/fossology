/* **************************************************************
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

************************************************************** */

/* std library includes */
#include <unistd.h>
#include <string.h>
#include <stdio.h>
#include <ctype.h>

/* other library includes */
#include <libfossagent.h>
#include <libfossdb.h>
#include <libfossrepo.h>
#include <libpq-fe.h>
#include <unistd.h>
#include <errno.h>
#include <signal.h>

/* local includes */
#include <copyright.h>
#include <cvector.h>
#include <copyright.h>
#include <pair.h>
#include <sql_statements.h>

#define READMAX 1024*1024            ///< farthest into a file to look for copyrights
#define THRESHOLD 10                 ///< tuned threshold for testing purposes
#define TESTFILE_NUMBER 140          ///< the number of files pairs used in tests
#define AGENT_NAME "copyright"       ///< the name of the agent, used to get agent key
#define AGENT_DESC "copyright agent" ///< what program this is

FILE* cout;                           ///< the file to print information to
FILE* cerr;                           ///< the file to print errors to
FILE* cin;                            ///< the file to read from
int verbose = 0;                      ///< turn on or off dumping to debug files
int db_connected = 0;                 ///< indicates if the database is connected
char* test_dir = "testdata/testdata"; ///< the location of the labeled and raw testing data

/**
 * @brief prints the usage statement for the copyright agent
 *
 * @param argv the command line namme of the function
 */
void copyright_usage(char* arg)
{
  fprintf(cout, "Usage: %s [options]\n", arg);
  fprintf(cout, "  Options are:\n");
  fprintf(cout, "  -d  :: Turns verbose on, matches printed to Matches file.\n");
  fprintf(cout, "  -i  :: Initialize the database, the exit.\n");
  fprintf(cout, "  -c  :: Run command line, does not write to database.\n");
  fprintf(cout, "  -t  :: Run the accuracy tests, nothing written to database.\n");
  fprintf(cout, "NOTE: -i, -c, and -t cause the agent to perform the request\n");
  fprintf(cout, "       and then exit without waiting for scheduler input\n");
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
int longest_common(char* dst, char* lhs, char* rhs)
{
  int result[strlen(lhs)][strlen(rhs)], i, j;
  int beg = 0, ths = 0, max = 0;

  memset(result, 0, sizeof(result));
  dst[0] = '\0';

  for(i = 0; i < strlen(lhs); i++)
  {
    for(j = 0; j < strlen(rhs); j++)
    {
      if(lhs[i] == rhs[j])
      {
        if(i == 0 || j == 0) result[i][j] = 1;
        else                 result[i][j] = result[i - 1][j - 1] + 1;

        if(result[i][j] > max)
        {
          max = result[i][j];
          ths = i - result[i][j] + 1;
          // the current substring is still hte longest found
          if(beg == ths)
          {
            strncat(dst, lhs + i, 1);
          }
          // a new longest common substring has been found
          else
          {
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
void run_test_files(copyright copy)
{
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
  if(!m_out || !n_out || !p_out)
  {
    fprintf(cerr, "ERROR: did not successfully open one of the log files\n");
    fprintf(cerr, "ERROR: the files that needed to be opened were:\n");
    fprintf(cerr, "ERROR: Matches, False_Positives, False_Negatives\n");
    exit(-1);
  }

  /* loop over every file in the test directory */
  for(i = 0; i < TESTFILE_NUMBER; i++)
  {
    sprintf(file_name, "%s%d_raw", test_dir, i);

    /* attempt to open the labeled test file */
    istr = fopen(file_name, "r");
    if(!istr)
    {
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
    for(first = buffer; *first; first++)
    {
      *first = tolower(*first);
    }

    /* loop through and find all <s>...</s> tags */
    loc = buffer;
    while((first = strstr(loc, "<s>")) != NULL)
    {
      last = strstr(loc, "</s>");

      if(last == NULL)
      {
        fprintf(cerr, "ERROR: unmatched \"<s>\"\n");
        fprintf(cerr, "ERROR: in file: \"%s\"\n", file_name);
        exit(-1);
      }

      if(last <= first)
      {
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
    if(!istr)
    {
      fprintf(cerr, "ERROR: Unmatched file in the test directory");
      fprintf(cerr, "ERROR: File with no match: \"%s\"_raw\n", file_name);
      fprintf(cerr, "ERROR: File that caused error: \"%s\"\n", file_name);
    }

    /* perform the analysis on the current file */
    copyright_analyze(copy, istr);
    fclose(istr);

    /* loop over every match that the copyright object found */
    for(iter = copyright_begin(copy); iter != copyright_end(copy); iter++)
    {
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
      for(curr = cvector_begin(compare); curr != cvector_end(compare); curr++)
      {
        if(longest_common(dst, copy_entry_text(*iter), (char*)*curr) > strlen(score))
        {
          strcpy(score, dst);
          best = curr;
        }
      }

      /* log the entry as found if it matched something in compare */
      if(cvector_size(compare) != 0 &&
          (strcmp(copy_entry_dict(*iter), "by") || strlen(score) > THRESHOLD))
      {
        cvector_remove(compare, best);
        matches++;
      }
      else if(!strcmp(copy_entry_dict(*iter), "email") || !strcmp(copy_entry_dict(*iter), "url"))
      {
        matches++;
      }
      else
      {
        fprintf(p_out, "====%s================================\n", file_name);
        fprintf(p_out, "DICT: %s\tNAME: %s\n",copy_entry_dict(*iter), copy_entry_name(*iter));
        fprintf(p_out, "TEXT[%s]\n",copy_entry_text(*iter));
      }
    }

    /* log all the false negatives */
    for(curr = cvector_begin(compare); curr != cvector_end(compare); curr++)
    {
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
void perform_analysis(PGconn* pgConn, copyright copy, pair curr, long agent_pk)
{
  /* locals */
  char sql[1024], * tmp = NULL;
  extern int HBItemsProcessed;
  cvector_iterator iter;
  copyright_iterator finds;
  FILE* input_fp, * mout = NULL;
  PGresult* pgResult;

  /* initialize memory */
  memset(sql, 0, sizeof(sql));
  iter = NULL;
  finds = NULL;
  input_fp = NULL;

  /* if the verbose flag has been set, we need to open the relevant files */
  if(verbose)
  {
    mout = fopen("Matches", "w");
    if(!mout)
    {
      fprintf(cerr, "FATAL: could not open Matches for logging\n");
      fflush(cerr);
      exit(-1);
    }
  }

  /* find the correct path to the file */
  if(*(int*)pair_second(curr) >= 0)
  {
    tmp = RepMkPath("files", (char*)pair_first(curr));
  }
  else
  {
    tmp = (char*)pair_first(curr);
  }

  fprintf(cout, "%s\n", RepMkPath("files", (char*)pair_first(curr)));

  /* attempt to open the file */
  input_fp = fopen(tmp, "rb");
  if(!input_fp)
  {
    fprintf(cerr, "FATAL: %s.%d Failure to open file %s\n", __FILE__, __LINE__, tmp);
    fprintf(cerr, "ERROR: %s\n", strerror(errno));
    fflush(cerr);
    exit(-1);
  }

  /* only free temp if running as an agent */
  if(*(int*)pair_second(curr) >= 0)
  {
    free(tmp);
  }

  /* perform the actual analysis */
  copyright_analyze(copy, input_fp);

  /* if running command line, print file name */
  if(*(int*)pair_second(curr) < 0)
  {
    fprintf(cout, "%s\n", (char*)pair_first(curr));
  }

  /* loop across the found copyrights */
  if(copyright_size(copy) > 0)
  {
    for(finds = copyright_begin(copy); finds != copyright_end(copy); finds++)
    {
      copy_entry entry = (copy_entry)*finds;

      if(verbose)
      {
        fprintf(mout, "=== %s ==============================================\n",
            (char*)pair_first(curr));
        fprintf(mout, "DICT: %s\nNAME: %s\nTEXT[%s]\n",
            copy_entry_dict(entry),
            copy_entry_name(entry),
            copy_entry_text(entry));
      }

      if(*(int*)pair_second(curr) >= 0)
      {
        memset(sql, '\0', sizeof(sql));
        snprintf(sql, sizeof(sql), insert_copyright, agent_pk, *(int*)pair_second(curr),
            copy_entry_start(entry), copy_entry_end(entry),
            copy_entry_text(entry), "", copy_entry_type(entry));
        pgResult = PQexec(pgConn, sql);

        if (PQresultStatus(pgResult) != PGRES_COMMAND_OK)
        {
          fprintf(cerr, "ERROR: %s.%d: %s\nOn: %s\n",
              __FILE__, __LINE__, PQresultErrorMessage(pgResult), sql);
          PQclear(pgResult);
          exit(-1);
        }
      }
      else
      {
        fprintf(cout, "\t[%d:%d] %s",
            copy_entry_start(entry), copy_entry_end(entry),
            copy_entry_text(entry));
        if(copy_entry_text(entry)[strlen(copy_entry_text(entry)) - 1] != '\n')
        {
          fprintf(cout, "\n");
        }
      }
    }
  }
  else if(*(int*)pair_second(curr) >= 0)
  {
    snprintf(sql, sizeof(sql), insert_no_copyright, agent_pk, *(int*)pair_second(curr));
    pgResult = PQexec(pgConn, sql);

    if (PQresultStatus(pgResult) != PGRES_COMMAND_OK)
    {
      fprintf(cerr, "ERROR: %s:%d, %s\nOn: %s\n",
          AGENT_DESC, __LINE__, PQresultErrorMessage(pgResult), sql);
      PQclear(pgResult);
      exit(-1);
    }
  }

  /* we are finished with this file, close it and incriment heart beat */
  if(verbose)
  {
    fclose(mout);
  }

  fclose(input_fp);
  Heartbeat(++HBItemsProcessed);
}

/**
 * @brief check to make sure the copyright has been created
 *
 * will attempt to access the copyright table, if the response from the database
 * indicates that the copyright table does not exist, this will also attempt to
 * create the table for future use.
 *
 * @param pgConn the connection to the database
 * @return 1 if the table exists at the end of the function, 0 otherwise
 */
int check_copyright_table(PGconn* pgConn)
{
  PGresult* pgResult = PQexec(pgConn, check_database_table);


  PQclear(pgResult);
  return 0;
}

/**
 * @brief main function for the copyright agent
 *
 * The copyright agent is used to automatically locate copyright statements
 * found in code.
 *
 * There are 3 ways to use the copyright agent:
 *   1. Command Line Analysis :: test a file from the command line
 *   2. Agent Based Analysis  :: waits for commands from stdin
 *   3. Accuracy Test         :: tests the accuracy of the copyright agent
 *
 * +-----------------------+
 * | Command Line Analysis |
 * +-----------------------+
 *
 * To analyze a file from the command line:
 *   -c <filename>      :: run copyright agent from command line
 *   -d                 :: turn on debugging information
 *
 *   example:
 *     $ ./copyright -c
 *
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 *
 * To run the copyright agent as an agent simply run with no command line args
 *   -i                 :: initialize a connection to the database
 *   -d                 :: turn on debuggin information
 *
 *   example:
 *     $ upload_pk | ./copyright
 *
 * +---------------+
 * | Accuracy Test |
 * +---------------+
 *
 * To test the accuracy of the copyright agent run with a -t. Make sure to run the
 * accuracy tests in the source directory with the testdata directory:
 *   -t                 :: run the accuracy analysis
 *
 *   example:
 *     $ ./copyright -t
 *
 * Running the tests will create 3 files:
 * 1. Matches: contains all of the matches found by the copyright agent, information
 *             includes what file the match was found in, the dictionary element
 *             that it matched, the name that it matched and the text that was found
 * 2. False_Positives: contains all of the flase positives found by the agent,
 *             information in the file includes the file the false positive was
 *             in, the dictionary match, the name match, and the text
 * 3. Flase_Negatives: contains all of the false negatives found by the agent,
 *             information in the file includes the file the false negative was
 *             in, and the text of the false negative
 *
 * NOTE: -d will procudes the exact same style of Matches file that the accuracy
 *       testing does. Currently this is the only thing that -d will produce
 *
 * @param argc the number of command line arugments
 * @param argv the command line arguments
 * @return 0 of a succefull program execution
 */
int main(int argc, char** argv)
{
  /* primitives */
  char input[FILENAME_MAX];     // input buffer
  char sql[512];                // buffer for database access
  int c, i = -1;                // temporary int containers
  int num_files = 0;            // the number of rows in a job
  long upload_pk = 0;           // the upload primary key
  long agent_pk = 0;            // the agents primary key
  extern int AlarmSecs;         // the number of seconds between heartbeats

  /* Database structs */
  void* DataBase = NULL;        // the Database object itself
  PGconn* pgConn = NULL;        // the connection to Database
  PGresult* pgResult = NULL;    // result of a database access

  /* copyright structs */
  copyright copy;               // the work horse of the copyright agent
  pair curr;                    // pair to push into the file list

  /* set the output streams */
  cout = stdout;
  cerr = stdout;
  cin = stdin;

  /* initialize complex data strcutres */
  copyright_init(&copy);

  /* parse the command line options */
  while((c = getopt(argc, argv, "dc:ti")) != -1)
  {
    switch(c)
    {
      case 'd': /* debugging */
        verbose = 1;
        break;
      case 'c': /* run from command line */
        pair_init(&curr, string_function_registry(), int_function_registry());

        pair_set_first(curr, optarg);
        pair_set_second(curr, &i);
        perform_analysis(pgConn, copy, curr, agent_pk);
        num_files++;

        pair_destroy(curr);
        break;
      case 't': /* run accuracy testing */
        run_test_files(copy);
        copyright_destroy(copy);
        return 0;
      case 'i': /* initialize database connections */
        DataBase = DBopen();
        if(!DataBase) {
          fprintf(cerr, "FATAL: Copyright agent unable to connect to database.\n");
          exit(-1);
        }
        DBclose(DataBase);
        return 0;
      default: /* error, print usage */
        copyright_usage(argv[0]);
        return -1;
    }
  }

  /* if there are no files in the file list then the agent is begin run from */
  /* the scheduler, open the database and grab the files to be analyzed      */
  if(num_files == 0)
  {
    /* create the heartbeat */
    signal(SIGALRM, ShowHeartbeat);
    alarm(AlarmSecs);

    /* open the database */
    DataBase = DBopen();
    if(!DataBase)
    {
      fprintf(cerr, "FATAL: Copyright agent unable to connect to database.\n");
      exit(-1);
    }

    /* book keeping */
    pgConn = DBgetconn(DataBase);
    pair_init(&curr, string_function_registry(), int_function_registry());
    db_connected = 1;
    agent_pk = GetAgentKey(DataBase, AGENT_NAME, 0, "", AGENT_DESC);

    /* enter the main agent loop */
    fprintf(cout, "OK");
    while(fgets(input, FILENAME_MAX, cin) != NULL)
    {
      upload_pk = atol(input);

      sprintf(sql, fetch_pfile, upload_pk, agent_pk);
      pgResult = PQexec(pgConn, sql);
      num_files = PQntuples(pgResult);

      for(i = 0; i < num_files; i++)
      {
        c = atoi(PQgetvalue(pgResult, i, PQfnumber(pgResult, "pfile_pk")));
        pair_set_first(curr, PQgetvalue(pgResult, i, PQfnumber(pgResult, "pfilename")));
        pair_set_second(curr, &c);
        perform_analysis(pgConn, copy, curr, agent_pk);
      }

      PQclear(pgResult);
      fprintf(cout, "OK");
    }

    fprintf(cout, "BYE");
    pair_destroy(curr);
  }

  if(db_connected)
  {
    DBclose(DataBase);
  }

  copyright_destroy(copy);

  return 0;
}

