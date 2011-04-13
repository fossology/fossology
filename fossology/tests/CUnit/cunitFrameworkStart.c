/*
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
 */

/**
 * cunitFrameworkStart.c
 * \brief cunit test framework start
 *
 *  Created on: Aug 9, 2010
 *      Author: larry
 * @version "$Id: cunitFrameworkStart.c 3368 2010-08-10 03:08:09Z larry $"
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <assert.h>
#include <sys/dir.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>
#include <libxml/parser.h>
#include <libxml/tree.h>
#include <time.h>

#define LINE 1024

static char CommandResult[1024];
static int Count = 0;

/**
 * @brief utility function, trim the string
 *
 * @param source string & destination string
 * @return success & error code, 0: ok, other: error
 */
static int Trim(char *Sentence)
{
  if (NULL == Sentence || "" == Sentence)
  {
    return 1;
  }
  char *Temp;
  if (Sentence)
  {
    Temp = Sentence + strlen(Sentence) - 1;
    while(*Sentence && isspace(*Sentence)) Sentence++;
    while(Temp > Sentence && isspace(*Temp)) *Temp-- = '\0';
  }
  return 0;
}

/**
 * @brief utility function, run commands
 *
 * @param commands

 * CommandResult is the execute result
 * @return success & error code, 0: ok, other: error
 */
static int CommandExecute(char *Command)
{
  FILE *stream;  
  char buf[1024]; 
     
  memset(CommandResult, '\0', sizeof(CommandResult)); 
  stream = popen(Command, "r" ); 
  fread(CommandResult, sizeof(char), sizeof(CommandResult),  stream); 

  pclose(stream);
  return 0;
}

/**
 * @brief parse the results(one array) from running commands to TwoDimensionalArray
 *
 * @param Array: source array
 * @param Token: token
 * @param TwoDimensionalArray: split result

 * @return success & error code, 0: ok, other: error
 */
static int Array2DimensionalArray(char *Array, char *Token, char TwoDimensionalArray[][1024])
{
  Count = 0;
  char temp[1024];
  char *haystack = CommandResult;
  char *needle= Token;
  char* buf = strstr( haystack, needle);
  
  while( buf != NULL )
  {
    strncpy(temp, haystack, buf-haystack);
    temp[buf-haystack] =0;
    Trim(temp);
    memset(TwoDimensionalArray[(Count)], '\0', sizeof(TwoDimensionalArray[(Count)]));
    memcpy(TwoDimensionalArray[(Count)++], temp, strlen(temp));
    haystack = buf + strlen(needle);

    buf = strstr( haystack, needle);
  }

  return 0;
}
/* suites */
long SuitesTotal = 0;
long SuitesRun = 0;
long SuitesSucceeded = 0;
long SuitesFailed = 0;

/* test cases */
long TestCasesTotal = 0;
long TestCasesRun = 0;
long TestCasesSucceeded = 0;
long TestCasesFailed = 0;

/* assertions  */
long AssertionsTotal = 0;
long AssertionsRun = 0;
long AssertionsSucceeded = 0;
long AssertionsFailed = 0;

char *ResultListing = NULL;
const char *CUNIT_RESULT_LISTING = "CUNIT_RESULT_LISTING";
//const char *SLASH_CUNIT_RESULT_LISTING = "\/CUNIT_RESULT_LISTING";

static int GetIndividualResultDir(char Path[], int Length)
{
  int i, j = i = 0;
  for (i = 0; i < Length; i++)
  {
    if ('/' ==Path[i]) j = i;
  }
  
  Path[j] = '\0';
  
  return 0;
}

/**
 * @brief extract cunit result listing from individual xx.xml
 *
 * @param FileName

 * ResultListing stores all cunit result listing 
 * @return success & error code, 0: ok, other: error
 */
