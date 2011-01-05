######################################################################
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
######################################################################

TOP = ..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CC = gcc
CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I. -lpq -lpthread
DEF   = -DAGENT_DIR='"$(DESTDIR)$(AGENTDIR)"'
FODEF = -DPROJECT_USER='"$(PROJECTUSER)"' -DPROJECT_GROUP='"$(PROJECTGROUP)"'

EXE = fossology-scheduler

#######################
# objects and headers #
#######################

OBJS = agent.o \
       event.o \

HEAD = agent.h \
       event.h \
       scheduler.h \
       host.h \
       
##########################
# executable build rules #
##########################

all: $(EXE)

$(EXE): scheduler.c libscheduler.a $(VARS) $(HEAD) Makefile
	$(CC) scheduler.c libscheduler.a $(DEF) $(FODEF) $(CFLAGS_LOCAL) `pkg-config --cflags --libs glib-2.0` -o $@

#######################
# library buidl rules #
#######################

libscheduler.a: $(OBJS) Makefile
	ar cvr $@ $(OBJS)

######################
# object build rules #
######################

agent.o: %.o: %.c %.h scheduler.h Makefile
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) `pkg-config --cflags glib-2.0` $<

event.o: %.o: %.c %.h Makefile
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

clean:
	rm -f $(OBJS) *.a
	rm -f $(EXE)



