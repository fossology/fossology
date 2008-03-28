# FOSSology CLI makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# VERSION: $Id: Makefile 1587 2007-12-14 02:48:04Z markd $

include ../Makefile.conf
SHELL=/bin/bash

# These are likely to change slowly over time
PHPCLI=/etc/php5/cli/php.ini
PHPWEB=/etc/php5/apache2/php.ini
LIBFILES=libcp2foss.h.php
BINFILES=cp2foss.php p.sh fossinit.php
MAN1SRC=cp2foss.pod
MAN1FILES=cp2foss.1
iPATHFILE=pathinclude.h.php

all: $(iPATHFILE) MANpages


$(iPATHFILE): ../Makefile.conf
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$VARDATADIR=\"$(VARDATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$AGENTDIR:\$$PATH:/usr/bin\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

MANpages:
	@echo "MANpages target"
	@if [ ! -x ./mkpod ] ; then chmod u+x ./mkpod; fi
	./mkpod $(MAN1SRC)
   
InstallationCreate:
	echo "INSTALLATIONCREATE target"; \
	chmod a+x $(BINFILES)
	$(CP) $(BINFILES) ../install/$(BINDIR)
	$(CP) $(LIBFILES) ../install/$(LIBDIR)
	$(CP) $(MAN1FILES) ../install/$(MAN1DIR)
	$(CP) $(iPATHFILE) ../install/$(WEBDIR)
	$(CP) $(iPATHFILE) ../install/$(PHPDIR)
	$(CP) $(iPATHFILE) ../install/$(AGENTDIR)

install: all InstallationCreate
	for file in ${BINFILES}; do \
		chmod a+x $${file}; \
	done
	echo "CLI->install Makefile"
	$(CP) $(BINFILES) $(BINDIR)
	if [ -f $(PHPCLI) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPCLI) || \
	    echo 'extension=pgsql.so' >> $(PHPCLI) ; \
	fi
	if [ -f $(PHPWEB) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPWEB) || \
	    echo 'extension=pgsql.so' >> $(PHPWEB) ; \
	fi
	$(MKDIR) -p $(PHPDIR)
	$(MKDIR) -p $(MAN1DIR)
	$(CP) $(LIBFILES) $(LIBDIR)
	$(CP) $(MAN1FILES) ../install/$(MAN1DIR)
	$(CP) $(iPATHFILE) $(WEBDIR)
	$(CP) $(iPATHFILE) $(PHPDIR)
	$(CP) $(iPATHFILE) $(AGENTDIR)

uninstall:
	(cd $(LIBDIR) ;   $(RM) $(LIBFILES))
	(cd $(MAN1FILES); $(RM) $(MAN1DIR))
	(cd $(WEBDIR) ;   $(RM) $(iPATHFILE))
	(cd $(PHPDIR) ;   $(RM) $(iPATHFILE))
	(cd $(AGENTDIR) ; $(RM) $(iPATHFILE))
	(cd $(BUINDIR) ;  $(RM) $(BINFILES))

clean:
	$(RM) $(iPATHFILE)

