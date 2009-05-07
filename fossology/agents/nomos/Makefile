#####
# (C) Copyright 2006 Hewlett-Packard Development Company, L.P.
#####
SHELL=/bin/sh
PROG=nomos
LANG=C
# BASEPATH=/OSRB/OSRB.new
BASEPATH=/root/OSRB.new
DATA=OSRBlists.tgz
ARCH=nomos_src.tgz
CRON=queue-cron.sh
CONF=nomos.conf
HTML=nomos.html
CGI=nomos.cgi
MF=Makefile
SPEC=STRINGS.in
STRINGS=Nomos.strings.txt
KEYS=Nomos.keys.txt
TOOL=autogdb
EXT=./EXT
TOOLSDIR=./tools
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA
DBPATH=$(BASEPATH)/db
LINTOUT=lint.out
LAST=.LASTSAVE
CRT=Copyright
DIR_ULB=/usr/local/bin123
# relative to ./tools/
SRC_CAB=cabextract-1.1.tar.gz
DIR_CAB=cabextract-1.1
SRC_JAR=fastjar-0.94.tar.gz
DIR_JAR=fastjar-0.94
SRC_UNRAR=unrar-3.50.tar.gz
DIR_UNRAR=unrar
SRC_PS2T=pstotext-1.9.tgz
DIR_PS2T=pstotext-1.9
SRC_SHAR=sharutils-4.2.1.tar.gz
DIR_SHAR=sharutils-4.2.1
SRC_DPKG=dpkg-source-1.13.25.tar.gz
# OTHER FLAGS we can possibly use:
# -DPARSE_DISTRO_ONLY -DCONVERT_PS -DTIMING -DMEMORY_TRACING -DMEM_ACCT
# -DMEMSTATS -DUNPACK_DEBUG -DUNKNOWN_CHECK_DEBUG -DPHRASE_DEBUG
# -DDOCTOR_DEBUG -DLICENSE_DEBUG -DSEARCH_CACHE_DEBUG -DASSERTS 
# -DSAVE_UNCLASSIFIED_LICENSES -DPACKAGE_LABEL -DPACKAGE_DEBUG -DREPORT_DEBUG
# -DSTOPWATCH -DPARSE_STOPWATCH -DGLOBAL_DEBUG -DCHDIR_DEBUG -DLTSR_DEBUG
# -DQA_CHECKS -DBUCKET_DEBUG -DEXPIRE_DEBUG -DPHRASE_DEBUG -DLIST_DEBUG
# -DSHOW_LOCATION
## BASEFLAGS=-UDEBUG -DUSE_DPKG_SOURCE -DPACKAGE_LABEL -DCUSTOMER_VERSION \
#	-DNEED_PASSWORD -DSAVE_UNCLASSIFIED_LICENSES -DPRECHECK \
#	-DSAVE_REFLICENSES -Werror
BASEFLAGS=-D_FILE_OFFSET_BITS=64 -DHP_INTERNAL -D__USE_LARGEFILE64 \
	-DSHOW_LOCATION # -UDEBUG
CFLAGS=-g -Wall $(BASEFLAGS) # -DDOCTOR_DEBUG -DLTSR_DEBUG -DUNPACK_DEBUG -DPHRASE_DEBUG -DUNKNOWN_CHECK_DEBUG -DLICENSE_DEBUG -DPACKAGE_DEBUG -DSTOPWATCH -DPARSE_STOPWATCH -DREPORT_DEBUG -DBUCKET_DEBUG -DMEMSTATS -O0 
# LIBS=-lmagic -lpcreposix
LIBS=-lmagic
# CDB -- removed report.o, conf.o OBJS=_precheck.o conf.o _autodata.o licenses.o list.o md5.o nomos.o \
#	parse.o process.o regex.o report.o util.o # DMalloc.o

OBJS=_precheck.o _autodata.o licenses.o list.o nomos.o \
	parse.o process.o regex.o util.o # sources.o DMalloc.o

SRCS=$(OBJS:.o=.c)
HDRS=nomos.h _autodefs.h # mtags.h
ALLSRC=$(SRCS) $(HDRS) encode.c
LINTOPTS=-bufferoverflowhigh -predboolint -onlytrans -warnposix \
	-shiftnegative -boolops +boolint -nullpass -mustfreeonly \
	-mayaliasunique -shiftimplementation -mustfreefresh \
	-globstate -branchstate -nullderef -nullret -formatcode \
	-compdestroy # -DPROC_TRACE
SAVEFILES=$(SPEC) $(LICFIX) $(PRE) $(CHECK) $(PDATA) $(MF) $(CONF) $(ALLSRC) $(CRON) $(CGI) $(TOOL) $(DATA) $(CRT) $(EXT) $(TOOLSDIR)

all:	encode $(PROG) 

