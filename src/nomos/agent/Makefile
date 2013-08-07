# FOSSology Makefile - agent/nomos
# Copyright (C) 2006-2013 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

EXE = nomos

SPEC=STRINGS.in
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA

OBJS=licenses.o list.o parse.o process.o nomos_regex.o util.o # sources.o DMalloc.o
GENOBJS=_precheck.o _autodata.o
HDRS=nomos.h $(OBJS:.o=.h) _autodefs.h

CFLAGS_LOCAL= $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
CFLAGS_LOCALO= $(CFLAGS_DBO) $(CFLAGS_REPOO) $(CFLAGS_AGENTO) $(ALL_CFLAGS)

all: encode nomos

debug: nomos-gl 

nomos: nomos.o $(OBJS) $(GENOBJS)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(CFLAGS_LOCAL) -o $@

nomos.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(CFLAGS_LOCALO) $(DEFS)
	
nomos-gl: nomos-g.o $(OBJS) $(GENOBJS)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(CFLAGS_LOCAL) -o $@

nomos-g.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c  -l /usr/lib/libefence.a $< $(CFLAGS_LOCALO) 

$(OBJS) $(GENOBJS): %.o: %.c $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(CFLAGS_DBO) $(ALL_CFLAGS)

#
# Non "standard" preprocessing stuff starts here...
#

encode: encode.o
	$(CC) $(CFLAGS) -o $@ $@.c

_precheck.c:	_autodata.c $(PRE) $(CHECK)
#	@echo "NOTE: _autodata.c has changed --> regenerate _precheck.c"
	./$(PRE)
	./$(CHECK)
#	@$(MAKE) $(STRINGS) $(KEYS)

_autodefs.h _autodata.c:	$(SPEC) $(LICFIX)
	@echo "NOTE: GENSEARCHDATA takes 1-2 minutes to run"
	./$(LICFIX)

#
# Non "standard" preprocessing stuff ends here...
#

install: all
	@echo "make nomos agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)
#	$(INSTALL_PROGRAM) encode  $(DESTDIR)$(AGENTDIR)/encode

uninstall:
	@echo "make nomos agent uninstall"
#	rm -f $(DESTDIR)$(AGENTDIR)/encode
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for nomos"

clean:
	rm -f encode nomos  *.o core \
           _autodata.c _autodefs.c _autodefs.h _precheck.c \
           _strings.data _STRFILTER strings.HISTOGRAM words.HISTOGRAM \
           split.OTHER checkstr.OK

include $(DEPS)

.PHONY: all install uninstall clean test
