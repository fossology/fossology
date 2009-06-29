/**************************************************************
 dbapi: Set of generic functions for communicating with a database.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
  
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 **************************************************************/
#ifndef DBAPI_H
#define DBAPI_H

void	DBclose	(void *DB);
void *	DBopen	();	/* returns void *DB handle! */
void *  DBmove  (void *DB); /* move results to a different handle */

int	DBaccess	(void *DB, char *SQL); /* pass an SQL command */
int	DBaccess2   (void *DB, char *SQL); /* pass an SQL command */

/*********************************************************************/
/*********************************************************************/
/** The following functions should be called after DBaccess() == 1. **/
/*********************************************************************/
/*********************************************************************/

char *DBerrmsg	(void *DB);
char *DBstatus	(void *DB);
int	  DBdatasize(void *DB);
int	  DBcolsize	(void *DB);
int	  DBrowsaffected(void *DB);
char *DBgetcolname	(void *DB, int Col);
int	  DBgetcolnum	(void *DB, char *ColName);
char *DBgetvalue	(void *DB, int Row, int Col);
#define	DBgetvaluename(DB,Row,Col)	DBgetvalue(DB,Row,DBgetcolnum(DB,Col))
int	  DBisnull	(void *DB, int Row, int Col);
#endif

