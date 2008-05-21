# scheduler Makefile
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
VARS=../Makefile.conf
include $(VARS)

DBPATH=../devel/libfossdb
DB=$(DBPATH)/libfossdb.a
REPOPATH=../devel/libfossrepo
REPO=$(REPOPATH)/libfossrepo.a

CFLAGS_DB=-I$(DBPATH) -L$(DBPATH)
CFLAGS_REPO=-I$(REPOPATH) -L$(REPOPATH)

CONFDEF=-DDEFAULTSETUP='"$(SYSCONFDIR)/scheduler.conf"'
FODEF=-DPROJECTUSER='"$(PROJECTUSER)"' -DPROJECTGROUP='"$(PROJECTGROUP)"'
MSCDEF=-DDATADIR='"$(DATADIR)"' -DBINDIR='"$(LIBEXECDIR)"' -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"'

# these objects need libfossdb
OBJ=scheduler.o spawn.o sockets.o agents.o hosts.o debug.o dberror.o
# these objects need libfossrepo as well
REPOOBJ=dbstatus.o dbq.o

all: fossology-scheduler mkschedconf

fossology-scheduler: $(OBJ) $(REPOOBJ) $(VARS)
	$(CC) $(OBJ) $(REPOOBJ) $(FODEF) $(CFLAGS_DB) -lfossdb $(CFLAGS_REPO) \
	-lfossrepo $(ALL_CFLAGS) -lpq -o $@

mkschedconf: mkschedconf.c $(REPO) $(VARS)
	$(CC) $< $(MSCDEF) $(CFLAGS_REPO) -lfossrepo $(ALL_CFLAGS) -o $@


# targets for our needed objects
$(OBJ): %.o: %.c %.h $(DB) $(VARS)
	$(CC) -c $< $(CONFDEF) $(CFLAGS_DB) $(ALL_CFLAGS)

$(REPOOBJ): %.o: %.c %.h $(DB) $(REPO) $(VARS)
	$(CC) -c $< $(CONFDEF) $(CFLAGS_REPO) $(CFLAGS_DB) $(ALL_CFLAGS)


# targets to make sure the libfoss libraries we use are up to date
$(DB):
	$(MAKE) -C $(DBPATH) libfossdb.a

$(REPO):
	$(MAKE) -C $(REPOPATH) libfossrepo.a


install: all $(VARS)
	$(INSTALL_PROGRAM) fossology-scheduler $(DESTDIR)$(LIBEXECDIR)/fossology-scheduler
	$(INSTALL_PROGRAM) mkschedconf $(DESTDIR)$(LIBEXECDIR)/mkschedconf

	# init script
	if [ ! -f $(DESTDIR)/etc/init.d/fossology -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)/etc/init.d; \
		cat init.d | sed -e "s@LIBEXECDIR@$(LIBEXECDIR)@" > $(DESTDIR)/etc/init.d/fossology; \
	else \
		echo "WARNING: /etc/init.d/fossology already exists."; \
		echo "  Not overwriting, consider checking it by hand."; \
	fi

	# default file
	if [ ! -f $(DESTDIR)/etc/default/fossology -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)/etc/default; \
		$(INSTALL_DATA) default $(DESTDIR)/etc/default/fossology; \
	else \
		echo "WARNING: /etc/init.d/fossology already exists."; \
		echo "  Not overwriting, consider checking it by hand."; \
	fi

	# scheduler config file
	if [ ! -f $(DESTDIR)$(SYSCONFDIR)/scheduler.conf -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)$(SYSCONFDIR); \
		./mkschedconf -o $(DESTDIR)$(SYSCONFDIR)/scheduler.conf -L; \
	else \
		echo "WARNING: $(DESTDIR)$(SYSCONFDIR)/scheduler.conf already exists,"; \
		echo "  consider checking or recreating it with mkconfig."; \
	fi

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fossology-scheduler
	rm -f $(DESTDIR)$(LIBEXECDIR)/mkschedconf

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi; \
	fi

	@if [ -d $(DESTDIR)$(SYSCONFDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(SYSCONFDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(SYSCONFDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(SYSCONFDIR); \
		fi; \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)$(SYSCONFDIR)/scheduler.conf"
	@echo "      $(DESTDIR)/etc/init.d/fossology"
	@echo "      $(DESTDIR)/etc/default/fossology"
	@echo "  Remove by hand if you desire."

clean:
	rm -f $(OBJ) $(REPOOBJ) core fossology-scheduler mkschedconf

test: all
	@echo "NOTICE: no tests available for scheduler"


.PHONY: all install uninstall clean test
