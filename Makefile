# FOSSology Makefile - agent/fo_nomos
# Copyright (C) 2006-2009 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

SPEC=STRINGS.in
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA


#OBJS=_precheck.o _autodata.o licenses.o list.o nomos.o parse.o process.o regex.o util.o # sources.o DMalloc.o
OBJS=licenses.o list.o nomos.o parse.o process.o nomos_regex.o util.o _precheck.o _autodata.o # sources.o DMalloc.o
SRCS=$(OBJS:.o=.c)
HDRS=_autodefs.h licenses.h list.h nomos.h nomos_regex.h parse.h process.h util.h

DEF=-D_FILE_OFFSET_BITS=64 -D__USE_LARGEFILE64
CFLAGS_LOCAL= $(DEF) $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)

all: encode fo_nomos

fo_nomos: $(OBJS) $(DB) $(REPO) $(VARS)
	$(CC) $(OBJS) $(CFLAGS_LOCAL) -o $@
#	$(CC) $< $(CFLAGS_LOCAL) -o $@

$(OBJS): %.o: %.c %.h $(DB) $(VARS)
	$(CC) -c $< $(DEF) $(ALL_CFLAGS) $(CFLAGS_DB) $(CFLAGS_REPOO) $(CFLAGS_AGENTO)

#
# Non "standard" preprocessing stuff starts here...
#
encode: encode.o
	$(CC) $(CFLAGS) -o $@ $@.c

_autodefs.h _autodata.c:	$(SPEC) $(LICFIX)
	@echo "NOTE: GENSEARCHDATA takes 1-2 minutes to run"
	./$(LICFIX)

_precheck.c:	_autodata.c # $(PRE)
#	@echo "NOTE: _autodata.c has changed --> regenerate _precheck.c"
	./$(PRE)
	./$(CHECK)
#	@$(MAKE) $(STRINGS) $(KEYS)

#
# Non "standard" preprocessing stuff ends here...
#

install: all
	$(INSTALL_PROGRAM) fo_nomos $(DESTDIR)$(AGENTDIR)/fo_nomos
	$(INSTALL_PROGRAM) encode  $(DESTDIR)$(AGENTDIR)/encode

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/encode
	rm -f $(DESTDIR)$(AGENTDIR)/fo_nomos

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f encode fo_nomos  *.o core _autodata.c _autodefs.c _autodefs.o _precheck.c _strings.data _STRFILTER

include $(DEPS)

.PHONY: all install uninstall clean test
