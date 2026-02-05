/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
 */

/* Adapter layer to allow maintagent2 to use alternate DB helpers without
 * modifying the original libfossdb. Keeps a thin wrapper around selected
 * functions so maintagent2 can link the adapter-only implementation.
 */
#ifndef LIBFOSSDB_ADAPTER_H
#define LIBFOSSDB_ADAPTER_H

#include <libpq-fe.h>

/**
 * @brief Create a session-local temporary table and COPY newline-separated ids into it.
 *
 * This helper is used by maintagent2 to populate a server-side temp table
 * named by `tempTableName` with newline-separated IDs provided in `ids`.
 * The function returns 0 on success; on error a non-zero value is returned
 * and an optional error message may be written into `errbuf` (up to
 * `errlen` bytes).
 */
int fo_copy_ids_to_temp_table_adapter(
	PGconn* pgConn,
	const char* ids,
	const char* tempTableName,
	char* errbuf,
	size_t errlen);

#endif /* LIBFOSSDB_ADAPTER_H */
