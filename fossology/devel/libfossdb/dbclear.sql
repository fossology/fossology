# dbclear.sql - SQL code to completely flush the database
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# Only do this if you want to completely zero-out the database!
# There is no "un-do" to this code!

# To use this SQL:
#   dbinit dbclear.sql
#
# The dbinit program is part of the installation.  Unless you changed the
# install paths, it should be located at /usr/local/fossy/test.d/dbinit 

delete from jobdepends;
delete from jobqueue;
delete from job;
delete from foldercontents;
delete from folder where folder_pk > 1;
delete from uploadtree;
delete from upload;
delete from ufile;
delete from agent_lic_status;
delete from agent_lic_meta;
delete from pfile;

