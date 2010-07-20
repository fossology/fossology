## FOSSology Makefile - agents/copyright_analysis
## 
## Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
## 
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

COPYRIGHTPATH=$(AGENTDIR)/copyright

all: model.dat vars.py

model.dat: run.py vars.py copyright.py copyright_library.py data.txt $(PYTHONLIB)
	./run.py --model model.dat --training data.txt
	
install: all
	if [ ! -d "$(DESTDIR)$(COPYRIGHTPATH)" ] ; then \
		mkdir -p "$(DESTDIR)$(COPYRIGHTPATH)"; \
	fi
	$(INSTALL_PROGRAM) run.py $(DESTDIR)$(COPYRIGHTPATH)/run.py
	$(INSTALL_PROGRAM) copyright.py $(DESTDIR)$(COPYRIGHTPATH)/copyright.py
	$(INSTALL_DATA) vars.py $(DESTDIR)$(COPYRIGHTPATH)/vars.py
	$(INSTALL_DATA) copyright_library.py $(DESTDIR)$(COPYRIGHTPATH)/copyright_library.py
	$(INSTALL_DATA) model.dat $(DESTDIR)$(DATADIR)/model.dat
	make -C ui-plugin install

uninstall: 
	rm -rf $(DESTDIR)$(COPYRIGHTPATH)
	rm -rf $(DESTDIR)$(DATADIR)/model.dat
	make -C ui-plugin uninstall

test: all
	@echo "*** No tests available for agents/copyright_analysis ***"

clean:
	rm -f model.dat training_files vars.py *.pyc

include $(DEPS)

.PHONY: all install uninstall clean test

vars.py:
	@rm -f $@
	@echo -n "DATADIR = '''" >> $@
	@echo -n $(DATADIR) >> $@
	@echo    "'''" >> $@
	@echo -n "AGENTDIR = '''" >> $@
	@echo -n $(AGENTDIR) >> $@
	@echo    "'''" >> $@
	@echo -n "PYTHONLIBPATH = '''" >> $@
	@echo -n $(PYTHONLIBPATH) >> $@
	@echo    "'''" >> $@
	@echo -n "COPYRIGHTPATH = '''" >> $@
	@echo -n $(COPYRIGHTPATH) >> $@
	@echo    "'''" >> $@
	@echo -n "PYTHONLIBEXECDIR = '''" >> $@
	@echo -n $(PYTHONLIBEXECDIR) >> $@
	@echo    "'''" >> $@

