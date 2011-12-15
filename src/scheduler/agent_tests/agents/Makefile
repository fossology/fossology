######################################################################
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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
TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = $(ALL_CFLAGS) -Werror
DEF = 

MODDIR = mods-enabled

C_FILES = $(wildcard *.c)
EXES = $(patsubst %.c, %, $(C_FILES))

##########################
# executable build rules #
##########################

all: $(EXES)
$(EXES): %: %.c $(FOLIB) $(VARS)
	@echo $@: 
	@if [ ! -d $(MODDIR)/$@ ] ; then \
	  mkdir -p $(MODDIR)/$@/agent/; fi;
	@cp test.conf $(MODDIR)/$@/$@.conf
	$(CC) $< -o $(MODDIR)/$@/agent/test_binary $(CFLAGS_LOCAL)

#####################
# other build rules #
#####################

clean:
	rm -rf $(MODDIR)

.PHONY: all $(MODDIR) $(EXE) $(FOLIB)