static int ExtractCunitResultListing(const char FileName[1024])
{
  FILE *TestFile = NULL;
  char FileNameTemp[1024];
  
  TestFile = fopen(FileName, "r");
  if ((TestFile == NULL))
  {
    fclose(TestFile);
    return 1;
  }
  strcpy(FileNameTemp, FileName);
  
  char *LineContent = NULL;
  LineContent = (char*)malloc(LINE*sizeof(char));
  int CaptureFlag = 0;
  GetIndividualResultDir(FileNameTemp, strlen(FileNameTemp));
  
  strcat(ResultListing, "    <CUNIT_RUN_SUITE> \n");
  strcat(ResultListing, "      <CUNIT_RUN_SUITE_SUCCESS> \n");
  strcat(ResultListing, "        <SUITE_NAME>");
  strcat(ResultListing, "Test File Directory Is:");
  strcat(ResultListing, FileNameTemp);
  strcat(ResultListing, "        </SUITE_NAME> \n");
  strcat(ResultListing, "      </CUNIT_RUN_SUITE_SUCCESS> \n");
  strcat(ResultListing, "    </CUNIT_RUN_SUITE> \n");

  while(1) 
  {
    char *Line = fgets(LineContent, 1024, TestFile);
    
    if(!Line)
    {
      break;
    }
    else
    {
      if (NULL != strstr(LineContent, CUNIT_RESULT_LISTING) && 0 == CaptureFlag)
      { 
        CaptureFlag = 1;
      } else if (NULL != strstr(LineContent, CUNIT_RESULT_LISTING) && 1 == CaptureFlag)
      {
        CaptureFlag = 0;
      }
      if (1 == CaptureFlag && NULL == strstr(LineContent, CUNIT_RESULT_LISTING))
      {
        strcat(ResultListing, LineContent);
      }
    }
 }
  free(LineContent);
  pclose(TestFile);
  return 0;
}

const char *TOTAL = "TOTAL";
const char *RUN = "RUN";
const char *SUCCEEDED = "SUCCEEDED";
const char *FAILED = "FAILED";
int Flag = 0;

/**
 * @brief walk one xx.xml, calculate the number of SuitesTotal, SuitesRun, SuitesSucceeded, SuitesFailed, TestCasesTotal, 
 * TestCasesTotal, long TestCasesRun, TestCasesSucceeded, TestCasesFailed, AssertionsTotal, AssertionsRun, AssertionsSucceeded, AssertionsFailed
 * @param one node

 * ResultListing stores all cunit result listing
 * @return success & error code, 0: ok, other: error
 */
static void WalkTree(xmlNode *a_node)
{
  xmlNode *cur_node = NULL;
  long number = 0;
  for (cur_node = a_node; cur_node; cur_node = cur_node->next) 
  {
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, TOTAL) && 0 == Flag)
    {
       number = atol(cur_node->content);
       SuitesTotal += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, RUN) && 0 == Flag)
    {
      number = atof(cur_node->content);
      SuitesRun += number;
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, SUCCEEDED) && 0 == Flag)
    {
       number = atof(cur_node->content);
      SuitesSucceeded += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, FAILED) && 0 == Flag)
    {
      number = atof(cur_node->content);
      SuitesFailed += number;    
      Flag = 1;
    } else

    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, TOTAL) && 1 == Flag)
    {
      number = atof(cur_node->content);
      TestCasesTotal += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, RUN) && 1 == Flag)
    {
      number = atof(cur_node->content);
      TestCasesRun += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, SUCCEEDED) && 1 == Flag)
    {
      number = atof(cur_node->content);
      TestCasesSucceeded += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, FAILED) && 1 == Flag)
    {
      number = atof(cur_node->content);
      TestCasesFailed += number;    
      Flag = 2;
    } else

    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, TOTAL) && 2 == Flag)
    {
      number = atof(cur_node->content);
      AssertionsTotal += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, RUN) && 2 == Flag)
    {
      number = atof(cur_node->content);
      AssertionsRun += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, SUCCEEDED) && 2 == Flag)
    {
      number = atof(cur_node->content);
      AssertionsSucceeded += number;    
    } else
    if (NULL != cur_node->parent && NULL != cur_node->parent->name && !strcmp(cur_node->parent->name, FAILED) && 2 == Flag)
    {
      number = atof(cur_node->content);
      AssertionsFailed += number;    
      Flag = 0;
    }
    
    WalkTree(cur_node->children);
  }
}

