FOSSology copyright tests readme
Copyright (C) 2010 Hewlett-Packard Development Company, L.P

The test formatting for the copyright agent tests are different from other
agents for a couple of reasons:
1. The copyright agent is written in an object oriented fashion, as a result
   each function is much shorter and has only one purpose. As a result of this
   each function only needs a single test. This would result in a very large
   number of test files, each containing a single test.
2. Since each file needs to know about the internal structure of the related
   struct, each file would need to declare this individually. This would result
   is a large amount of duplicate code.

Because of these two reasons, each file (copyright.c cvector.c radixtree.c) got
its own test file, where all of the functions are tested for completeness. This
results in only three files and a much more succinct set of tests. However, it
was important, given this particular test structure, to make sure that no
functions were used without previously being tested.



