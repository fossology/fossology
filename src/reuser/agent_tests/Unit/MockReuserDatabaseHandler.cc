/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#include "MockReuserDatabaseHandler.hpp"

extern "C" {
#include "libfossdbmanager.h"
}

MockReuserDatabaseHandler::MockReuserDatabaseHandler()
  : ReuserDatabaseHandler(fo::DbManager(static_cast<fo_dbManager*>(nullptr)))
{
}
