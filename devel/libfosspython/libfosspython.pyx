# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
# 
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import signal
import re

cdef extern from "../libfossagent/libfossagent.h":
    void InitHeartbeat()
    void ShowHeartbeat(int Sig)
    void Heartbeat(long NewItemsProcessed)
    int	GetAgentKey	(void *DB, char * agent_name, long Upload_pk, char *svn_rev, char *agent_desc)

def initHeartbeat():
    """
    initHeartbeat()

    Send heartbeat on next alarm.
    Setup signal handlers.
    """
    InitHeartbeat()
    signal.signal(signal.SIGALRM,showHeartbeat)
    signal.alarm(10)
    
def showHeartbeat(sig,frame):
    """
    showHeartbeat(sig,frame)

    Given an alarm signal, display a heartbeat.
    """
    ShowHeartbeat(sig)

def updateHeartbeat(newItemsProcessed):
    """
    updateHeartbeat(newItemsProcessed)

    Update heartbeat counter and items processed.
    
     * where `newItemsProcessed' is an integer.
    """
    Heartbeat(newItemsProcessed)

cdef extern from "libpq-fe.h":
    ctypedef struct PGconn
    ctypedef struct PGresult
    ctypedef struct PQresult
    
    void PQclear(PGresult *res)
    int PQresultStatus(PGresult *res)

ctypedef struct dbapi:
    PGconn *Conn
    PGresult *Res
    int RowsAffected

cdef extern from "../libfossdb/libfossdb.h":
    void *DBopen()
    void DBclose(void *VDB)
    void *DBmove(void *VDB)
    int DBaccess(void *VDB, char *SQL)
    PGresult *DBaccess2(void *VDB, char *SQL)
    char *DBerrmsg(void *VDB)
    char *DBstatus(void *VDB)
    int DBdatasize(void *VDB)
    int DBcolsize(void *VDB)
    int DBrowsaffected(void *VDB)
    char *DBgetcolname(void *VDB, int Col)
    int DBgetcolnum(void *VDB, char *ColName)
    char *DBgetvalue(void *VDB, int Row, int Col)
    int DBisnull(void *VDB, int Row, int Col)

cdef class FossDB:
    """
        FossDB() -> FossDB object

        Creates and handles the database connection between the client code and the fossology database.
    """
    cdef void * DB

    def __new__(self):
        self.DB = DBopen()
        if not self.isconnected():
            self.DB = NULL
            raise Exception('Unable to connect to the database! :(', 'self.DB = DBopen()')

    def __init__(self):
        pass

    def __dealloc__(self):
        DBclose(self.DB)

    def isconnected(self):
        """
        Returns True if a database connection was made.
        """
        if (self.DB == NULL):
            return False
        return True

    def getAgentKey(self, agent_name, svn_rev, agent_desc):
        """
        FossDB.getAgentKey(agent_name, svn_rev, agent_desc) -> int (agent_pk)

        Get the Agent Key from the database.

         * Where `agent_name' is a string describing the agents name.
         * Where `svn_rev' is a string holding the current svn revision.
         * Where `agent_desc' is a string describing the agent.
        """
        return GetAgentKey(self.DB, agent_name, 0, svn_rev, agent_desc)

    def access(self, sql):
        """
        FossDB.access(sql) -> int (error id)

        Write to the DB and read results.
        Returns:
          1 = ok, got results (e.g., SELECT)
          0 = ok, no results (e.g., INSERT)
          -1 = constraint error
          -2 = other error
          -3 = timeout
        NOTE: For a huge DB request, this could take a while
        and could consume all memory.
        Callers should take care to not call unbounded SQL selects.
        (Say "select limit 1000" or something.)

         * where `sql' is a string.
        """
        return DBaccess(self.DB, sql)

    def access2(self, sql):
        """
        FossDB.access2(sql) -> int (error id)

        Write to the DB and read results.
        Stripped down version of DBaccess without all the
        error assumptions.

         * where `sql' is a string.
        """
        cdef dbapi *DB
        DB = <dbapi *>self.DB

        if not DB or not sql:
            return -1

        if DB.Res:
            PQclear(DB.Res)
            DB.Res = NULL
        
        DB.Res = DBaccess2(self.DB, sql)
        
        if (DB.Res == NULL):
            return -1

        status = self.status()
        if status in ['PGRES_COMMAND_OK', 'PGRES_EMPTY_QUERY', 'PGRES_COPY_IN', 'PGRES_COPY_OUT']:
            return 0
        elif status == 'PGRES_TUPLES_OK':
            return 1

        return -1

    def errmsg(self):
        """
        FossDB.errmsg() -> string (error message)

        Return the last error message or empty string if db not open or no result available
        """
        return re.sub('ERROR: +', '', DBerrmsg(self.DB)).rstrip()

    def status(self):
        """
        FossDB.status() -> string (status message)

        Return the last result status or empty string if db not open or no result available
        """
        return DBstatus(self.DB)

    def datasize(self):
        """
        FossDB.datasize() -> int

        Return the amount of data.
        """
        return DBdatasize(self.DB)

    def colsize(self):
        """
        FossDB.colsize() -> int
        
        Return the number of columns in the returned data.
        """
        return DBcolsize(self.DB)

    def rowsaffected(self):
        """
        FossDB.rowsaffected() -> int

        Return number of rows affected by
        the last operation.  (Good for INSERT, DELETE, or UPDATE.)
        Returns -1 on error.
        """
        return DBrowsaffected(self.DB)
    
    def getcolname(self, num):
        """
        FossDB.getcolname(num) -> string

        Return the name of a column.

         * where `num' is an integer
        """
        return DBgetcolname(self.DB, num)

    def getcolnum(self, name):
        """
        FossDB.getcolnum(name) -> int

        Return the number of a column's name. Returns size or -1.

         * where `name' is a string holding the name of the column.
        """
        return DBgetcolnum(self.DB, name)

    def getvalue(self, row, col):
        """
        FossDB.getvalue(row, col) -> string

        Return the value of a row/column.
        NOTE: No difference between invalid and NULL value.
        NOTE: Fixed fields may be space-padded.

         * where `row' and `col' are integers.
        """
        return DBgetvalue(self.DB, row, col)

    def isnull(self, row, col):
        """
        FossDB.isnull(row, col) -> int

        Return 1 of value is null, 0 if non-null.
        Returns -1 on error.

         * where `row' and `col' are integers.
        """
        return DBisnull(self.DB, row, col)

