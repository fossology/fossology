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
/* local includes */
#include <libfossscheduler.h>

/* library includes */
#include <sys/file.h>
#include <string.h>
#include <stdlib.h>

#define LOCK 1        ///< constant for lock_unlock
#define UNLOCK 0      ///< constant for lock_unlock
#define BLOCK_SIZE 10 ///< the number of files in each block

/* local globals */
struct flock lock_params;         ///< the locking parameters that will be passed to fcntl()
int agent_byte_offset;            ///< the byte offset for this particular agent
int files_in_job;                 ///< the total number of files to analyze
int file_index;                   ///< the index in current_file that was returned last
int current_file_pk[BLOCK_SIZE];  ///< the primary keys associated with each file
FILE* job_info;                   ///< the file that contains all of the job information
FILE* current_file[BLOCK_SIZE];   ///< the file that is currently open for analysis

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * Looks or unlocks the job_info file. This function will block until all
 * conflicting locks held by other processes are released. This is to make sure
 * that the information in job_info is consitent.
 *
 * @param direction when the file is being locked or unlcoked
 */
void lock_unlock(int direction) {
  if(direction) lock_params.l_type = F_WRLCK;
  else lock_params.l_type = F_UNLCK;
  fcntl(fileno(job_info), F_SETLKW, &lock_params);
}

/* ************************************************************************** */
/* **** Global Functions **************************************************** */
/* ************************************************************************** */


/**
 * Function to establish a connection between an agent and the scheduler.
 *
 * Steps taken by this function:
 *   - initialize memory associated with agent connection
 *   - send "SPAWNED" to the scheduler
 *   - receive job info
 *    - the job information filename
 *    - the offset into the job information file for this agents information
 *   - check the nfs mounts for the agent
 *   - open job info
 *   - set up the heartbeat()
 *
 * Making a call to this function should be the first thing that an agent does
 * after parsing its command line arguments.
 */
void scheduler_connect() {
  /* local variables */
  char buffer[FILENAME_MAX];

  /* initialize memory associated with agent connection */
  job_info = NULL;
  memset(current_file   , 0, sizeof(current_file));
  memset(current_file_pk, 0, sizeof(current_file_pk));
  memset(buffer, '\0' , sizeof(buffer));

  lock_params.l_type =    0;                  // this must be set in the function calling fcntl
  lock_params.l_whence =  SEEK_SET;           // start offset start a beginning of file
  lock_params.l_start =   0;                  // there is no offset, start at l_whence
  lock_params.l_len =     0;                  // read from l_whence + l_start to end of file
  lock_params.l_pid =     getpid();           // this is process that is requesting the lock

  /* send "SPAWNED to the scheduler */
  fprintf(stdout, "SPAWNED");

  /* receive job info */
  fscanf(stdin, "%s %d\n", buffer, &agent_byte_offset);

  /* check the nfs mounts for the agent */
  job_info = fopen(buffer, "r+b");

  /* set up the heartbeat() */
  // TODO
}

/**
 * Function to cleanup the connection between an agent and the scheduler
 *
 * Steps taken by this function:
 *   - close the job info file
 *   - send "CLOSED" to the scheduler
 *   - return or call exit(0)
 *      TODO determine if this function should return or if the agent is
 *           expected to clean up its memory before calling scheduler_disconnect
 *
 * Making a call to this function should be the last thing that an agent does
 * before exiting
 */
void scheduler_disconnect() {
  /* close the job info file */
  fclose(job_info);

  /* send "CLOSED" to the scheduler */
  fprintf(stdout, "CLOSED");

  /* call exit(0) */
  exit(0);
}

/**
 * Function to get the next file that an agent should analyze. This will clean
 * up any file opened and any memory allocated by the previous call to this
 * function (i.e. the agent should not close the FILE* returned).
 *
 * Steps taken by this function:
 *   - check if a new set of files needs to be grabbed
 *    - TRUE
 *     - lock job information (FILE* or shared memory)
 *     - check the jobs status
 *      - die, block, continue
 *     - clean up the previous set of files
 *      - close associated files
 *      - inform scheduler that part of the job is done
 *     - get the next set of files to analyze
 *      - get the filenames and open the files
 *      - inform the scheduler that this agent is working on this part of the job
 *     - call heartbeat()
 *     - release the info lock
 *    - FALSE
 *     - return the next open file pointer
 *
 * @return a FILE* to the opened file that should be analyzed
 */
