/***************************************************************
 Copyright (C) 2006-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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
/**
 * \file nomos.c
 * \brief Main for the nomos agent
 *
 * Nomos detects licenses and copyrights in a file.  Depending on how it is
 * invoked, it either stores it's findings in the FOSSology data base or
 * reports them to standard out.
 *
 */
/* CDB - What is this define for??? */
#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif /* not defined _GNU_SOURCE */

#include "nomos.h"
#include "nomos_utils.h"

extern licText_t licText[]; /* Defined in _autodata.c */
struct globals gl;
struct curScan cur;
FILE **pFile;
pid_t mainPid = 0; // main process id


int schedulerMode = 0; /**< Non-zero when being run from scheduler */
int Verbose = 0;

#define FUNCTION

#ifdef SVN_REV_S
char BuildVersion[]="nomos build version: " VERSION_S " r(" SVN_REV_S ").\n";
#else
char BuildVersion[] = "nomos build version: NULL.\n";
#endif

/* We're being run from the scheduler */
/* nomos agent starting up in scheduler mode... */
/* \ref http://www.fossology.org/projects/fossology/wiki/Nomos_Test_Cases*/

void arsNomos(cacheroot_t* cacheroot){
  int i;
  int upload_pk = 0;
  int numrows;
  int ars_pk = 0;
  int user_pk = 0;
  char *AgentARSName = "nomos_ars";
  char sqlbuf[1024];
  PGresult *result;

  char *repFile;

  schedulerMode = 1;
  /* get user_pk for user who queued the agent */
  user_pk = fo_scheduler_userID();
  /* read upload_pk from scheduler */
  while (fo_scheduler_next())
  {
    upload_pk = atoi(fo_scheduler_current());
    if (upload_pk == 0)
      continue;
    /* Check Permissions */
    if (GetUploadPerm(gl.pgConn, upload_pk, user_pk) < PERM_WRITE)
    {
      LOG_ERROR("You have no update permissions on upload %d", upload_pk);
      continue;
    }
    /* if it is duplicate request (same upload_pk, sameagent_fk), then do not repeat */
    snprintf(sqlbuf, sizeof(sqlbuf),
        "select ars_pk from nomos_ars,agent \
                where agent_pk=agent_fk and ars_success=true \
                  and upload_fk='%d' and agent_fk='%d'",
        upload_pk, gl.agentPk);
    result = PQexec(gl.pgConn, sqlbuf);
    if (fo_checkPQresult(gl.pgConn, result, sqlbuf, __FILE__, __LINE__))
      Bail(-__LINE__);
    if (PQntuples(result) != 0)
    {
      LOG_NOTICE("Ignoring requested nomos analysis of upload %d - Results are already in database.", upload_pk);
      PQclear(result);
      continue;
    }
    PQclear(result);
    /* Record analysis start in nomos_ars, the nomos audit trail. */
    ars_pk = fo_WriteARS(gl.pgConn, ars_pk, upload_pk, gl.agentPk, AgentARSName, 0, 0);
    /* retrieve the records to process */
    snprintf(sqlbuf, sizeof(sqlbuf),
        "SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename \
         FROM (SELECT distinct(pfile_fk) AS PF FROM uploadtree WHERE upload_fk='%d' and (ufile_mode&x'3C000000'::int)=0) as SS \
              left outer join license_file on (PF=pfile_fk and agent_fk='%d') inner join pfile on PF=pfile_pk\
         WHERE fl_pk IS null or agent_fk <>'%d'",
        upload_pk, gl.agentPk, gl.agentPk);
    result = PQexec(gl.pgConn, sqlbuf);
    if (fo_checkPQresult(gl.pgConn, result, sqlbuf, __FILE__, __LINE__))
      Bail(-__LINE__);
    numrows = PQntuples(result);
    /* process all files in this upload */
    for (i = 0; i < numrows; i++)
    {
      initializeCurScan(&cur);
      strcpy(cur.pFile, PQgetvalue(result, i, 1));
      cur.pFileFk = atoi(PQgetvalue(result, i, 0));
      repFile = fo_RepMkPath("files", cur.pFile);
      if (!repFile)
      {
        LOG_FATAL("Nomos unable to open pfile_pk: %ld, file: %s", cur.pFileFk, cur.pFile);
        Bail(-__LINE__);
      }
      /* make sure this is a regular file, ignore if not */
      if (!isFILE(repFile))
        continue;
      processFile(repFile);
      fo_scheduler_heart(1);
      if (recordScanToDB(cacheroot, &cur))
      {
        LOG_FATAL("nomos terminating upload %d scan due to previous errors.", upload_pk);
        Bail(-__LINE__);
      }
      freeAndClearScan(&cur);
    }
    PQclear(result);
    /* Record analysis success in nomos_ars. */
    fo_WriteARS(gl.pgConn, ars_pk, upload_pk, gl.agentPk, AgentARSName, 0, 1);
  }
}