cdef extern from "../libfossrepo/libfossrepo.h":
    int	    RepOpen()
    void    RepClose()
    char *  RepGetRepPath()
    int     RepHostExist(char *Type, char *Host)
    char *	RepGetHost(char *Type, char *Filename)
    char *	RepMkPath(char *Type, char *Filename)
    int     RepExist(char *Type, char *Filename)
    int     RepRemove(char *Type, char *Filename)
    int	    RepImport(char *Source, char *Type, char *Filename, int Link)

def repOpen():
    """
    repOpen()

    Call before using any other function.

    Every other function uses the repository
    configuration files.  Why open them 100,000 times when
    it can be opened once and stored in RAM?
    This sets global structures.
    Returns: 1 on opened, 0 on failed.
    """
    return RepOpen()

def repClose():
    """
    repClose()

    Call after using all other functions.

    Every other function uses the repository
    configuration files.  Why open them 100,000 times when
    it can be opened once and stored in RAM?
    This unsets structures.
    """
    RepClose()

def repGetRepPath():
    """
    repGetRepPath() -> string

    Path to mounted repository.

    Determine the path for the repository's root.
    The RepPath is where all the repository mounts are located.
    The path should NOT end with a "/".
    Allocates and returns string with path or NULL.
    """
    return RepGetRepPath()

def repHostExist(Type, Host):
    """
    repHostExist(Type, Host) -> int

    Determine if a host exists.
    Returns 1=exists, 0=not exists, -1 on error.

     * where `Type' and `Host' are strings.
    """
    return RepHostExist(Type, Host)

def repoGetHost(Type, Filename):
    """
    repoGetHost(Type, Filename) -> string

    Determine the host for the tree.
    Type is the type of data.
    Filename is the filename to match.
    Allocates and returns string with hostname or NULL.

     * where `Type' and `Filename' are strings.
    """
    return RepGetHost(Type, Filename)

def repMkPath(Type, Filename):
    """
    repMkPath(Type, Filename) -> string

    Given a filename, construct the full
    path to the file.
    Allocates and returns a string.
    This does NOT make the actual file or modify the file system!
    Ext is an optional extension (for making temporary files).
    Caller must free the string!
    NOTE: This scans for alternate file locations, in case
    the file exists.

     * where `Type' and `Filename' are strings.
    """
    return RepMkPath(Type, Filename)

def repExist(Type, Filename):
    """
    repExist(Type, Filename) -> int
    
    Determine if a file exists.
    Returns 1=exists, 0=not exists, -1 on error.

     * where `Type' and `Filename' are strings.
    """
    return RepExist(Type, Filename)

def repRemove(Type, Filename):
    """
    repRemove(Type, Filename) -> int

    Delete a repository file.
    NOTE: This will LEAVE empty directories!
    Returns 0=deleted, !0=error from unlink().

     * where `Type' and `Filename' are strings.
    """
    return RepRemove(Type, Filename)

def repImport(Source, Type, Filename, Link):
    """
    repImport(Source, Type, Filename, Link) -> int

    Import a file into the repository.
    This is a REALLY FAST copy.
    Returns: 0=success, !0 for error.

     * where `Source', `Type' and `Filename' are strings.
     * where `Link' is an integer. Use a value of 1 to link and 0 not to link.
    """
    return RepImport(Source, Type, Filename, Link)