/**
 * @brief extract cunit run summary from individual xx.xml
 *
 * @param FileName

 * ResultListing stores all cunit result listing
 * @return success & error code, 0: ok, other: error
 */
static int ExtractCunitRunSummary(const char FileName[1024])
{
  xmlDoc *doc = NULL;
  xmlNode *root_element = NULL;  
 
  doc = xmlReadFile(FileName, NULL, 0);
 
  if (doc == NULL)
  {
    printf("error: could not parse file %s\n", FileName);
  } else 
  {
    /* Get the root element node */
    root_element = xmlDocGetRootElement(doc);
    WalkTree(root_element);
 
    /* free the document */
    xmlFreeDoc(doc);
  }
  /*
   *Free the global variables that may
   *have been allocated by the parser.
  */
  xmlCleanupParser();
  return 0;
}

/**
 * @brief if install cunit?
 * @return 1: installed, other: not installed
 */
static int InstallCUnitJudge()
{
  FILE * file;
  if (file = fopen("/usr/lib/libcunit.a", "r"))
  {
    fclose(file);
    return 1;
  } else 
  {
    return 0;
  }
}

/**
 * @brief prepare for unit testing, change the dir, execute make test or make coverage
 *
 * @param ProductList

 * @return success & error code, 0: ok, other: error
 */
static int Preparing(char *ProductList)
{
  char Dir[LINE];
  getcwd(Dir, LINE);
  char TwoDimensionalArray[LINE][LINE];
  printf("CurrentDir is: %s\n", Dir);
  int status = InstallCUnitJudge();
  
  if (1 != status) 
  {
    printf("CUnit is not installed, please install it first!!\n");
    exit(-1);
  }
  
  chdir("../../");
  system("make clean");
  system("make coverage");
  chdir("./tests/CUnit");
  rmdir("results");
  return 0;
}

/**
 * @brief get test result, invoke ExtractCunitResultListing & ExtractCunitRunSummary for each xx.xml
 *
 * @param ProductList

 * @return success & error code, 0: ok, other: error
 */
static int GetTestResult(char *ProductList)
{
  char TwoDimensionalArray[LINE][LINE];
  Preparing(ProductList);
  
  char *CollectResultCommand = "find ../../ -path ../../tests -prune -o -name \"*Results.xml\" -exec ls {} \\;";
  CommandExecute(CollectResultCommand);
  Array2DimensionalArray(CommandResult, "\n", TwoDimensionalArray);
  int i = 0;
  for (i = 0; i < Count; i++)
  {
    ExtractCunitResultListing(TwoDimensionalArray[i]); 
    ExtractCunitRunSummary(TwoDimensionalArray[i]);
  }

  return 0;
}

static FILE*  f_pTestResultFile = NULL; /**< FILE pointer the test results file. */

/**
 * @brief generate the test result 
 *
 * @param test result file name

 * @return success & error code, 0: ok, other: error
 */