/**
 * \brief list all files(store file paths) in the specified directory
 *
 * \pamram dir_name - directory
 * \param process_count - process count
 */
void list_dir (const char * dir_name, int process_count)
{
  struct dirent *dp;
  DIR *dfd;
  int list_file_count = 0;
  const char *dir = dir_name;

  if ((dfd = opendir(dir)) == NULL)
  {
    fprintf(stderr, "Can't open %s\n", dir);
    return;
  }

  int i = 1;
  char *filename_qfd = NULL; // store one file path
  filename_qfd = (char *)malloc(MAX_FILE_PATH *sizeof(char));
  while ((dp = readdir(dfd)) != NULL)
  {
    struct stat stbuf ;

    /** allocte memory to store the path */
    while ((strlen(dir) + strlen(dp->d_name)) >= i*MAX_FILE_PATH)
    {
      filename_qfd = (char *)realloc(filename_qfd, (++i*MAX_FILE_PATH)*sizeof(char));
    }

    /* get the file path */
    sprintf( filename_qfd , "%s/%s",dir,dp->d_name) ;

    if( stat(filename_qfd,&stbuf ) == -1 )
    {
      LOG_FATAL("Unable to stat file: %s\n",filename_qfd) ;
      continue ;
    }

    if (strcmp (dp->d_name, "..") != 0 && strcmp (dp->d_name, ".") != 0)
    {
      if ((stbuf.st_mode & S_IFMT)  == S_IFDIR)
      {
        list_dir(filename_qfd, process_count);
      }
      else {
        sprintf(filename_qfd, "%s\n", filename_qfd);
        pid_t pid = getpid();
        int file_number = list_file_count%process_count;
        int size = fwrite (filename_qfd, sizeof(char), strlen(filename_qfd), pFile[file_number]);
        list_file_count++;

        if (process_count == list_file_count) list_file_count = 0; // reset list_file_count each cycle

        if (0 == size)
        LOG_NOTICE("file_number, pid, size, filename_qfd, process_count, list_file_count are:%d, %d, %s, %d, %d\n", pid, size, filename_qfd, process_count, list_file_count);
       continue;
      }
    }
  }
  filename_qfd = NULL;
  free(filename_qfd);
}

/** 
 * \brief read line by line, then call processFile to grab license line by line
 * 
 * \param file_number - while temp path file do you want to read and process
 */
void read_file_grab_license(int file_number)
{
  char *line = NULL;
  size_t len = 0;
  ssize_t read;
  char path_file[TEMP_FILE_LEN] = "";

  sprintf(path_file, "/tmp/fossology_file_list_%d.txt", file_number);
  FILE *file = fopen(path_file, "r");
  if (!file) 
  {
    LOG_FATAL("failed to open %s\n", path_file);
    return;
  }
  while ((read = getline(&line, &len, file)) != -1) {
    if (line && line[0])
    {
      int lenth = strlen(line);
      while(isspace(line[lenth - 1])) line[--lenth] = 0;  // right trim
      while(isspace(*line)) ++line;  // left trim
    }
    //line = trim(line);
    initializeCurScan(&cur);
    processFile(line);
  }
  fclose(file);

  if (line) free(line);
}

/**
 * \brief the recursive create process and process grabbing licenses
 *
 * \param proc_num - how many child processes(proc_num - 1) will be created
 */
