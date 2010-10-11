/*
 * libfossscheduler.c
 *
 *  Created on: Oct 8, 2010
 *      Author: norton
 */

// TODO move these into a higher level include
enum job_status {
  RUNNING,
  KILLED,
  PAUSED
};

/* local includes */
#include <libfossscheduler.h>

/* library includes */
#include <sys/file.h>

#define LOCK 1
#define UNLOCK 0

/* local globals */
struct flock lock_params;   ///< the locking parameters that will be passed to fcntl()
FILE* job_info;             ///< the file that contains all of the job information
FILE* current_file;         ///< the file that is currently open for analysis

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
 *   - check the nfs mounts for the agent
 *   - send "SPAWNED" to the scheduler
 *   - receive job info
 *      TODO determine if this is shared memory or info filename
 *   - open job info
 *
 * Making a call to this function should be the first thing that an agent does
 * after parsing its command line arguments.
 */
void scheduler_connect() {
  /* local variables */

  /* initialize memory */
  job_info = NULL;
  current_file = NULL;

  /* set up the locking structure */
  lock_params.l_type =    0;                  // this must be set in the function calling fcntl
  lock_params.l_whence =  SEEK_SET;           // start offset start a beginning of file
  lock_params.l_start =   0;                  // there is no offset, start at l_whence
  lock_params.l_len =     0;                  // read from l_whence + l_start to end of file
  lock_params.l_pid =     getpid();           // this is process that is requesting the lock
}

/**
 * Function to cleanup the connection between an agent and the scheduler
 *
 * Steps taken by this function:
 *   - close the job info (FILE* or shared memory)
 *   - send "CLOSED" to the scheduler
 *   - return or call exit(0)
 *      TODO determine if this function should return or if the agent is
 *           expected to clean up its memory before calling scheduler_disconnect
 *
 * Making a call to this function should be the last thing that an agent does
 * before exiting
 */
void scheduler_disconnect() {
  // TODO write
}

/**
 * Function to get the next file that an agent should analyze. This will clean
 * up any file opened and any memory allocated by the previous call to this
 * function (i.e. the agent should not close the FILE* returned).
 *
 * Steps taken by this function:
 *   - lock job information (FILE* or shared memory)
 *   - check the jobs status
 *      - die, block, continue
 *   - get the name of the next file to analyze
 *   - open the FILE* based on the name retrieved
 *   - incriment heartbeat
 *   - release the info lock
 *   - return FILE*
 *
 * @return a FILE* to the opened file that should be analyzed
 */
FILE* scheduler_next() {
  /* local variables */
  size_t bytes;                   // the number of bytes read or written to a file
  unsigned char status;           // holds the status of the job read from the file
  char buffer[1024];              // string buffer used to recieve info from scheduler

  /* lock job information */
  lock_unlock(LOCK);

  /* check the jobs status */
  bytes = fread(&status, sizeof(unsigned char), 1, job_info);
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
    if(strcmp(buffer, "RESTART")) {
      return NULL;
    }

    // we need to do all the same things for a restart as a normal call to
    // scheduler_next() therefore, just make a recursive call and return its result
    return scheduler_next();
  }



  // release the info lock
  lock_unlock(UNLOCK);
  return current_file;
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
  // TODO write
}

