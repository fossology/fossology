How to use the License.bsam file...
Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

This is a cache file containing licenses.

The steps for adding a license:

1. Add the license under the "Raw" directory tree.
   This should be the actual license file.
   The directory tree is based on related licenses (since some licenses
   borrow heavily from other licenses.)

   - It is best if you cut out all of the non-sequential non-legal items.
     For example, the real "GPL" file contains historical information,
     legal wording, and sample usage.  You should only keep the legal wording.

   - Many licenses have small "reference" paragraphs.
     For example, you don't need to include the entire GPL, you just need
     a paragraph saying "this is GPL".
     Save these as separate files.  I use "(ref)" to indicate them.
     For example, there is a file called "GPL" and a file "GPL(ref)".
     If there are different common variations, then they should be enumerated
     such as GPL(ref2) and GPL(ref3).

   - If the license has a fake name, like "YEAR" or "XXXX" in the copyright
     section, then change these to a real year.  If it says "[NAME]" then
     just change it to "NAME".  The filter program converts numbers to
     a special year token, and "[NAME]" becomes 3 tokens: [ NAME ].  Changing
     it to one name (or better yet, removing it) reduces false negatives.
     For example, Apache 2.0 contains the following reference template:
	=====
	Copyright [yyyy] [name of copyright owner]

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0
	=====
     The first copyright line should be changed to:
	=====
	Copyright 2000
	=====
       - The year value is not important for bSAM -- it becomes a year token.
       - The name is not a key word to match, so just remove it.


2. make clean ; make
   This will build the License.bsam file.

3. Test the file.
   ./SelfTest
     This will create a file called "self-compare" showing the best match.
     Make sure your license does not match any other licenses.
     This might take 5 minutes to run.  (More licenses take more time.)

   ./SelfTest2
     This will show ALL matches > 60%.
     This might take 5 minutes to run.  (More licenses take more time.)
     Make sure your license is not too close to some other license.
     If it is too close, see if the different is due to a single word change.
     Try not to include licenses where the only word change is a company name.
     (This is OK since company names do not change the legal meaning, and
     other license variations will probably have different company names.)
     Also, use this to make sure you planted the files in the right tree.

4. If it all looks well, then: sudo make install
   And use svn to add and check in the file.

