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
GLIB_FLAGS = `pkg-config --cflags --libs glib-2.0` -lgthread-2.0 -lgio-2.0
CFLAGS_LOCAL = $(GLIB_FLAGS) $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I. -lpq -lpthread
DEF   = -DAGENT_DIR='"$(DESTDIR)$(AGENTDIR)"' -DLOG_DIR='"$(LOGDIR)"'
FODEF = -DPROJECT_USER='"$(PROJECTUSER)"' -DPROJECT_GROUP='"$(PROJECTGROUP)"'
DEPEN = Makefile

EXE = fossology-scheduler
CLI = fossology-cli

#######################
# objects and headers #
#######################

OBJS = agent.o \
       event.o \
       job.o \
       logging.o \
       interface.o \

HEAD = agent.h \
       event.h \
       scheduler.h \
       host.h \
       job.h \
       logging.h \
       interface.h \
       
##########################
# executable build rules #
##########################

all: $(EXE) $(CLI)

$(EXE): scheduler.c $(OBJS) $(VARS) $(HEAD) $(DEPEN)
	$(CC) scheduler.c $(OBJS) $(DEF) $(FODEF) $(CFLAGS_LOCAL) -o $@

$(CLI): schedulerCLI.c $(DEPEN)
	$(CC) schedulerCLI.c $(CFLAGS_LOCAL) -o $@

#######################
# library buidl rules #
#######################

libscheduler.a: $(OBJS) $(DEPEN)
	ar cvr $@ $(OBJS)

######################
# object build rules #
######################

agent.o: %.o: %.c %.h job.h scheduler.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

job.o: %.o: %.c %.h agent.h scheduler.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

interface.o: %.o: %.c %.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

event.o: %.o: %.c %.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

logging.o: %.o: %.c %.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

clean:
	rm -f $(OBJS) *.a
	rm -f $(EXE)






