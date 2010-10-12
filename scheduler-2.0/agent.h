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

agent_init(agent* a);
agent_destroy(agent a);

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

// TODO host agent_host(agent a);
// TODO

#endif /* AGENT_H_INCLUDE */