over:
	@touch $(SPEC)
	$(MAKE)

encode:	encode.c
	$(CC) $(CFLAGS) -o $@ $@.c

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

$(ARCH):	$(CONF) $(SAVEFILES)
	@echo Creating compressed tar image...
	tar zcf $(ARCH) $(SAVEFILES)
	ls -l $(ARCH)
	rm -f $(CONF)

$(CONF):
	cp -f EXT/$(CONF) .

$(KEYS):	$(SPEC)
	@grep %KEY% $(SPEC) | grep -v "^#" | sort | uniq -c | sort -nr > $(KEYS)

$(STRINGS): _autodata.c
	@grep -F 'Phrase[' _autodata.c > $(STRINGS)

tar tgz arch src:
	@rm -f $(ARCH)
	$(MAKE) $(ARCH)

newsrc:	
	@$(MAKE) clobber
	rm -f *.c *.h $(SPEC)
	tar zxf $(ARCH)
	touch $(SPEC)

sync dist:
	@echo Updating data-repository on ldl...
	@rsync -Hae ssh --delete $(HOME)/OSRB.new glen@ldl:public_html

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

copy:
	scp Makefile $(SPEC) $(LICFIX) $(PRE) $(CHECK) checkstrings *.c *.h glen@ldl:ospo-tools/nomos/

check:
	@./tools/checkstrings
#	@$(MAKE) -s uses

tswitch:
	$(MAKE) clean
	$(MAKE) "CFLAGS=$(CFLAGS) -DPROC_TRACE_SWITCH" $(PROG)

ts:	tswitch

stats:
	@echo Lines of code:
	@wc -l $$(ls *.c *.h $(SPEC) $(LICFIX) $(PRE) $(CHEC) queue*) | sort -nr
	@grep 'INTERESTING(' parse.c > _LIC_STR.INTERESTING
	@grep '_MIN_' $(SPEC) >> _LIC_STR.INTERESTING
	@grep 'LOWINTEREST(' parse.c > _LIC_STR.LOWINTEREST
	@printf 'License-strings in code: '; wc -l _LIC_STR* | grep 'total' | awk '{print $$1}'
	@grep 'INTERESTING(' parse.c | sed -e 's/lDebug ? ".*" : //g' -e "s/^[  ]*//g" | sort | uniq > _LIC_STR.INTERESTING.unique
	@grep '_MIN_' $(SPEC) >> _LIC_STR.INTERESTING.unique
	@grep 'LOWINTEREST(' parse.c | sed -e 's/lDebug ? ".*" : //g' -e "s/^[  ]*//g" | sort | uniq > _LIC_STR.LOWINTEREST.unique
	@wc -l _LIC_STR* | grep -v '[0-9] total'
	@printf '... Unique license-strings: '; wc -l _LIC_STR*unique | grep 'total' | awk '{print $$1}'
	@$(MAKE) -s uses

uses:
	@grep %ENTRY% $(SPEC) | tee _LIC_STR_XXX | egrep -v '(^#|_(UTIL_DICT|KW|ZZGEN|LEGAL|LT_INDEMN|MIN|TEXT_))' | grep -v 'LT_FREE_[0-9]' | awk '{print $$2}' > _LIC_STR_ALL
	@echo Checking $$(wc -l < _LIC_STR_XXX) identifiers for use, defined in $(SPEC)...
	@for i in $$(cat _LIC_STR_ALL); do \
	    X=$$(grep -l $$i [^_]*.c) ; \
	    [ -n "$$X" ] && continue ; \
	    echo "NOT USED: $$i" ; \
	done
	@rm -f _LIC_STR*
#	@awk '{print $$1}' _strings.data | sort | uniq -c | sort -nr

LINT Lint lint:
	splint $(LINTOPTS) $(SRCS) > $(LINTOUT)
##	splint $(LINTOPTS) $(SRCS) | grep -v longopts > LINT.out

save:	clean tags
	ssh glen@ldl rm -rf new
	scp -qpr $(HOME)/new glen@ldl:
	@echo Code saved, now you need to re-make