void myFork(int proc_num) {
  pid_t pid;
  pid = fork();

  if (pid < 0)
  {
    LOG_FATAL("fork failed\n");
  }
  else if (pid == 0) { // chile process, every singe process runs on one temp path file
    read_file_grab_license(proc_num); // grabbing licenses on /tmp/fossology_file_list_%d.txt
    return;
  }
  else if (pid > 0) {
    // if pid != 0, we're in the parent
    // let's call ourself again, decreasing the counter, until it reaches 1.
    if (proc_num > 1) {
      myFork(proc_num - 1);
    }
    else read_file_grab_license(0); // main(parent) process run on /tmp/fossology_file_list_%0.txt
  }
}

int main(int argc, char **argv) 
{
  int i;
  int c;
  int file_count = 0;
  char *cp;
  char sErrorBuf[1024];
  char *agent_desc = "License Scanner";
  char **files_to_be_scanned; /**< The list of files to scan */
  char *SVN_REV = NULL;
  char *VERSION = NULL;
  char agent_rev[myBUFSIZ];
  cacheroot_t cacheroot;
  char *scanning_directory= NULL;
  int process_count = 0;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &(gl.pgConn));
  gl.dbManager = fo_dbManager_new(gl.pgConn);

#ifdef PROC_TRACE
  traceFunc("== main(%d, %p)\n", argc, argv);
#endif /* PROC_TRACE */

#ifdef MEMORY_TRACING
  mcheck(0);
#endif /* MEMORY_TRACING */
#ifdef GLOBAL_DEBUG
  gl.DEEBUG = gl.MEM_DEEBUG = 0;
