/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * \file utils.c
 * \brief Miscellaneous utility functions for maintagent
 */

#include "maintagent.h"

/**********  Globals  *************/
extern PGconn    *pgConn;        // database connection


/**
 * @brief Exit function.  This does all cleanup and should be used
 *        instead of calling exit() or main() return.
 *
 * @param ExitVal Exit value
 * @returns void Calls exit()
 */
FUNCTION void ExitNow(int ExitVal) 
{
  if (pgConn) PQfinish(pgConn);

  if (ExitVal) LOG_ERROR("Exiting with status %d", ExitVal);

  fo_scheduler_disconnect(ExitVal);
  exit(ExitVal);
} /* ExitNow() */
