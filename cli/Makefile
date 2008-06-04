# FOSSology Makefile - cli/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)

LIB=libcp2foss.h.php
EXE=cp2foss.php p.sh fossinit.php fossjobs.php
DOCS=cp2foss.pod fossjobs.pod
MAN1FILES=$(DOCS:%.pod=%.1)
HTMLFILES=$(DOCS:%.pod=%.html)
TEXTFILES=$(DOCS:%.pod=%.txt)
iPATHFILE=pathinclude.h.php

all: $(iPATHFILE) docs
	for file in $(EXE); do \
		chmod +x $${file}; \
	done

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

docs: $(DOCS) $(MAN1FILES) $(HTMLFILES) $(TEXTFILES)

%.1: %.pod
	pod2man --center="$*" --release="Version $(VERSION)" $< > $*.1

%.html: %.pod
	pod2html --infile=$< --outfile=$*.html
	rm -f pod*.tmp

%.txt: %.pod
	pod2text $< $*.txt

install: all
	for file in $(EXE); do \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(BINDIR)/$$file; \
	done

	$(INSTALL_DATA) $(LIB) $(DESTDIR)$(LIBDIR)/$(LIB)
	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(LIBDIR)/$(iPATHFILE)

	for file in $(MAN1FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MAN1DIR)/$$file; \
	done

	# FIXME: need to pick a central location
	#$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(WEBDIR)/$(iPATHFILE)
	#$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	#$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(AGENTDIR)/$(iPATHFILE)

uninstall:
	for file in $(EXE); do \
		rm -f $(DESTDIR)$(BINDIR)/$$file; \
	done

	rm -f $(DESTDIR)$(LIBDIR)/$(LIB)
	rm -f $(DESTDIR)$(LIBDIR)/$(iPATHNAME)

	for file in $(MAN1FILES); do \
		rm -f $(DESTDIR)$(MAN1DIR)/$$file
	done

test: all
	@echo "*** Tests are still run by hand for cli/ ***"

clean:
	rm -f $(iPATHFILE) *.o core $(MAN1FILES) $(HTMLFILES) $(TEXTFILES) pod*.tmp

.PHONY: all install uninstall clean test
