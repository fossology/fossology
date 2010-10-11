/*
 * libfossscheduler.h
 *
 *  Created on: Oct 8, 2010
 *      Author: norton
 */

#ifndef LIBFOSSSCHEDULER_H_INCLUDE
#define LIBFOSSSCHEDULER_H_INCLUDE

/* local includes */

/* library includes */
#include <stdio.h>

// connects an agent to the scheduler
void scheduler_connect();
// closes the connection to the scheduler
void scheduler_disconnect();
// gets the next file to be analyzed
FILE* scheduler_next();
// gets the pfile_pk associated with the currect file
int scheduler_pfile_pk();

#endif /* LIBFOSSSCHEDULER_H_INCLUDE */
