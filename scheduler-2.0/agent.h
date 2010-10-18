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

#ifndef AGENT_H_INCLUDE
#define AGENT_H_INCLUDE

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/** Enum to keep track of the state of an agent */
enum agent_status {
  AG_FAILED,    //!< AG_FAILED   The agent has failed during execution
  AG_CREATED,   //!< AG_CREATED  The agent has been allocated but is not running yet
  AG_SPAWNED,   //!< AG_SPAWNED  The agent has finished allocation but has registered work yet
  AG_RUNNING,   //!< AG_RUNNING  The agent has chosen a set of files to work on and is running
  AG_FINISHED   //!< AG_FINISHED The agent has does not have any more work to do and has finished
};

typedef enum agent_status agent_status;

/**
 * Class to hold all of the information associated with creating a specific
 * type of agent.
 *
 * To create:
 *   meta_agent ma;
 *   meta_agent_init(&ma);
 *
 */
typedef struct meta_agent_internal* meta_agent;

/**
 * Class to hold all of the information associated with an agent.
 *
 * To create:
 *   agent a;
 *   agent_init(&a);
 */
typedef struct agent_internal* agent;

/**
 *
 *
 */
typedef int agent_pk;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void meta_agent_init(meta_agent* ma);
void meta_agent_destroy(meta_agent ma);
void agent_init(agent* a, meta_agent meta_data);
void agent_destroy(agent a);

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

// TODO host agent_host(agent a);

/* ************************************************************************** */
/* **** Modifier Functions ************************************************** */
/* ************************************************************************** */

void agent_fail(agent a);

#endif /* AGENT_H_INCLUDE */
