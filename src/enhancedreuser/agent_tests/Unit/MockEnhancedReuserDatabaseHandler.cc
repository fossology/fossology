/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include "MockEnhancedReuserDatabaseHandler.hpp"

extern "C" {
#include "libfossdbmanager.h"
}

MockEnhancedReuserDatabaseHandler::MockEnhancedReuserDatabaseHandler()
  : EnhancedReuserDatabaseHandler(fo::DbManager(static_cast<fo_dbManager*>(nullptr)))
{
}
