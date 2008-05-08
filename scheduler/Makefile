# scheduler Makefile
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
include ../Makefile.conf

DBPATH=../devel/libfossdb
DB=$(DBPATH)/libfossdb.a
REPOPATH=../devel/libfossrepo
REPO=$(REPOPATH)/libfossrepo.a

CFLAGS_DB=-I$(DBPATH) -L$(DBPATH)
CFLAGS_REPO=-I$(REPOPATH) -L$(REPOPATH)

CONFDEF=-DDEFAULTSETUP='"$(AGENTDATADIR)/scheduler.conf"'
FODEF=-DPROJECTUSER='"$(PROJECTUSER)"' -DPROJECTGROUP='"$(PROJECTGROUP)"'
MSCDEF=-DDATADIR='"$(AGENTDATADIR)"' -DBINDIR='"$(AGENTDIR)"' -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"'

# these objects need libfossdb
OBJ=scheduler.o spawn.o sockets.o agents.o hosts.o debug.o dberror.o
# these objects need libfossrepo as well
REPOOBJ=dbstatus.o dbq.o

all: fossology mkschedconf

fossology: $(OBJ) $(REPOOBJ)
	$(CC) $^ $(FODEF) $(CFLAGS_DB) -lfossdb $(CFLAGS_REPO) -lfossrepo \
	$(ALL_CFLAGS) -lpq -o $@

mkschedconf: mkschedconf.c $(REPO)
	$(CC) $< $(MSCDEF) $(CFLAGS_REPO) -lfossrepo $(ALL_CFLAGS) -o $@


# targets for our needed objects
$(OBJ): %.o: %.c %.h $(DB)
	$(CC) -c $< $(CONFDEF) $(CFLAGS_DB) $(ALL_CFLAGS)

$(REPOOBJ): %.o: %.c %.h $(DB) $(REPO)
	$(CC) -c $< $(CONFDEF) $(CFLAGS_REPO) $(CFLAGS_DB) $(ALL_CFLAGS)


# targets to make sure the libfoss libraries we use are up to date
$(DB):
	$(MAKE) -C ../devel/libfossdb

$(REPO):
	$(MAKE) -C ../devel/libfossrepo


install: all
	$(INSTALL_PROGRAM) fossology $(AGENTDIR)/fossology
	$(INSTALL_PROGRAM) mkschedulerconf $(LIBEXECDIR)/mkschedulerconf

	# init script
	if [ ! -f $(DESTDIR)/etc/init.d/fossology -o $(OVERWRITE) ] ; then \
		cat init.d | sed -e "s@BINDIR@$(BINDIR)@" > \
			$(DESTDIR)/etc/init.d/fossology
	else \
		echo "WARNING: /etc/init.d/fossology already exists."; \
		echo "  Not overwriting, consider checking it by hand."; \
	fi

	# default file
	if [ ! -f $(DESTDIR)/etc/default/fossology -o $(OVERWRITE) ] ; then \
		$(INSTALL_DATA) default $(DESTDIR)/etc/default/fossology
	else \
		echo "WARNING: /etc/init.d/fossology already exists."; \
		echo "  Not overwriting, consider checking it by hand."; \
	fi

	# scheduler config file
	if [ ! -f $(DATADIR)/scheduler.conf -o $(OVERWRITE) ] ; then \
		./mkconfig -o $(DATADIR)/scheduler.conf -L; \
	else \
		echo "WARNING: $(DATADIR)/scheduler.conf already exists,"; \
		echo "  consider checking or recreating it with mkconfig."; \
	fi

uninstall:
	rm -f $(BINDIR)/fossology
	rm -f $(LIBEXECDIR)/mkschedconf
	# we cowardly refuse to remove the scheduler.conf file
	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DATADIR)/scheduler.conf"
	@echo "      $(DESTDIR)/etc/init.d/fossology"
	@echo "      $(DESTDIR)/etc/default/fossology"
	@echo "  Remove by hand if you desire."

clean:
	$(RM) $(OBJ) $(REPOOBJ) core fossology mkschedconf

test: all
	@echo "NOTICE: no tests available for scheduler"


.PHONY: all install uninstall clean test
