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
#include <unistd.h>
#include <errno.h>
#include <signal.h>

/* other library includes */
#include <libfossology.h>
#include <libpq-fe.h>

/* local includes */
#include <copyright.h>
#include <cvector.h>
#include <pair.h>
#include <sql_statements.h>

#define READMAX 1024*1024            ///< farthest into a file to look for copyrights
#define THRESHOLD 10                 ///< tuned threshold for testing purposes
#define TESTFILE_NUMBER 140          ///< the number of files pairs used in tests
#define AGENT_NAME "copyright"       ///< the name of the agent, used to get agent key
#define AGENT_DESC "copyright agent" ///< what program this is
#define AGENT_ARS  "copyright_ars"   ///< name used for the ars table

psqlCopy_t sqlcpy;                    ///< the sql copy struct used for insertion
FILE* cout;                           ///< the file to print information to
FILE* cerr;                           ///< the file to print errors to
FILE* cin;                            ///< the file to read from
int verbose = 0;                      ///< turn on or off dumping to debug files
int db_connected = 0;                 ///< indicates if the database is connected
char* test_dir = "testdata/testdata"; ///< the location of the labeled and raw testing data

/* ************************************************************************** */
/* **** Utility Functions *************************************************** */
/* ************************************************************************** */

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
 * Finds the Hash for a particular string. Used when checking found copyrights
 * in the database
 *
 * @param str the string to find the hash for
 * @return the hash for the string as an unsigned long
 */
unsigned long hash_string(char* str) {
  unsigned long hash = 0;

  for(; *str; str++) {
    hash = *str + (hash << 6) + (hash << 16) - hash;
  }

  return hash;
}

/**
 * the postgresql escape function has a bug when it tries to escape string that
 * contain a '/'. This function accounts for that bug and then calls the escape
 * function for postgresql.
 * This function also substitutes a space for a tab so that the data can be
 * used with the sql copy functions.  It would be more acurate to substitute
 * a tab for the string "\t" to preserve the data, but a space is simpler
 * and probably good enough.
 *
 * @param pgConn the connection to the database
 * @param dst the destination of the escaped string
 * @param src the source string that needs to be escaped
 * @param esclen the len of the string to escape
 */
void  escape_string(PGconn* pgConn, char *dst, const char *src, int esclen)
{
  int len;
  int error;

  /*  remove any backslashes from the string as they don't escape properly
   *  for example, "don\'t" in the input will cause an insert error
   */
  char *cp = (char *)src;
  while(*cp)
  {
    if ((*cp == '\\') || (*cp == '\t'))
    {
      *cp = ' ';
    }
    cp++;
  }

  /* check the size of the destination buffer */
  if((len = strlen(src)) > esclen/2) {
    fprintf(cerr, "ERROR %s.%d: length of input string is too large\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR length of string was %d, max length is %d\n", len, esclen/2);
    return;
  }

  PQescapeStringConn(pgConn, dst, src, len, &error);
  if (error)
  {
    fprintf(cerr, "WARNING %s.%d: Error escaping string for database entry\n",__FILE__, __LINE__ );
    fprintf(cerr, "WARNING string was: '%s'\n", src);
  }
}

