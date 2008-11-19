# FOSSology Makefile - cli/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

EXE=cp2foss.php fossinit.php fossjobs.php fosslic.php
DOCS=cp2foss.pod fossjobs.pod fosslic.pod
MAN1FILES=$(DOCS:%.pod=%.1)
HTMLFILES=$(DOCS:%.pod=%.html)
TEXTFILES=$(DOCS:%.pod=%.txt)

all: $(iPATHFILE) docs

docs: $(DOCS) $(MAN1FILES) $(HTMLFILES) $(TEXTFILES)

%.1: %.pod
	pod2man --name=$* --center="FOSSology" --release="Version $(VERSION)" $< > $*.1

%.html: %.pod
	pod2html --infile=$< --outfile=$*.html
	# pod2html leaves temporary files: see debian bug #378328
	rm -f pod*.tmp

%.txt: %.pod
	pod2text $< $*.txt

install: all
	for file in $(EXE); do \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(BINDIR)/$${file%.php}; \
	done

	for file in $(MAN1FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MAN1DIR)/$$file; \
	done

uninstall:
	for file in $(EXE); do \
		rm -f $(DESTDIR)$(BINDIR)/$${file%.php}; \
	done

	for file in $(MAN1FILES); do \
		rm -f $(DESTDIR)$(MAN1DIR)/$$file; \
	done

test: all
	@echo "*** Tests are still run by hand for cli/ ***"

clean:
	# pod2html leaves temporary files: see debian bug #378328
	rm -f *.o core $(MAN1FILES) $(HTMLFILES) $(TEXTFILES) pod*.tmp

include $(DEPS)

.PHONY: all install uninstall clean test
