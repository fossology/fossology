# FOSSology Makefile - install/
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

# Create and install:
#  fo-postinstall
#  SYSCONFDIR/db.conf

TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONFPATH=$(SYSCONFDIR)
# default version is the scm revision number
-DVERSION=$(SCM_REV)

all:	fo-postinstall defconf/Db.conf defconf/fossology.conf db/dbcreate

# include the preprocessing stuff
include $(TOP)/Makefile.process

# generate the postinstall script
fo-postinstall:	fo-postinstall-process
	chmod +x fo-postinstall

# generate the default Db.conf file
defconf/Db.conf: defconf/Db.conf-process

# generate fossology.conf
defconf/fossology.conf:	defconf/fossology.conf-process

# create dbcreate
	$(MAKE) -s -C db

install: all
	$(INSTALL_PROGRAM) fo-postinstall $(DESTDIR)$(LIBEXECDIR)/fo-postinstall
	$(INSTALL_PROGRAM) fo_dbcheck.php $(DESTDIR)$(LIBEXECDIR)/fo_dbcheck.php
	$(INSTALL_PROGRAM) fossinit.php $(DESTDIR)$(LIBEXECDIR)/fossinit.php
	$(MAKE) -s -C db install
	echo "Making configuration directories"; \
	if [ ! -e $(DESTDIR)$(CONFPATH) ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH); \
	fi
	if [ ! -e $(DESTDIR)$(CONFPATH)/mods-enabled ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH)/mods-enabled; \
		/bin/chmod 775 $(DESTDIR)$(CONFPATH)/mods-enabled; \
	fi
	if [ ! -e $(DESTDIR)$(CONFPATH)/conf ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH)/conf; \
	fi
	echo "Installing configuration files..."
	if [ ! -f $(DESTDIR)$(CONFPATH)/Db.conf -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/Db.conf"; \
	 	$(INSTALL) -m 640 defconf/Db.conf $(DESTDIR)$(CONFPATH)/Db.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/Db.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	if [ ! -f $(DESTDIR)$(CONFPATH)/fossology.conf -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/fossology.conf"; \
	 	$(INSTALL) -m 666 defconf/fossology.conf $(DESTDIR)$(CONFPATH)/fossology.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/fossology.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	if [ ! -f $(DESTDIR)$(CONFPATH)/sampleheader.txt -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/sampleheader.txt"; \
	 	$(INSTALL) -m 666 defconf/sampleheader.txt $(DESTDIR)$(CONFPATH)/sampleheader.txt; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/sampleheader.txt already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	if [ ! -f $(DESTDIR)$(CONFPATH)/samplefooter.txt -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/samplefooter.txt"; \
	 	$(INSTALL) -m 666 defconf/samplefooter.txt $(DESTDIR)$(CONFPATH)/samplefooter.txt; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/samplefooter.txt already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	if [ ! -f $(DESTDIR)$(CONFPATH)/conf/fo-apache.conf -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/conf/fo-apache.conf"; \
	 	$(INSTALL) -m 666 fo-apache.conf $(DESTDIR)$(CONFPATH)/conf/fo-apache.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/conf/fo-apache.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	if [ ! -f $(DESTDIR)$(CONFPATH)/conf/src-install-apache-example.conf -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/conf/src-install-apache-example.conf"; \
	 	$(INSTALL) -m 666 src-install-apache-example.conf $(DESTDIR)$(CONFPATH)/conf/src-install-apache-example.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/conf/src-install-apache-example.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo-postinstall
	rm -f $(DESTDIR)$(CONFPATH)/sampleheader.txt
	rm -f $(DESTDIR)$(CONFPATH)/samplefooter.txt
	rm -f $(DESTDIR)$(LIBEXECDIR)/fossinit.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo_dbcheck.php

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi; \
	fi

	@echo "Configuration files will not be removed:"
	@echo "      $(DESTDIR)$(CONFPATH)/fossology.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Db.conf"
	@echo "  Remove by hand if you desire."
	$(MAKE) -s -C db uninstall

clean:
	rm -f fo-postinstall defconf/Db.conf defconf/fossology.conf
	$(MAKE) -s -C db clean

test: all
	@echo "NOTICE: no tests available for install"

coverage:
        @echo "NOTICE: no coverage available for install"

.PHONY: all install uninstall clean test coverage