/* ************************************************************************** */
/* **** Accuracy Tests ****************************************************** */
/* ************************************************************************** */

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
  char copy_buf[FILENAME_MAX];
  char name_buf[FILENAME_MAX];
  char* first, * last, * loc, tmp;
  int i, matches, correct = 0, falsep = 0, falsen = 0;

  /* grab the copyright files */
  memset(copy_buf, '\0', sizeof(copy_buf));
  memset(name_buf, '\0', sizeof(copy_buf));
  snprintf(copy_buf, sizeof(copy_buf),
      "%s/mods-enabled/copyright/agent/copyright.dic",
      sysconfigdir);
  snprintf(name_buf, sizeof(name_buf),
      "%s/mods-enabled/copyright/agent/names.dic",
      sysconfigdir);

  /* create data structures */
  copyright_init(&copy, copy_buf, name_buf);
  cvector_init(&compare, string_function_registry());

  /* open the logging files */
  m_out = fopen("Matches", "w");
  n_out = fopen("False_Negatives", "w");
  p_out = fopen("False_Positives", "w");

  /* big problem if any of the log files didn't open correctly */
  if(!m_out || !n_out || !p_out)
  {
    fprintf(cerr, "ERROR did not successfully open one of the log files\n");
    fprintf(cerr, "ERROR the files that needed to be opened were:\n");
    fprintf(cerr, "ERROR Matches, False_Positives, False_Negatives\n");
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
      fprintf(cerr, "ERROR Must run testing from correct directory. The\n");
      fprintf(cerr, "ERROR correct directory is installation dependent but\n");
      fprintf(cerr, "ERROR the working directory should include the folder:\n");
      fprintf(cerr, "ERROR   %s\n", test_dir);
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
        fprintf(cerr, "ERROR unmatched \"<s>\"\n");
        fprintf(cerr, "ERROR in file: \"%s\"\n", file_name);
        exit(-1);
      }

      if(last <= first)
      {
        fprintf(cerr, "ERROR unmatched \"</s>\"\n");
        fprintf(cerr, "ERROR in file: \"%s\"\n", file_name);
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
      fprintf(cerr, "ERROR Unmatched file in the test directory");
      fprintf(cerr, "ERROR File with no match: \"%s\"_raw\n", file_name);
      fprintf(cerr, "ERROR File that caused error: \"%s\"\n", file_name);
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

/* ************************************************************************** */
/* **** Database Access ***************************************************** */
/* ************************************************************************** */

/**
 * @brief perform the analysis of a given list of files
 *
 * loops over the file_list given and performs the analysis of all the files,
 * when finished simply check if the results should be entered into the
 * database, this is indicated by the second element of the pair not being NULL
 *
 * @param pgConn the connection to the database
 * @param copy the copyright instance to use to perform the analysis
 * @param current_file the file and the pfile_pk that is currently being analyzed
 * @param agent_pk the primary key for this agent, use to enter info into the database
 * @param mout a logging file to used for debugging
 */
void perform_analysis(PGconn* pgConn, copyright copy, pair current_file, long agent_pk, FILE* mout)
{
  /* locals */
  char sql[2048];               // buffer to hold the sql commands
  char buf[2048];               // buffer to hold string that have been escaped for sql
  char hash[256];               // holds the hash of the copyright string for entry into database
  char* file_name;              // holds the name of the file to open
  copyright_iterator finds;     // an iterator to access the copyrights
  FILE* input_fp;               // the file that will be analyzed

  /* initialize memory */
  memset(sql, 0, sizeof(sql));
  file_name = NULL;
  finds = NULL;
  input_fp = NULL;

  /* find the correct path to the file */
  if(*(int*)pair_second(current_file) >= 0)
  {
    file_name = fo_RepMkPath("files", (char*)pair_first(current_file));
  }
  else
  {
    file_name = (char*)pair_first(current_file);
  }

  /* attempt to open the file */
  input_fp = fopen(file_name, "rb");
  if(!input_fp)
  {
    fprintf(cerr, "ERROR %s.%d Failure to open file %s\n", __FILE__, __LINE__, file_name);
    fprintf(cerr, "ERROR %s\n", strerror(errno));
    fflush(cerr);
    copyright_clear(copy);
    return;
  }

  /* only free temp if running as an agent */
  if(*(int*)pair_second(current_file) >= 0)
  {
    free(file_name);
  }

  /* perform the actual analysis */
  copyright_analyze(copy, input_fp);

  /* if running command line, print file name */
  if(*(int*)pair_second(current_file) < 0)
  {
    fprintf(cout, "%s ::\n", (char*)pair_first(current_file));
  }

  /* loop across the found copyrights */
  if(copyright_size(copy) > 0)
  {
    for(finds = copyright_begin(copy); finds != copyright_end(copy); finds++)
    {
      copy_entry entry = *finds;

      if(verbose)
      {
        fprintf(mout, "=== %s ==============================================\n",
            (char*)pair_first(current_file));
        fprintf(mout, "DICT: %s\nNAME: %s\nTEXT[%s]\n",
            copy_entry_dict(entry),
            copy_entry_name(entry),
            copy_entry_text(entry));
      }

      if(*(int*)pair_second(current_file) >= 0)
      {
        /* ensure legal sql */
        escape_string(pgConn, buf, copy_entry_text(entry), sizeof(buf));

        /* get the hash for the string */
        sprintf(hash, "0x%lx", hash_string(copy_entry_text(entry)));

        /* place the copyright in the table */
        memset(sql, '\0', sizeof(sql));
        snprintf(sql, sizeof(sql), "%ld\t%d\t%d\t%d\t%s\t%s\t%s\n",
            agent_pk,
            *(int*)pair_second(current_file),
            copy_entry_start(entry),
            copy_entry_end(entry),
            buf,
            hash,
            copy_entry_type(entry));

        fo_sqlCopyAdd(sqlcpy, sql);
      }
      else
      {
        fprintf(cout, "\t[%d:%d:%s] '%s'",
            copy_entry_start(entry), copy_entry_end(entry),
            copy_entry_type(entry), copy_entry_text(entry));
        if(copy_entry_text(entry)[strlen(copy_entry_text(entry)) - 1] != '\n')
        {
          fprintf(cout, "\n");
        }
      }
    }
  }

  fclose(input_fp);
  fo_scheduler_heart(1);
}

/* ************************************************************************** */
/* **** Database Functions ************************************************** */
/* ************************************************************************** */

/**
 * @brief Sets up the tables for the copyright agent within the database
 *
 * this will create the copyright table and everything that is related to the
 * copyright table.
 *
 * @param the connection to the database
 */
int setup_database(PGconn* pgConn)
{
  /* locals */
  int exists = 0;     // whether any piece of the table already exists
  PGresult* pgResult; // the result from a database access

  /* initialize memory */
  pgResult = NULL;

  /* start by creating the copyright sequence */
  pgResult = PQexec(pgConn, create_database_sequence);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    if(strcmp(PQresultErrorMessage(pgResult), "relation \"copyright_ct_pk_seq\" already exists"))
    {
      fprintf(cerr, "ERROR %s.%d: Could not create copyright_ct_pk_seq.\n", __FILE__, __LINE__);
      fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
      fprintf(cerr, "ERROR sql was: %s\n", create_database_sequence);
      return -1;
    }
    else
    {
      exists = 1;
    }
  }
  PQclear(pgResult);

  /* if necessary change the owner of the copyright table */
  if(!exists)
  {
    pgResult = PQexec(pgConn, alter_database_table);
    if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
    {
      fprintf(cerr, "ERROR %s.%d: Could not alter copyrght_ct_pk_seq.\n", __FILE__, __LINE__);
      fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
      fprintf(cerr, "ERROR sql was: %s\n", alter_database_table);
      return -1;
    }
  }
  PQclear(pgResult);

  /* create the copyright database table */
  pgResult = PQexec(pgConn, create_database_table);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    if(strcmp(PQresultErrorMessage(pgResult), "relation \"copyright_ct_pk_seq\" already exists"))
    {
      fprintf(cerr, "ERROR %s.%d: Could not create table copyright.\n", __FILE__, __LINE__);
      fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
      fprintf(cerr, "ERROR sql was: %s\n", create_database_table);
      return -1;
    }
  }
  PQclear(pgResult);

  /* create the pfile foreign key index */
  pgResult = PQexec(pgConn, create_pfile_foreign_index);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    fprintf(cerr, "ERROR %s.%d: Could not create copyright pfile_fk.\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
    fprintf(cerr, "ERROR sql was: %s\n", create_pfile_foreign_index);
    return -1;
  }
  PQclear(pgResult);

  /* create the agent foreign key index */
  pgResult = PQexec(pgConn, create_agent_foreign_index);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    fprintf(cerr, "ERROR %s.%d: Could not create copyright agent_fk.\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
    fprintf(cerr, "ERROR sql was: %s\n", create_agent_foreign_index);
    return -1;
  }
  PQclear(pgResult);

  /* alter the owner of the copyright table */
  pgResult = PQexec(pgConn, alter_copyright_owner);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    fprintf(cerr, "ERROR %s.%d: Could not change the onwer of the copyright table.\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
    fprintf(cerr, "ERROR sql was: %s\n", alter_copyright_owner);
    return -1;
  }
  PQclear(pgResult);

  /* alter pfile_fk */
  pgResult = PQexec(pgConn, alter_table_pfile);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    fprintf(cerr, "ERROR %s.%d: Could not alter pfile_fk in copyright table.\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR PQ error message: %s.\n", PQresultErrorMessage(pgResult));
    fprintf(cerr, "ERROR sql was: %s\n", alter_table_pfile);
    return -1;
  }
  PQclear(pgResult);

  /* alter agent_fk */
  pgResult = PQexec(pgConn, alter_table_agent);
  if(PQresultStatus(pgResult) != PGRES_COMMAND_OK)
  {
    fprintf(cerr, "ERROR %s.%d: Could not alter agent_fk in copyright table.\n", __FILE__, __LINE__);
    fprintf(cerr, "ERROR PQ error message %s.\n", PQresultErrorMessage(pgResult));
    fprintf(cerr, "ERROR sql was: %s\n", alter_table_agent);
    return -1;
  }
  PQclear(pgResult);

  return 1;
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
  /* local variables */
  PGresult* pgResult; // the result of the database access
  int ret;            // the value returned by this function
  char* str;          // the string error message if the database access fails
  char buffer[256];   // a buffer used for string manipulation

  /* initialize memory and do the sql access */
  ret = 1;
  str = NULL;
  memset(buffer, '\0', sizeof(buffer));
  pgResult = PQexec(pgConn, check_database_table);

  /* check if the database already exists */
  if(PQresultStatus(pgResult) != PGRES_TUPLES_OK)
  {
    str = PQresultErrorMessage(pgResult);
    if(longest_common(buffer, str, "does not exist") == 14)
    {
      fprintf(cerr, "WARNING %s.%d: Could not find copyright table.", __FILE__, __LINE__);
      ret = setup_database(pgConn);
    }
    else
    {
      fprintf(cerr, "ERROR %s.%d: problem with copyright table\n", __FILE__, __LINE__);
      fprintf(cerr, "ERROR PQ error message: %s\n", PQresultErrorMessage(pgResult));
      fprintf(cerr, "ERROR sql was: %s\n", check_database_table);
      ret = 0;
    }
    free(str);
  }

  /* check if the copyright exsits */
  pgResult = PQexec(pgConn, check_copyright_ars);
  if(PQresultStatus(pgResult) != PGRES_TUPLES_OK && PQntuples(pgResult) != 1)
  {
    fo_CreateARSTable(pgConn, AGENT_ARS);
  }

  /* clean up memory and return */
  PQclear(pgResult);
  return ret;
}