#endif /* GLOBAL_DEBUG */

  files_to_be_scanned = calloc(argc, sizeof(char *));

  SVN_REV = fo_sysconfig("nomos", "SVN_REV");
  VERSION = fo_sysconfig("nomos", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);

  gl.agentPk = fo_GetAgentKey(gl.pgConn, basename(argv[0]), 0, agent_rev, agent_desc);

  /* Record the progname name */
  if ((cp = strrchr(*argv, '/')) == NULL_STR)
  {
    strncpy(gl.progName, *argv, sizeof(gl.progName));
  }
  else
  {
    while (*cp == '.' || *cp == '/')
      cp++;
    strncpy(gl.progName, cp, sizeof(gl.progName));
  }

  if (putenv("LANG=C") < 0)
  {
    (void) strerror_r(errno, sErrorBuf, sizeof(sErrorBuf));
    LOG_FATAL("Cannot set LANG=C in environment.  Error: %s", sErrorBuf)
    Bail(-__LINE__);
  }

  /* Save the current directory */
  if (getcwd(gl.initwd, sizeof(gl.initwd)) == NULL_STR)
  {
    (void) strerror_r(errno, sErrorBuf, sizeof(sErrorBuf));
    LOG_FATAL("Cannot obtain starting directory.  Error: %s", sErrorBuf)
    Bail(-__LINE__);
  }

  /* default paragraph size (# of lines to scan above and below the pattern) */
  gl.uPsize = 6;

  /* Build the license ref cache to hold 2**11 (2048) licenses.
   This MUST be a power of 2.
   */
  cacheroot.maxnodes = 2 << 11;
  cacheroot.nodes = calloc(cacheroot.maxnodes, sizeof(cachenode_t));
  if (!initLicRefCache(&cacheroot))
  {
    LOG_FATAL("Nomos could not allocate %d cacheroot nodes.", cacheroot.maxnodes)
    Bail(-__LINE__);
  }

  /* Process command line options */
  while ((c = getopt(argc, argv, "VSvhilc:d:n:")) != -1)
  {
    switch (c) {
      case 'c': break; /* handled by fo_scheduler_connect() */
      case 'i':
        /* "Initialize" */
        Bail(0); /* DB was opened above, now close it and exit */
      case 'l':
        /* set long command line output */
        gl.progOpts |= OPTS_LONG_CMD_OUTPUT;
        break;
      case 'v':
        Verbose++; break;
    case 'S':
      gl.progOpts |= OPTS_HIGHLIGHT_STDOUT;
      break;
      case 'V':
        printf("%s", BuildVersion);
        Bail(0);
      case 'd': /* diretory to scan */
        scanning_directory = optarg;
        struct stat dir_sta;
        int ret = stat(scanning_directory, &dir_sta);
        if (-1 == ret || S_IFDIR != (dir_sta.st_mode & S_IFMT))
        {
          if (-1 == ret) printf("stat('%s') error message: %s.\n",scanning_directory, strerror(errno));
          else printf("Warning: '%s' from -d is not a good directory(dir_sta.st_mode & S_IFMT = %o).\n", scanning_directory, dir_sta.st_mode & S_IFMT);
          Usage(argv[0]);
          Bail(-__LINE__);
        }
        break;
      case 'n': /* spawn mutiple processes to scan */
        process_count = atoi(optarg);
        break;
      case 'h':
      default:
        Usage(argv[0]);
        Bail(-__LINE__);
    }
  }

  /* Copy filename args (if any) into array */
  for (i = optind; i < argc; i++)
  {
    files_to_be_scanned[file_count] = argv[i];
    file_count++;
  }

  licenseInit();
  gl.flags = 0;

  if (file_count == 0 && !scanning_directory)
  {
    arsNomos(&cacheroot);
  }
  else
  { /******** Files on the command line ********/
    cur.cliMode = 1;
    //printf("process_count, scanning_directory are:%d, %s\n", process_count, scanning_directory);
    if (scanning_directory) {
      if (process_count < 2) process_count = 2; // the least count is 2, at least has one child process

      pFile = (FILE **)malloc(process_count*(sizeof(FILE*)));
      int i = 0;
      char temp_path_file_name[TEMP_FILE_LEN] = ""; // store file path list
      for(i = 0; i < process_count; i++)
      {
        sprintf(temp_path_file_name, "/tmp/fossology_file_list_%d.txt", i);
        FILE *file = fopen(temp_path_file_name, "r");
        /** check if this file exists or not, delete when existing */
        if (file)
        {
          fclose(file);
          unlink(temp_path_file_name);
        }
        /** get the temp path file distriptors */
        pFile[i] = fopen(temp_path_file_name, "w");
        if (!pFile[i])
        {
          LOG_FATAL("failed to open %s\n", temp_path_file_name);
        }
      }
      /** walk through the specified directory to get all the file(file path) and */
      /** store into mutiple files(/tmp/fossology_file_list_%d.txt */
      /** /tmp/fossology_file_list_0.txt is used by the main process */
      list_dir(scanning_directory, process_count);

      /** after the walking through and strore job is done, close all the temp path file distriptors */
      for(i = 0; i < process_count; i++) fclose(pFile[i]);

      /** create process_count - 1 child processes(please do not forget we always have the main process) */
      if (process_count > 1) {
        mainPid = getpid(); // get main process id
        myFork(process_count - 1);
        int status = 0;
        pid_t wpid = 0;
        if (mainPid == getpid())
        {
          /** wait all processes done. */
          while(1){
            wpid = wait(&status);
            if (-1 == wpid) break;
          }

          /** delete the temp path files */
          for(i = 0; i < process_count; i++)
          {
            sprintf(temp_path_file_name, "/tmp/fossology_file_list_%d.txt", i);
            FILE *file = fopen(temp_path_file_name, "r");
            /** check if this file exists or not, delete when existing */
            if (file)
            {
              fclose(file);
              unlink(temp_path_file_name);
            }
          }
        }

        /** free memeory */
        pFile = NULL;
        free(pFile);
      }
    }
    else {
      if (0 != process_count) 
      {
        printf("Warning: -n {nprocs} ONLY works with -d {directory}.\n");
      }
      for (i = 0; i < file_count; i++) {
        initializeCurScan(&cur);
        processFile(files_to_be_scanned[i]);
        recordScanToDB(&cacheroot, &cur);
        freeAndClearScan(&cur);
      }
    }
  }

  lrcache_free(&cacheroot);  // for valgrind

  /* Normal Exit */
  Bail(0);

  /* this will never execute but prevents a compiler warning about reaching
     the end of a non-void function */
  return (0);
}
