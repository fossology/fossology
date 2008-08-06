# FOSSology Makefile - ui/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)

DIRS=images plugins common template
FILES=`find . -type f | grep -v svn | grep -E "(php|css|htm|html|dtd|htc|gif|dat)$$"`
# also set in Makefile.conf, but we override with a shorter version
iPATHFILE=pathinclude.h.php

all:

$(iPATHFILE): ../Makefile.conf
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "global \$$GlobalReady;" >> $(iPATHFILE)
	@echo "if (!isset(\$$GlobalReady)) { exit; }" >> $(iPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(iPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECTSTATEDIR=\"$(PROJECTSTATEDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$AGENTDIR:\$$PATH:/usr/bin\";" >> $(iPATHFILE)
	@echo "\$$VERSION=\"$(VERSION)\";" >> $(iPATHFILE)
	@echo "\$$SVN_REV=\"$(SVN_REV)\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

install: all $(iPATHFILE)

	for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	for file in $(FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/$$file; \
	done

	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)

uninstall:
	for file in $(FILES); do \
		rm -f $(DESTDIR)$(WEBDIR)/$$file; \
	done

	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)

	for dir in $(DIRS); do \
		rmdir $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	rm -f $(DESTDIR)$(PHPDIR)/$(iPATHFILE)

test: all
	@echo "*** Tests are still run by hand for ui/ ***"

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall clean test