/* ************************************************************************** */
/* **** Main Functions ****************************************************** */
/* ************************************************************************** */

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
  fprintf(cout, "  -C {filename} :: Scan {filename} from command line. Does not write to database.\n");
  fprintf(cout, "  -t  :: Run the accuracy tests, nothing written to database.\n");
  fprintf(cout, "NOTE: -i, -c, and -t cause the agent to perform the request\n");
  fprintf(cout, "       and then exit without waiting for scheduler input\n");
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
 *     $ ./copyright -c myfiletoscan
 *
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 *
 * To run the copyright agent as an agent simply run with no command line args
 *   -i                 :: initialize a connection to the database
 *   -d                 :: turn on debugging information
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
 * 2. False_Positives: contains all of the false positives found by the agent,
 *             information in the file includes the file the false positive was
 *             in, the dictionary match, the name match, and the text
 * 3. Flase_Negatives: contains all of the false negatives found by the agent,
 *             information in the file includes the file the false negative was
 *             in, and the text of the false negative
 *
 * NOTE: -d will produces the exact same style of Matches file that the accuracy
 *       testing does. Currently this is the only thing that -d will produce
 *
 * @param argc the number of command line arguments
 * @param argv the command line arguments
 * @return 0 on a successful program execution
 */
int main(int argc, char** argv)
{
  /* primitives */
  char sql[512];                // buffer for database access
  int c, i = -1;                // temporary int containers
  int num_files = 0;            // the number of rows in a job
  int ars_pk = 0;               // the args primary key
  long upload_pk = 0;           // the upload primary key
  long agent_pk = 0;            // the agents primary key
  char *DBConfFile = NULL;      /* use default Db.conf */
  char *ErrorBuf;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[myBUFSIZ];
  char copy_buf[FILENAME_MAX];
  char name_buf[FILENAME_MAX];

  /* Database structs */
  PGconn* pgConn = NULL;        // the connection to Database
  PGresult* pgResult = NULL;    // result of a database access

  /* copyright structs */
  copyright copy;               // the work horse of the copyright agent
  pair curr;                    // pair to push into the file list

  /* verbose data */
  FILE* mout = NULL;

  /* set the output streams */
  cout = stdout;
  cerr = stdout;
  cin = stdin;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv);

  /* initialize complex data strcutres */
  memset(copy_buf, '\0', sizeof(copy_buf));
  memset(name_buf, '\0', sizeof(copy_buf));
  snprintf(copy_buf, sizeof(copy_buf),
      "%s/mods-enabled/copyright/agent/copyright.dic",
      sysconfigdir);
  snprintf(name_buf, sizeof(name_buf),
      "%s/mods-enabled/copyright/agent/names.dic",
      sysconfigdir);

  if(!copyright_init(&copy, copy_buf, name_buf))
  {
    fprintf(cerr, "FATAL %s.%d: copyright initialization failed\n", __FILE__, __LINE__);
    fprintf(cerr, "FATAL %s\n", strerror(errno));
    fflush(cerr);
    return -1;
  }

  /* parse the command line options */
  while((c = getopt(argc, argv, "dc:C:ti")) != -1)
  {
    switch(c)
    {
      case 'd': /* debugging */
        mout = fopen("Matches", "w");
        if(!mout)
        {
          fprintf(cerr, "ERROR could not open Matches for logging\n");
          fflush(cerr);
        }
        else
        {
          verbose = 1;
        }
        break;
      case 'C': /* run from command line */
        pair_init(&curr, string_function_registry(), int_function_registry());

        pair_set_first(curr, optarg);
        pair_set_second(curr, &i);
        perform_analysis(pgConn, copy, curr, agent_pk, mout);
        num_files++;

        pair_destroy(curr);
        break;
      case 't': /* run accuracy testing */
        run_test_files(copy);
        copyright_destroy(copy);
        return 0;
      case 'i': /* initialize database connections */
        pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
        if(!pgConn) {
          fprintf(cerr, "FATAL %s.%d: Copyright agent unable to connect to database.\n", __FILE__, __LINE__);
          exit(-1);
        }
        copyright_destroy(copy);
        PQfinish(pgConn);
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
    /* open the database */
    pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
    if(!pgConn)
    {
      fprintf(cerr, "FATAL: %s.%d: Copyright agent unable to connect to database.\n", __FILE__, __LINE__);
      exit(-1);
    }

    /* create the sql copy structure */
    sqlcpy = fo_sqlCopyCreate(pgConn, "copyright", 32768, 7,
        "agent_fk", "pfile_fk", "copy_startbyte", "copy_endbyte", "content", "hash", "type");

    /* book keeping */
    pair_init(&curr, string_function_registry(), int_function_registry());
    db_connected = 1;
    SVN_REV = fo_sysconfig("copyright", "SVN_REV");
    VERSION = fo_sysconfig("copyright", "VERSION");
    sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);
    agent_pk = fo_GetAgentKey(pgConn, AGENT_NAME, 0, agent_rev, AGENT_DESC);

    /* make sure that we are connected to the database */
    if(!check_copyright_table(pgConn))
    {
      exit(-1);
    }

    /* enter the main agent loop */
    while(fo_scheduler_next())
    {
      upload_pk = atol(fo_scheduler_current());
      ars_pk = fo_WriteARS(pgConn, 0, upload_pk, agent_pk, AGENT_ARS, NULL, 0);

      sprintf(sql, fetch_pfile, upload_pk, agent_pk);
      pgResult = PQexec(pgConn, sql);
      num_files = PQntuples(pgResult);

      for(i = 0; i < num_files; i++)
      {
        c = atoi(PQgetvalue(pgResult, i, PQfnumber(pgResult, "pfile_pk")));
        pair_set_first(curr, PQgetvalue(pgResult, i, PQfnumber(pgResult, "pfilename")));
        pair_set_second(curr, &c);
        perform_analysis(pgConn, copy, curr, agent_pk, mout);
      }

      fo_WriteARS(pgConn, ars_pk, upload_pk, agent_pk, AGENT_ARS, NULL, 1);
      PQclear(pgResult);
    }

    pair_destroy(curr);
  }

  if(db_connected)
  {
    fo_sqlCopyDestroy(sqlcpy, 1);
    PQfinish(pgConn);
  }

  if(verbose)
  {
    fclose(mout);
  }

  copyright_destroy(copy);
  fo_scheduler_disconnect(0);

  return 0;
}