diff diffs:
	@echo Setting up last copy to diff...
	@rm -rf $(LAST); mkdir $(LAST); cd $(LAST); scp -q glen@ldl:new/*.[ch] .
	@for i in $(HDRS) $(SRCS) ; do \
		diff -urN $(LAST)/$$i $$i ; \
		done | less '+/---'
##		done

.PHONY: tags tool

tags:	
	@rm -f tags
	ctags $(SRCS)

tool:
	@echo "Handy command to reduce PROC_TRACE output:"
	@printf "\tLANG=C egrep -v '^(==|!!|=>|\.\.\.|#|    )'\n"
	@echo "Handy command to reduce MEMSTATS output:"
	@printf "\tLANG=C egrep -v '(^\.\.\.|^\*\*| VmRSS|static lists)'\n"
	@echo "Handy command to follow regex/license matching:"
	@printf "\tLANG=C egrep '(^Found|addRef|saveLicenseData|parseLicenses| this is file | score | used [0-9]*,|\<PROCESS\>)' X\n"

inst install:	all tools
	@if test "X$(NOMOS_BASE)" = "X" ; then \
		echo "Please set AND export NOMOS_BASE environment variable" ;\
		exit 1 ;\
	fi
	@if test ! -d $(NOMOS_BASE) ; then \
		echo "Creating directory $(NOMOS_BASE)..." ;\
		mkdir -p $(NOMOS_BASE) $(NOMOS_BASE)/db $(NOMOS_BASE)/ext ;\
	fi
	@if test ! -f $(NOMOS_BASE)/db/OSRBlists.tgz ; then \
		cp OSRBlists.tgz $(NOMOS_BASE)/db/ ;\
	fi
	@if test ! -f $(NOMOS_BASE)/ext/nomos.conf ; then \
		cp EXT/* $(NOMOS_BASE)/ext/ ;\
	fi
	@mkdir -p $(DIR_ULB)
	@if test ! -f $(DIR_ULB)/dpkg-source ; then \
		echo Extracting \"dpkg-source\" repository... ;\
		cp -f tools/$(SRC_DPKG) /tmp ;\
		cd /; tar zxf /tmp/$(SRC_DPKG) ;\
		rm -f /tmp/$(SRC_DPKG) ;\
	fi
	@if test ! -f $(DIR_ULB)/uuencode ; then \
		echo Building \"sharutils\" from source... ;\
		rm -rf tools/$(DIR_SHAR) ;\
		cd tools; tar zxf $(SRC_SHAR); cd $(DIR_SHAR); ./configure > ../MAKE_sharutils.out 2>&1; mv -f Makefile.sharutils.HACK Makefile; touch Makefile; make >> ../MAKE_sharutils.out 2>&1 ;\
		cp src/uudecode src/uuencode src/unshar $(DIR_ULB) ;\
	fi
	@if test ! -f $(DIR_ULB)/pstotext ; then \
		echo Building \"pstotext\" from source... ;\
		rm -rf tools/$(DIR_PS2T) ;\
		cd tools; tar zxf $(SRC_PS2T); cd $(DIR_PS2T); make > ../MAKE_pstotext.out 2>&1 ;\
		cp pstotext $(DIR_ULB) ;\
	fi
	@if test ! -f $(DIR_ULB)/fastjar ; then \
		rm -rf tools/$(DIR_JAR) ;\
		cd tools; tar zxf $(SRC_JAR); cd $(DIR_JAR); ./configure > ../MAKE_fastjar.out 2>&1; make >> ../MAKE_fastjar.out 2>&1 ;\
		cp fastjar grepjar $(DIR_ULB) ;\
		ln $(DIR_ULB)/fastjar $(DIR_ULB)/jar ;\
	fi
	@if test ! -f $(DIR_ULB)/cabextract ; then \
		echo Building \"cabextract\" from source... ;\
		rm -rf tools/$(DIR_CAB) ;\
		cd tools; tar zxf $(SRC_CAB); cd $(DIR_CAB); ./configure > ../MAKE_cabextract.out 2>&1; make >> ../MAKE_cabextract.out 2>&1 ;\
		cp cabextract $(DIR_ULB) ;\
	fi
	@if test ! -f $(DIR_ULB)/unrar ; then \
		echo Building \"unrar\" from source... ;\
		rm -rf tools/$(DIR_UNRAR) ;\
		cd tools; tar zxf $(SRC_UNRAR); cd $(DIR_UNRAR); make -f makefile.unix > ../MAKE_unrar.out 2>&1 ;\
		cp unrar $(DIR_ULB) ;\
	fi
	@echo "Testing install: results should be \"None\"..."
	@./nomos --file /etc/passwd 2>&1 | tee /tmp/NOMOS.OUT
	@if test -f /tmp/nomos.tempdir ; then \
		echo "Test installation failed!" ;\
		rm -rf /tmp/nomos.tempdir ;\
	elif echo None | diff -q /tmp/NOMOS.OUT - ; then \
		echo Installation test passed. ;\
	else \
		echo Test results not as expected. ;\
		echo Expected \"None\", got: ;\
		cat /tmp/NOMOS.OUT ;\
		rm -f /tmp/NOMOS.OUT ;\
	fi
		

$(OBJS):	$(HDRS)

clean:
	rm -f *.o $(LINTOUT)

reset:
	mv -f $(DBPATH)/$(DATA) /tmp
	rm -rf $(BASEPATH)
	mkdir -p $(DBPATH)
	mv /tmp/$(DATA) $(DBPATH)

# clobber: clean reset
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
