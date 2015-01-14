######################################################################
# Copyright (C) 2011-2015 Hewlett-Packard Development Company, L.P.
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
######################################################################

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