FILE* scheduler_next() {
  /* local variables */
  size_t bytes;                   // the number of bytes read or written to a file
  char buffer[FILENAME_MAX];      // string buffer used to recieve info from scheduler
  int status;                     // holds the status of the job read from the file
  int file_number;                // the file number read from job information file
  int file_offset;                // the offset that this agent will read at in job_info
  int number_read;                // the number of files that will be read this interation
  int i;                          // simple counter variable

  if(current_file[++file_index] && file_index < BLOCK_SIZE)
  {
    return current_file[file_index];
  }
  else
  {
    /* lock job information */
    lock_unlock(LOCK);

    /* check the jobs status */
    fseek(job_info, 4, SEEK_SET);
    bytes = fread(&status, sizeof(status), 1, job_info);
    if(bytes != sizeof(unsigned char))
    {
      fprintf(stderr, "FATAL %s.%d: error reading from job information file\n", __FILE__, __LINE__);
      return NULL;
    }

    // the scheduler has instructed this agent to die
    // release the file lock and return a NULL to inform the agent to die
    if(status == KILLED)
    {
      lock_unlock(UNLOCK);
      return NULL;
    }
    // this agent has been premted by a different job
    // release the lock and wait for instructions from the scheduler
    else if(status == PAUSED)
    {
      lock_unlock(UNLOCK);
      fgets(buffer, sizeof(buffer), stdin);

      // check if the scheduler wishes for the process to retart
      if(strcmp(buffer, "RESTART"))
      {
        return NULL;
      }

      // we need to do all the same things for a restart as a normal call to
      // scheduler_next() therefore, just make a recursive call and return its result
      return scheduler_next();
    }

    /* clean up the previous files */
    for(i = 0; i < BLOCK_SIZE; i++)
      if(current_file[i])
        fclose(current_file[i]);
    memset(current_file   , 0, sizeof(current_file));
    memset(current_file_pk, 0, sizeof(current_file_pk));
    memset(buffer, 0, sizeof(buffer));
    // TODO send finish info to scheduler

    /* get the next set of files to analyze */
    fseek(job_info, agent_byte_offset, SEEK_SET);
    bytes = fread(&file_number, sizeof(file_number), 1, job_info);
    bytes = fread(&file_offset, sizeof(file_offset), 1, job_info);
    number_read = files_in_job - file_number < BLOCK_SIZE ? files_in_job - file_number : BLOCK_SIZE;
    fseek(job_info, file_offset, SEEK_SET);

    /* inform the scheduler of the files that will be analyzed */
    fprintf(stdout, "UPDATE: %d %d", file_number, file_number + file_offset);

    for(i = 0; i < number_read; i++) {
      bytes = fread(&current_file_pk[i], sizeof(int), 1, job_info);
      bytes = fread(&status, sizeof(status), 1, job_info);
      bytes = fread(buffer, sizeof(char), status, job_info);
      current_file[i] = fopen(buffer, "rb");
    }

    file_offset = ftell(job_info);
    file_number += number_read;
    fseek(job_info, agent_byte_offset, SEEK_SET);
    bytes = fwrite(&file_number, sizeof(file_number), 1, job_info);
    bytes = fwrite(&file_offset, sizeof(file_offset), 1, job_info);

    /* call the heartbeat() */
    // TODO

    /* release the info lock */
    lock_unlock(UNLOCK);
    return current_file[file_index];
  }
}

/**
 * Function to retrieve the pfile_pk associated with the currently opened file.
 * This number will only change when a call to scheduler_next() is made. If this
 * function is called before a call to scheduler_next is made, this will return
 * -1 as a place holder.
 *
 * @return the pfile_pk associated with the currect file
 */
int scheduler_pfile_pk() {
  return current_file_pk[file_index];
}

