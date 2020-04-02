/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2019 Siemens AG

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
 * \file
 * \brief Miscellaneous utility functions for maintagent
 */

#include "maintagent.h"

/**
 * @brief Exit function.  This does all cleanup and should be used
 *        instead of calling exit() or main() return.
 *
 * @param exitVal Exit value
 * @returns void Calls exit()
 */
FUNCTION void exitNow(int exitVal)
{
  if (pgConn) PQfinish(pgConn);
  if (dbManager) fo_dbManager_free(dbManager);

  if (exitVal) LOG_ERROR("Exiting with status %d", exitVal);

  fo_scheduler_disconnect(exitVal);
  exit(exitVal);
} /* ExitNow() */
