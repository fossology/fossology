# FOSSology Makefile - agents/nomos
# (C) Copyright 2006, 2009 Hewlett-Packard Development Company, L.P.
#
PROG=nomos
LANG=C
SPEC=STRINGS.in
STRINGS=Nomos.strings.txt
KEYS=Nomos.keys.txt
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA

# OTHER FLAGS we can possibly use:
# -DPARSE_DISTRO_ONLY -DCONVERT_PS -DTIMING -DMEMORY_TRACING -DMEM_ACCT
# -DMEMSTATS -DUNPACK_DEBUG -DUNKNOWN_CHECK_DEBUG -DPHRASE_DEBUG
# -DDOCTOR_DEBUG -DLICENSE_DEBUG -DSEARCH_CACHE_DEBUG -DASSERTS 
# -DSAVE_UNCLASSIFIED_LICENSES -DPACKAGE_LABEL -DPACKAGE_DEBUG -DREPORT_DEBUG
# -DSTOPWATCH -DPARSE_STOPWATCH -DGLOBAL_DEBUG -DCHDIR_DEBUG -DLTSR_DEBUG
# -DQA_CHECKS -DBUCKET_DEBUG -DEXPIRE_DEBUG -DPHRASE_DEBUG -DLIST_DEBUG
## BASEFLAGS=-UDEBUG -DUSE_DPKG_SOURCE -DPACKAGE_LABEL \
#	-DSAVE_UNCLASSIFIED_LICENSES -DPRECHECK \
#	-Werror

BASEFLAGS=-D_FILE_OFFSET_BITS=64 -D__USE_LARGEFILE64 -Wall #-UDEBUG 
CFLAGS=-g $(BASEFLAGS) 
LIBS=-lmagic
OBJS=_precheck.o conf.o _autodata.o licenses.o list.o md5.o nomos.o \
	parse.o process.o regex.o report.o sources.o util.o # DMalloc.o
SRCS=$(OBJS:.o=.c)
HDRS=nomos.h _autodefs.h md5.h 
ALLSRC=$(SRCS) $(HDRS) encode.c

all:	encode $(PROG) 

over:
	@touch $(SPEC)
	$(MAKE)

encode:	encode.c
	$(CC) -o $@ $@.c

$(PROG): $(OBJS) 
	$(CC) -o $(PROG) $(LDFLAGS) $(CFLAGS) $(OBJS) $(LIBS)
	@nm -n nomos | grep -v " [Uw] " > Nomos.nm.map
#	@$(MAKE) -s check

objdump:
	@objdump -s nomos > Nomos.objdump.map

_autodefs.h _autodata.c:	$(SPEC) $(LICFIX)
	./$(LICFIX)

_precheck.c:	_autodata.c # $(PRE)
#	@echo "NOTE: _autodata.c has changed --> regenerate _precheck.c"
	./$(PRE)
	./$(CHECK)
	@$(MAKE) $(STRINGS) $(KEYS)

gen pre:
	@./$(PRE)

$(KEYS):	$(SPEC)
	@grep %KEY% $(SPEC) | grep -v "^#" | sort | uniq -c | sort -nr > $(KEYS)

$(STRINGS): _autodata.c
	@grep -F 'Phrase[' _autodata.c > $(STRINGS)

prof:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -pg" "LDFLAGS=$(LDFLAGS) -pg" $(PROG)

dr doctor:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DDOCTOR_DEBUG" $(PROG)
	@echo "less '+/(getInstances|BEFORE|AFTER|Middle|Found regex)'"

plain:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS)" $(PROG)

time times timer watch:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DSTOPWATCH" $(PROG)

pcre:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS)" "LIBS=$(LIBS) -lpcreposix" $(PROG)

efence:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS)" "LIBS=$(LIBS) -lefence" $(PROG)

verbose:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DPROC_TRACE" "LIBS=$(LIBS) -lefence" $(PROG)

debug:
	$(MAKE) clean
	$(MAKE) "CFLAGS=-DDEBUG=3" $(PROG)

opt:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(BASEFLAGS) -O4" $(PROG)

trace:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DPROC_TRACE" $(PROG)

t:	trace

check:
	@./checkstrings
#	@$(MAKE) -s uses

tswitch:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DPROC_TRACE_SWITCH" $(PROG)

ts:	tswitch

stats:
	@echo Lines of code:
	@wc -l $$(ls *.c *.h STRINGS* GEN* PRE* CHECK* queue*) | sort -nr
	@grep 'INTERESTING(' parse.c > _LIC_STR.INTERESTING
	@grep '_MIN_' STRINGS.in >> _LIC_STR.INTERESTING
	@grep 'LOWINTEREST(' parse.c > _LIC_STR.LOWINTEREST
	@printf 'License-strings in code: '; wc -l _LIC_STR* | grep 'total' | awk '{print $$1}'
	@grep 'INTERESTING(' parse.c | sed -e 's/lDebug ? ".*" : //g' -e "s/^[  ]*//g" | sort | uniq > _LIC_STR.INTERESTING.unique
	@grep '_MIN_' STRINGS.in >> _LIC_STR.INTERESTING.unique
	@grep 'LOWINTEREST(' parse.c | sed -e 's/lDebug ? ".*" : //g' -e "s/^[  ]*//g" | sort | uniq > _LIC_STR.LOWINTEREST.unique
	@wc -l _LIC_STR* | grep -v '[0-9] total'
	@printf '... Unique license-strings: '; wc -l _LIC_STR*unique | grep 'total' | awk '{print $$1}'
	@$(MAKE) -s uses

uses:
	@grep %ENTRY% $(SPEC) | tee _LIC_STR_XXX | egrep -v '(^#|_(UTIL_DICT|KW|ZZGEN|LEGAL|MIN|TEXT_))' | awk '{print $$2}' > _LIC_STR_ALL
	@echo Checking $$(wc -l < _LIC_STR_XXX) identifiers for use, defined in $(SPEC)...
	@for i in $$(cat _LIC_STR_ALL); do \
	    X=$$(grep -l $$i [^_]*.c) ; \
	    [ -n "$$X" ] && continue ; \
	    echo "NOT USED: $$i" ; \
	done
	@rm -f _LIC_STR*
#	@awk '{print $$1}' _strings.data | sort | uniq -c | sort -nr


tags:	
	@rm -f tags
	ctags $(SRCS)

etags:	
	@rm -f TAGS
	etags $(SRCS)

tool:
	@echo "Handy command to reduce PROC_TRACE output:"
	@printf "\tLANG=C egrep -v '^(==|!!|=>|\.\.\.|#|    )'\n"
	@echo "Handy command to reduce MEMSTATS output:"
	@printf "\tLANG=C egrep -v '(^\.\.\.|^\*\*| VmRSS|static lists)'\n"
	@echo "Handy command to follow regex/license matching:"
	@printf "\tLANG=C egrep '(^Found|addRef|saveLicenseData|parseLicenses| this is file | score | used [0-9]*,|\<PROCESS\>)' X\n"


$(OBJS):	$(HDRS)

clean:
	rm -f *.o 

clobber: clean 
	rm -f $(PROG) encode

test:
	@echo Testing various make targets:
	@echo verbose...
	$(MAKE) clean
	$(MAKE) verbose
	@echo opt...
	$(MAKE) clean
	$(MAKE) opt
	@echo trace...
	$(MAKE) clean
	$(MAKE) trace
	@echo tswitch...
	$(MAKE) clean
	$(MAKE) tswitch
	@echo vanilla-old-plain...
	$(MAKE) clean
	$(MAKE) plain