static int Print2File(char *TestResultFilename)
{
  FILE*  f_pTestResultFile = NULL; /**< FILE pointer the test results file. */

  if (NULL == (f_pTestResultFile = fopen(TestResultFilename, "w")))
  { 
     return 1;
  }
  
  /* header */
  fprintf(f_pTestResultFile,
            "<?xml version=\"1.0\" ?> \n"
            "<?xml-stylesheet type=\"text/xsl\" href=\"CUnit-Run.xsl\" ?> \n"
            "<!DOCTYPE CUNIT_TEST_RUN_REPORT SYSTEM \"CUnit-Run.dtd\"> \n"
            "<CUNIT_TEST_RUN_REPORT> \n"
            "  <CUNIT_HEADER/> \n");
  
  /* cunit run summary */
  fprintf(f_pTestResultFile,
          "  <CUNIT_RUN_SUMMARY> \n"
          "    <CUNIT_RUN_SUMMARY_RECORD> \n"
          "      <TYPE> Suites </TYPE> \n"
          "      <TOTAL> %ld</TOTAL> \n"
          "      <RUN> %ld </RUN> \n"
          "      <SUCCEEDED> -NA- </SUCCEEDED> \n"
          "      <FAILED> %ld </FAILED> \n"
          "    </CUNIT_RUN_SUMMARY_RECORD> \n",
          SuitesTotal,
	  SuitesRun,
	  SuitesFailed
          );

  fprintf(f_pTestResultFile,
          "    <CUNIT_RUN_SUMMARY_RECORD> \n"
          "      <TYPE> Test Cases </TYPE> \n"
          "      <TOTAL> %ld </TOTAL> \n"
          "      <RUN> %ld </RUN> \n"
          "      <SUCCEEDED> %ld </SUCCEEDED> \n"
          "      <FAILED> %ld </FAILED> \n"
          "    </CUNIT_RUN_SUMMARY_RECORD> \n",
          TestCasesTotal ,
	  TestCasesRun ,
          TestCasesSucceeded ,
	  TestCasesFailed
          );

 fprintf(f_pTestResultFile,
          "    <CUNIT_RUN_SUMMARY_RECORD> \n"
          "      <TYPE> Assertions </TYPE> \n"
          "      <TOTAL> %ld </TOTAL> \n"
          "      <RUN> %ld </RUN> \n"
          "      <SUCCEEDED> %ld </SUCCEEDED> \n"
          "      <FAILED> %ld </FAILED> \n"
          "    </CUNIT_RUN_SUMMARY_RECORD> \n"
          "  </CUNIT_RUN_SUMMARY> \n",
          AssertionsTotal ,
          AssertionsRun ,
          AssertionsSucceeded ,
          AssertionsFailed
          );

  /* cunit result listing */
  fprintf(f_pTestResultFile,"  <CUNIT_RESULT_LISTING> \n");
  fprintf(f_pTestResultFile, ResultListing);
  fprintf(f_pTestResultFile,"  </CUNIT_RESULT_LISTING> \n");

  /* footer */
  char* szTime;
  time_t tTime = 0;
  time(&tTime);
  szTime = ctime(&tTime);
  fprintf(f_pTestResultFile,
          "  <CUNIT_FOOTER> File Generated By CUnit at %s </CUNIT_FOOTER> \n"
          "</CUNIT_TEST_RUN_REPORT>",
          (NULL != szTime) ? szTime : "");

  return 0;
}

/**
 * @brief get coverage  result
 *
 * @return success & error code, 0: ok, other: error
 */
static int GetCoverageResult()
{
  char *CollectResultCommand = "find ../../ -path ../../tests/CUnit/results -prune -o -name \"results\" -exec cp -r {} ../../tests/CUnit \\;";
  CommandExecute(CollectResultCommand);
  CollectResultCommand = "rm -rf ./results/tests  ./results/index.html";
  CommandExecute(CollectResultCommand);
  return 0;
}

/**
 * @brief copy /usr/local/share/CUnit/CUnit-Run.dtd CUnit-Run.xsl to ./ 
 * in order to open test-Results.xml, requirng the two files
 */
static int CopyFiles()
{
  char *Commands = " /bin/cp /usr/share/CUnit/CUnit-Run* ./";
  CommandExecute(Commands);
  return 0;
}

/**
 * @brief start cuit test 
 *
 * @param RunMode: run mode, one of four interfaces
 * @param ProductList: soure dir
 * @param NeedCoverage: if need coverage, default yes
 * @param ResultDestination: result destination, default ./fossology/test/CUnit
 * @return success & error code, 0: ok, other: error
 */
int CunitTestFramework(char RunMode, char *ProductList, int NeedCoverage, char *ResultDestination)
{
  printf("the parameters: RunMode is:%c,  TestList is: %s,  NeedCoverage is:%d,  ResultDestination is:%s\n",
  RunMode, ProductList, NeedCoverage, ResultDestination);
  ResultListing = (char*)malloc(LINE*LINE*sizeof(char));
  GetTestResult(ProductList);
  GetCoverageResult();
  Print2File(ResultDestination);
  CopyFiles();
  free(ResultListing);
  return 0;
}

