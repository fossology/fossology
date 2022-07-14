# SPDX-FileCopyrightText: © 2011-2015 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

# Uses:
#   doxygen
#   schemaSpy  (http://schemaspy.sourceforge.net/)
#   java       (apt-get install default-jre)
#   postgres JDBC (apt-get install libpg-java)

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DoxDir=$(FOSRCDIR)/srcdocs
SchemaSpy=/usr/local/bin/schemaSpy
PgJDBC=/usr/share/java/postgresql-jdbc4-9.1.jar

all: doxygen schemaspy

doxygen:
	doxygen fodox.conf > fodox.log 2> fodox.errs

schemaspy:
	java -jar $(SchemaSpy) -t pgsql -cp $(PgJDBC) -db fossology -s public -host localhost -u fossy -p fossy -o $(DoxDir)/www/schemaspy

clean:
	rm -rf www *~ fodox.errs fodox.log

.PHONY: all doxygen schmaspy clean
