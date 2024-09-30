-- SPDX-FileCopyrightText: © Fossology contributors

-- SPDX-License-Identifier: GPL-2.0-only

INSERT INTO agent VALUES (1, 'nomos', '2.4.1-ng.695dd8', 'License Scanner', true, NULL, '2015-05-04 11:37:39.12504+02');
INSERT INTO agent VALUES (2, 'delagent', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.477678+02');
INSERT INTO agent VALUES (3, 'maintagent', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.597601+02');
INSERT INTO agent VALUES (4, 'mimetype', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.623748+02');
INSERT INTO agent VALUES (5, 'reportgen', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.62586+02');
INSERT INTO agent VALUES (6, 'adj2nest', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.636537+02');
INSERT INTO agent VALUES (7, 'monkbulk', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.637863+02');
INSERT INTO agent VALUES (8, 'monk', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.642129+02');
INSERT INTO agent VALUES (10, 'pkgagent', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.669964+02');
INSERT INTO agent VALUES (11, 'ecc', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.6724+02');
INSERT INTO agent VALUES (12, 'buckets', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.673072+02');
INSERT INTO agent VALUES (13, 'wget_agent', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.681217+02');
INSERT INTO agent VALUES (14, 'ununpack', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.732657+02');
INSERT INTO agent VALUES (15, 'copyright', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.820557+02');
INSERT INTO agent VALUES (16, 'ninka', '2.4.1-ng.695dd8', '(null)', true, NULL, '2015-05-04 11:42:21.820836+02');
INSERT INTO agent VALUES (17, 'deciderjob', '2.4.1-ng.695dd8', 'deciderjob agent', true, NULL, '2015-05-04 11:42:22.130801+02');
INSERT INTO agent VALUES (18, 'report', '2.4.1-ng.695dd8', 'report agent', true, NULL, '2015-05-04 11:42:22.155375+02');
INSERT INTO agent VALUES (19, 'readmeoss', '2.4.1-ng.695dd8', 'readmeoss agent', true, NULL, '2015-05-04 11:42:22.170878+02');
INSERT INTO agent VALUES (20, 'decider', '2.4.1-ng.695dd8', 'decider agent', true, NULL, '2015-05-04 11:42:22.24854+02');
INSERT INTO agent VALUES (21, 'reuser', '2.4.1-ng.695dd8', 'reuser agent', true, NULL, '2015-05-04 11:42:22.306605+02');


INSERT INTO upload VALUES (1, '', 'ReportTestfiles.tar', 2, 104, '2015-05-04 11:43:14.866898+02', 1, 'ReportTestfiles.tar', 'uploadtree_a', NULL, NULL, 0);


INSERT INTO bucketpool VALUES (1, 'GPL Demo bucket pool', 1, 'Y', 'Demonstration of a very simple GPL/non-gpl bucket pool');


INSERT INTO bucket_def VALUES (1, 'GPL Licenses (Demo)', 'orange', 50, 50, 1, 3, '(affero|gpl)', NULL, 'N', 'f');
INSERT INTO bucket_def VALUES (2, 'non-gpl (Demo)', 'yellow', 50, 1000, 1, 99, NULL, NULL, 'N', 'f');


INSERT INTO groups VALUES (1, 'Default User');
INSERT INTO groups VALUES (2, 'fossy');


INSERT INTO mimetype VALUES (1, 'application/gzip');
INSERT INTO mimetype VALUES (2, 'application/x-gzip');
INSERT INTO mimetype VALUES (3, 'application/x-compress');
INSERT INTO mimetype VALUES (4, 'application/x-bzip');
INSERT INTO mimetype VALUES (5, 'application/x-bzip2');
INSERT INTO mimetype VALUES (6, 'application/x-upx');
INSERT INTO mimetype VALUES (7, 'application/pdf');
INSERT INTO mimetype VALUES (8, 'application/x-pdf');
INSERT INTO mimetype VALUES (9, 'application/x-zip');
INSERT INTO mimetype VALUES (10, 'application/zip');
INSERT INTO mimetype VALUES (11, 'application/x-tar');
INSERT INTO mimetype VALUES (12, 'application/x-gtar');
INSERT INTO mimetype VALUES (13, 'application/x-cpio');
INSERT INTO mimetype VALUES (14, 'application/x-rar');
INSERT INTO mimetype VALUES (15, 'application/x-cab');
INSERT INTO mimetype VALUES (16, 'application/x-7z-compressed');
INSERT INTO mimetype VALUES (17, 'application/x-7z-w-compressed');
INSERT INTO mimetype VALUES (18, 'application/x-rpm');
INSERT INTO mimetype VALUES (19, 'application/x-archive');
INSERT INTO mimetype VALUES (20, 'application/x-debian-package');
INSERT INTO mimetype VALUES (21, 'application/x-iso');
INSERT INTO mimetype VALUES (22, 'application/x-iso9660-image');
INSERT INTO mimetype VALUES (23, 'application/x-fat');
INSERT INTO mimetype VALUES (24, 'application/x-ntfs');
INSERT INTO mimetype VALUES (25, 'application/x-ext2');
INSERT INTO mimetype VALUES (26, 'application/x-ext3');
INSERT INTO mimetype VALUES (27, 'application/x-x86_boot');
INSERT INTO mimetype VALUES (28, 'application/x-debian-source');
INSERT INTO mimetype VALUES (29, 'application/x-xz');
INSERT INTO mimetype VALUES (30, 'application/jar');
INSERT INTO mimetype VALUES (31, 'application/x-dosexec');



INSERT INTO pfile VALUES (1, '149FD9DAC3A1FF6AD491F95F49E90BF2', '403C2B25B9B02A2EBB6817C459B37AFC1F9BA3B5', 'b4c2050b25d3d296d5cf58589ca00816dc72df42262c2f629d5c6a984a161aa4', 35328, 11);
INSERT INTO pfile VALUES (2, '8F4010C5B689A6EA4A28671FD1907F23', '75E8FBDFAB38D5406BD718711D8FDDDB530CA174', 'fda70df85987b394ff384b899703bc0e55ac7bdba94d06f47462e155cf0c0350', 6880, NULL);
INSERT INTO pfile VALUES (3, 'C2047D353D61BCE5D6335CC1C30C1780', '2634F1C5473C7A9E9B9238EC2AAB1FCA468911A8', '1c8d3cc6810ecd3623ebff7d2c3db1a44024260c5ae662f8166d69b9425828ed', 6894, NULL);
INSERT INTO pfile VALUES (4, 'BE09F57E1E58119F1537439BA545835C', 'FD7D17CFA15074F73F183C086B1983EB9F31781F', '395f150240d43dff8baea6586baf5665337de57b8204a501fbd6148b2fe165b7', 296, NULL);
INSERT INTO pfile VALUES (5, '39C379E9C7F5BB524754C4DEF5FEF135', '840B588279248271D93ACA3092AD5F4DC724BDA4', '47a4cee30c085c497e628bab975fd586b5bed6fb25cc2720ae17339937436158', 285, NULL);
INSERT INTO pfile VALUES (6, '2702A657B801333C3150BDC8BE642F9B', '798826BF3EB294E5D514ECFA3222CCF09BBCD985', '8df6eb5d69ffe2bf61937d49f3ef72e98213fd09f9ad41c626e419503178bacd', 14554, NULL);


INSERT INTO users (user_pk, user_name, root_folder_fk, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, upload_visibility, user_agent_list, default_bucketpool_fk, ui_preference, new_upload_group_fk, new_upload_perm, default_folder_fk) VALUES (1, 'Default User', 1, 'Default User when nobody is logged in', 'Seed', 'Pass', 0, NULL, 'y', 'public', NULL, NULL, 'simple', NULL, NULL, 1);
INSERT INTO users (user_pk, user_name, root_folder_fk, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, upload_visibility, user_agent_list, default_bucketpool_fk, ui_preference, new_upload_group_fk, new_upload_perm, default_folder_fk) VALUES (2, 'fossy', 1, 'Default Administrator', '16294329171791449506', 'b27fd9578d6893916952c2fb74a64bbc9e1bf0b9', 10, 'y', 'y', 'public', 'agent_copyright,agent_mimetype,agent_monk,agent_nomos,agent_pkgagent', 1, '', NULL, NULL, 1);


INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (2, 4, 2, 2, 2, 5, 1, '2015-05-04 11:43:18.276425+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (4, 5, 3, 2, 2, 5, 1, '2015-05-04 11:43:18.297719+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (7, 10, 6, 2, 2, 5, 0, '2015-05-04 11:44:10.097161+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (8, 10, 6, 2, 2, 4, 0, '2015-05-04 11:45:11.88761+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (10, 7, 4, 2, 2, 5, 0, '2015-05-04 11:45:30.851906+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (12, 8, 5, 2, 2, 5, 0, '2015-05-04 11:45:34.941938+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (14, 5, 3, 2, 2, 5, 0, '2015-05-04 11:46:26.59626+02');
INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added) VALUES (16, 4, 2, 2, 2, 5, 0, '2015-05-04 11:47:10.946366+02');



INSERT INTO clearing_decision_event VALUES (1, 2);
INSERT INTO clearing_decision_event VALUES (2, 4);
INSERT INTO clearing_decision_event VALUES (4, 7);
INSERT INTO clearing_decision_event VALUES (3, 7);
INSERT INTO clearing_decision_event VALUES (3, 8);
INSERT INTO clearing_decision_event VALUES (4, 8);
INSERT INTO clearing_decision_event VALUES (5, 10);
INSERT INTO clearing_decision_event VALUES (6, 12);
INSERT INTO clearing_decision_event VALUES (7, 14);
INSERT INTO clearing_decision_event VALUES (8, 16);



INSERT INTO clearing_event VALUES (1, 4, 485, false, 2, 2, NULL, 3, '', '', '', '2015-05-04 11:43:18.276425+02');
INSERT INTO clearing_event VALUES (2, 5, 272, false, 2, 2, NULL, 3, '', '', '', '2015-05-04 11:43:18.297719+02');
INSERT INTO clearing_event VALUES (3, 10, 561, true, 2, 2, NULL, 1, '', '', '', '2015-05-04 11:44:07.229472+02');
INSERT INTO clearing_event VALUES (4, 10, 560, false, 2, 2, NULL, 3, '', '', '', '2015-05-04 11:44:10.097161+02');
INSERT INTO clearing_event VALUES (5, 7, 199, false, 2, 2, NULL, 3, '', '', '', '2015-05-04 11:45:30.851906+02');
INSERT INTO clearing_event VALUES (6, 8, 199, false, 2, 2, NULL, 3, '', '', '', '2015-05-04 11:45:34.941938+02');
INSERT INTO clearing_event VALUES (7, 5, 272, false, 2, 2, NULL, 3, 'all Nomos findings are within the Monk findings', '', '', '2015-05-04 11:46:22.698323+02');
INSERT INTO clearing_event VALUES (8, 4, 485, false, 2, 2, NULL, 3, '', 'Here is an alternative license text.', '', '2015-05-04 11:47:09.615748+02');
INSERT INTO clearing_event VALUES (9, 4, 485, false, 2, 2, NULL, 3, '', '', 'Here is an acknowledgement.', '2015-05-04 11:47:09.615748+02');



INSERT INTO copyright VALUES (1, 15, 4, 'Copyright:
', '82b9c4a898c9c8e7dd3b2f12c9efe2f1', 'statement', 85, 96, 'true');
INSERT INTO copyright VALUES (2, 15, 4, 'Copyright 2004 XXX 3dfx Interactive. conspicuously and appropriately publish on each copy of a derivative work">
', '257e7a16fdea45af00db34b34901297e', 'statement', 98, 212, 'true');
INSERT INTO copyright VALUES (3, 15, 2, 'Copyright © 1990-2007 Condor Team, Computer Sciences Department, University of Wisconsin-Madison, Madison, WI. All Rights Reserved. For more information contact: Condor Team, Attention: Professor Miron Livny, Dept of Computer Sciences, 1210 W. Dayton St., Madison, WI 53706-1685, (608) 262-0856 or miron@cs.wisc.edu.', '38fed8260a51272cce26cb9a9e8ae150', 'statement', 288, 605, 'true');
INSERT INTO copyright VALUES (4, 15, 2, 'Copyright (c) 1999 University of Chicago and The University of Southern California. All Rights Reserved.', '838e7addcd532fd109fcf9046b830fcb', 'statement', 6315, 6419, 'true');
INSERT INTO copyright VALUES (5, 15, 2, 'http://www.condorproject.org/', '7b3bded8d05be4f8f286137d781671c5', 'url', 816, 845, 'true');
INSERT INTO copyright VALUES (6, 15, 2, 'http://www.condorproject.org/)"', '5f99b6394b68ba3efd6355d001cf5c9c', 'url', 1537, 1568, 'true');
INSERT INTO copyright VALUES (7, 15, 2, 'http://pages.cs.wisc.edu/~miron/miron.html', '8c35167907ce1778906c09d05ecf2008', 'url', 6096, 6138, 'true');
INSERT INTO copyright VALUES (8, 15, 2, 'http://www.globus.org/)', '01792705aacafab69309d1a5b10a1ff3', 'url', 6238, 6261, 'true');
INSERT INTO copyright VALUES (9, 15, 2, 'http://www.gnu.org/software/libc/', '0083c8b416c911ebd402eb58b58d6aee', 'url', 6569, 6602, 'true');
INSERT INTO copyright VALUES (10, 15, 2, 'miron@cs.wisc.edu', '1102feddb903c81db7bd06a95548f49f', 'email', 587, 604, 'true');
INSERT INTO copyright VALUES (11, 15, 2, 'condor-admin@cs.wisc.edu', '330bfb156248b5c4a07d7bb14b2e7b86', 'email', 2245, 2269, 'true');
INSERT INTO copyright VALUES (12, 15, 2, 'miron@cs.wisc.edu', '1102feddb903c81db7bd06a95548f49f', 'email', 6078, 6095, 'true');
INSERT INTO copyright VALUES (13, 15, 2, 'CONTRIBUTORS', '98f07bc20cb66328be238119df96c490', 'author', 3686, 3698, 'true');
INSERT INTO copyright VALUES (14, 15, 2, 'CONTRIBUTORS MAKE NO REPRESENTATION THAT THE SOFTWARE, MODIFICATIONS, ENHANCEMENTS OR DERIVATIVE WORKS THEREOF, WILL NOT INFRINGE ANY PATENT, COPYRIGHT, TRADEMARK, TRADE SECRET OR OTHER PROPRIETARY RIGHT.', 'f024c21e035ed961f01c504644199975', 'author', 3931, 4135, 'true');
INSERT INTO copyright VALUES (15, 15, 2, 'CONTRIBUTORS SHALL HAVE NO LIABILITY TO LICENSEE OR OTHER PERSONS FOR DIRECT, INDIRECT, SPECIAL, INCIDENTAL, CONSEQUENTIAL, EXEMPLARY, OR PUNITIVE DAMAGES OF ANY CHARACTER INCLUDING, WITHOUT LIMITATION, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES, LOSS OF USE, DATA OR PROFITS, OR BUSINESS INTERRUPTION, HOWEVER CAUSED AND ON ANY THEORY OF CONTRACT, WARRANTY, TORT', '9d35c5020b1a224f65a49c8615821d91', 'author', 4196, 4560, 'true');
INSERT INTO copyright VALUES (16, 15, 5, 'Copyright:
', '82b9c4a898c9c8e7dd3b2f12c9efe2f1', 'statement', 79, 90, 'true');
INSERT INTO copyright VALUES (17, 15, 5, 'Copyright 2004 XXX 3dfx Interactive. conspicuously and appropriately publish on each copy of a derivative work">
', '257e7a16fdea45af00db34b34901297e', 'statement', 92, 206, 'true');
INSERT INTO copyright VALUES (18, 15, 6, 'Copyright (C) 1991-2, RSA Data Security, Inc. Created 1991. All rights reserved.', 'b3a0b78c4f3bda49400c03e2dc4af2fe', 'statement', 343, 426, 'true');
INSERT INTO copyright VALUES (19, 15, 6, 'phk@login.dknet.dk', 'be9a31b6132e46a413774b36859fb540', 'email', 1685, 1703, 'true');
INSERT INTO copyright VALUES (20, 15, 6, 'andersen@uclibc.org', 'b80a70781d6e6a7bba4953b6ecd2e214', 'email', 2185, 2204, 'true');
INSERT INTO copyright VALUES (21, 15, 3, 'Copyright (c) 1990-2006 Condor Team, Computer Sciences Department, University of Wisconsin-Madison, Madison, WI. All Rights Reserved. For more information contact: Condor Team, Attention: Professor Miron Livny, Dept of Computer Sciences, 1210 W. Dayton St., Madison, WI 53706-1685, 608) 262-0856 or miron@cs.wisc.edu.', '80b1b708b6c123ebcbab66818e432351', 'statement', 54, 370, 'true');
INSERT INTO copyright VALUES (23, 15, 3, 'http://www.condorproject.org/', '7b3bded8d05be4f8f286137d781671c5', 'url', 820, 849, 'true');
INSERT INTO copyright VALUES (24, 15, 3, 'http://www.condorproject.org/)"', '5f99b6394b68ba3efd6355d001cf5c9c', 'url', 1545, 1576, 'true');
INSERT INTO copyright VALUES (25, 15, 3, 'http://www.cs.wisc.edu/~miron/miron.html', 'a9731964cbb7701ae0191d610f8d2224', 'url', 6363, 6403, 'true');
INSERT INTO copyright VALUES (26, 15, 3, 'http://www.globus.org/)', '01792705aacafab69309d1a5b10a1ff3', 'url', 6509, 6532, 'true');
INSERT INTO copyright VALUES (27, 15, 3, 'http://www.gnu.org/software/libc/', '0083c8b416c911ebd402eb58b58d6aee', 'url', 6858, 6891, 'true');
INSERT INTO copyright VALUES (28, 15, 3, 'miron@cs.wisc.edu', '1102feddb903c81db7bd06a95548f49f', 'email', 352, 369, 'true');
INSERT INTO copyright VALUES (29, 15, 3, 'condor-admin@cs.wisc.edu', '330bfb156248b5c4a07d7bb14b2e7b86', 'email', 2295, 2319, 'true');
INSERT INTO copyright VALUES (30, 15, 3, 'miron@cs.wisc.edu', '1102feddb903c81db7bd06a95548f49f', 'email', 6345, 6362, 'true');
INSERT INTO copyright VALUES (31, 15, 3, 'authority', '873e9c0b50183b613336eea1020f4369', 'author', 570, 579, 'true');
INSERT INTO copyright VALUES (32, 15, 3, 'Contributors and the University', '1b9da6873af4fafcecb86a7778e976b8', 'author', 730, 761, 'true');
INSERT INTO copyright VALUES (33, 15, 3, 'CONTRIBUTORS AND THE UNIVERSITY', 'ef6a95f6baea2c6f551161de39c4a67f', 'author', 3826, 3863, 'true');
INSERT INTO copyright VALUES (34, 15, 3, 'CONTRIBUTORS AND THE UNIVERSITY MAKE NO REPRESENTATION THAT THE', '57d9c9d62a47e75d9f90ba05c82748e5', 'author', 4120, 4183, 'true');
INSERT INTO copyright VALUES (35, 15, 3, 'CONTRIBUTORS AND ANY OTHER OFFICER', '1cb39133d8d92e45813cd58562660ee1', 'author', 4426, 4460, 'true');


INSERT INTO copyright_ars VALUES (2, 15, 1, true, NULL, '2015-05-04 11:43:17.244277+02', '2015-05-04 11:43:17.304726+02');


INSERT INTO copyright_decision VALUES (1, 2, 2, 5, '', '', '');
INSERT INTO copyright_decision VALUES (2, 2, 3, 4, '', '', '');
INSERT INTO copyright_decision VALUES (3, 2, 6, 4, '', '', '');
INSERT INTO copyright_decision VALUES (4, 2, 3, 5, '', '', '');



INSERT INTO decider_ars VALUES (5, 20, 1, true, NULL, '2015-05-04 11:43:18.236117+02', '2015-05-04 11:43:18.305526+02');



INSERT INTO folder (folder_pk, folder_name, user_fk, folder_desc, folder_perm) VALUES (1, 'Software Repository', 2, 'Top Folder', NULL);



INSERT INTO foldercontents VALUES (1, 1, 0, 0);
INSERT INTO foldercontents VALUES (2, 1, 2, 1);


INSERT INTO group_user_member VALUES (1, 1, 1, 1);
INSERT INTO group_user_member VALUES (2, 2, 2, 1);



INSERT INTO highlight VALUES ('L ', 867, 71, 3, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 234, 34, 3, NULL, NULL);
INSERT INTO highlight VALUES ('M ', 234, 5709, 4, 0, 5666);
INSERT INTO highlight VALUES ('M0', 0, 1129, 6, 0, 1137);
INSERT INTO highlight VALUES ('M+', 1134, 2, 6, 1141, 0);
INSERT INTO highlight VALUES ('M0', 1137, 230, 6, 1141, 221);
INSERT INTO highlight VALUES ('M+', 1372, 2, 6, 1364, 0);
INSERT INTO highlight VALUES ('M0', 1375, 363, 6, 1364, 322);
INSERT INTO highlight VALUES ('M+', 1743, 2, 6, 1690, 0);
INSERT INTO highlight VALUES ('M0', 1746, 172, 6, 1690, 166);
INSERT INTO highlight VALUES ('M+', 1923, 2, 6, 1858, 0);
INSERT INTO highlight VALUES ('M0', 1926, 398, 6, 1858, 383);
INSERT INTO highlight VALUES ('M+', 2329, 2, 6, 2243, 0);
INSERT INTO highlight VALUES ('M0', 2332, 1417, 6, 2243, 1330);
INSERT INTO highlight VALUES ('M+', 3754, 2, 6, 3577, 0);
INSERT INTO highlight VALUES ('M0', 3757, 608, 6, 3577, 554);
INSERT INTO highlight VALUES ('M+', 4370, 2, 6, 4135, 0);
INSERT INTO highlight VALUES ('M0', 4373, 695, 6, 4135, 635);
INSERT INTO highlight VALUES ('M+', 5073, 2, 6, 4774, 0);
INSERT INTO highlight VALUES ('M0', 5076, 568, 6, 4774, 544);
INSERT INTO highlight VALUES ('M+', 5649, 2, 6, 5320, 0);
INSERT INTO highlight VALUES ('M0', 5652, 755, 6, 5320, 746);
INSERT INTO highlight VALUES ('L ', 871, 71, 7, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 0, 34, 7, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 98, 36, 8, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 136, 73, 8, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 92, 36, 9, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 130, 73, 9, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 353, 35, 11, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 691, 29, 11, NULL, NULL);
INSERT INTO highlight VALUES ('L ', 1639, 269, 10, NULL, NULL);



INSERT INTO highlight_keyword VALUES (2, 1619, 14);
INSERT INTO highlight_keyword VALUES (2, 1657, 14);
INSERT INTO highlight_keyword VALUES (2, 3700, 5);
INSERT INTO highlight_keyword VALUES (2, 39, 9);
INSERT INTO highlight_keyword VALUES (2, 288, 9);
INSERT INTO highlight_keyword VALUES (2, 298, 2);
INSERT INTO highlight_keyword VALUES (2, 3664, 9);
INSERT INTO highlight_keyword VALUES (2, 3909, 9);
INSERT INTO highlight_keyword VALUES (2, 4073, 9);
INSERT INTO highlight_keyword VALUES (2, 4174, 9);
INSERT INTO highlight_keyword VALUES (2, 6315, 9);
INSERT INTO highlight_keyword VALUES (2, 6325, 3);
INSERT INTO highlight_keyword VALUES (2, 6504, 2);
INSERT INTO highlight_keyword VALUES (2, 4343, 7);
INSERT INTO highlight_keyword VALUES (2, 4712, 7);
INSERT INTO highlight_keyword VALUES (2, 2665, 6);
INSERT INTO highlight_keyword VALUES (2, 4017, 6);
INSERT INTO highlight_keyword VALUES (2, 909, 9);
INSERT INTO highlight_keyword VALUES (2, 1131, 9);
INSERT INTO highlight_keyword VALUES (2, 1420, 9);
INSERT INTO highlight_keyword VALUES (2, 2154, 9);
INSERT INTO highlight_keyword VALUES (2, 6430, 9);
INSERT INTO highlight_keyword VALUES (2, 2429, 5);
INSERT INTO highlight_keyword VALUES (2, 3295, 5);
INSERT INTO highlight_keyword VALUES (2, 3473, 5);
INSERT INTO highlight_keyword VALUES (2, 4154, 10);
INSERT INTO highlight_keyword VALUES (2, 4222, 10);
INSERT INTO highlight_keyword VALUES (2, 4592, 10);
INSERT INTO highlight_keyword VALUES (2, 4962, 10);
INSERT INTO highlight_keyword VALUES (2, 53, 6);
INSERT INTO highlight_keyword VALUES (2, 248, 6);
INSERT INTO highlight_keyword VALUES (2, 1047, 6);
INSERT INTO highlight_keyword VALUES (2, 1074, 6);
INSERT INTO highlight_keyword VALUES (2, 1227, 6);
INSERT INTO highlight_keyword VALUES (2, 2309, 6);
INSERT INTO highlight_keyword VALUES (2, 2489, 6);
INSERT INTO highlight_keyword VALUES (2, 2695, 6);
INSERT INTO highlight_keyword VALUES (2, 2919, 6);
INSERT INTO highlight_keyword VALUES (2, 3019, 6);
INSERT INTO highlight_keyword VALUES (2, 3073, 6);
INSERT INTO highlight_keyword VALUES (2, 3345, 6);
INSERT INTO highlight_keyword VALUES (2, 3423, 6);
INSERT INTO highlight_keyword VALUES (2, 3547, 6);
INSERT INTO highlight_keyword VALUES (2, 4236, 6);
INSERT INTO highlight_keyword VALUES (2, 4726, 6);
INSERT INTO highlight_keyword VALUES (2, 4852, 6);
INSERT INTO highlight_keyword VALUES (2, 4932, 6);
INSERT INTO highlight_keyword VALUES (2, 5179, 6);
INSERT INTO highlight_keyword VALUES (2, 5293, 6);
INSERT INTO highlight_keyword VALUES (2, 5390, 6);
INSERT INTO highlight_keyword VALUES (2, 5482, 6);
INSERT INTO highlight_keyword VALUES (2, 5497, 6);
INSERT INTO highlight_keyword VALUES (2, 5654, 6);
INSERT INTO highlight_keyword VALUES (2, 5821, 6);
INSERT INTO highlight_keyword VALUES (2, 5935, 6);
INSERT INTO highlight_keyword VALUES (2, 2295, 6);
INSERT INTO highlight_keyword VALUES (2, 2508, 6);
INSERT INTO highlight_keyword VALUES (2, 2688, 6);
INSERT INTO highlight_keyword VALUES (2, 2892, 6);
INSERT INTO highlight_keyword VALUES (2, 2912, 6);
INSERT INTO highlight_keyword VALUES (2, 3109, 6);
INSERT INTO highlight_keyword VALUES (2, 3255, 6);
INSERT INTO highlight_keyword VALUES (2, 3338, 6);
INSERT INTO highlight_keyword VALUES (2, 3540, 6);
INSERT INTO highlight_keyword VALUES (2, 4065, 6);
INSERT INTO highlight_keyword VALUES (2, 2088, 7);
INSERT INTO highlight_keyword VALUES (2, 4816, 7);
INSERT INTO highlight_keyword VALUES (2, 6629, 7);
INSERT INTO highlight_keyword VALUES (2, 973, 17);
INSERT INTO highlight_keyword VALUES (2, 2628, 11);
INSERT INTO highlight_keyword VALUES (2, 6528, 11);
INSERT INTO highlight_keyword VALUES (2, 1087, 10);
INSERT INTO highlight_keyword VALUES (2, 6674, 20);
INSERT INTO highlight_keyword VALUES (2, 3734, 7);
INSERT INTO highlight_keyword VALUES (2, 3789, 7);
INSERT INTO highlight_keyword VALUES (2, 4546, 7);
INSERT INTO highlight_keyword VALUES (2, 4379, 13);
INSERT INTO highlight_keyword VALUES (3, 1628, 14);
INSERT INTO highlight_keyword VALUES (3, 1672, 14);
INSERT INTO highlight_keyword VALUES (3, 3865, 5);
INSERT INTO highlight_keyword VALUES (3, 54, 9);
INSERT INTO highlight_keyword VALUES (3, 708, 9);
INSERT INTO highlight_keyword VALUES (3, 3804, 9);
INSERT INTO highlight_keyword VALUES (3, 4092, 9);
INSERT INTO highlight_keyword VALUES (3, 4293, 9);
INSERT INTO highlight_keyword VALUES (3, 4404, 9);
INSERT INTO highlight_keyword VALUES (3, 6590, 9);
INSERT INTO highlight_keyword VALUES (3, 6600, 3);
INSERT INTO highlight_keyword VALUES (3, 6789, 2);
INSERT INTO highlight_keyword VALUES (3, 4651, 7);
INSERT INTO highlight_keyword VALUES (3, 5056, 7);
INSERT INTO highlight_keyword VALUES (3, 2732, 6);
INSERT INTO highlight_keyword VALUES (3, 4231, 6);
INSERT INTO highlight_keyword VALUES (3, 913, 9);
INSERT INTO highlight_keyword VALUES (3, 1137, 9);
INSERT INTO highlight_keyword VALUES (3, 1414, 9);
INSERT INTO highlight_keyword VALUES (3, 2201, 9);
INSERT INTO highlight_keyword VALUES (3, 6711, 9);
INSERT INTO highlight_keyword VALUES (3, 2487, 5);
INSERT INTO highlight_keyword VALUES (3, 3401, 5);
INSERT INTO highlight_keyword VALUES (3, 3597, 5);
INSERT INTO highlight_keyword VALUES (3, 4382, 10);
INSERT INTO highlight_keyword VALUES (3, 4518, 10);
INSERT INTO highlight_keyword VALUES (3, 4924, 10);
INSERT INTO highlight_keyword VALUES (3, 5598, 10);
INSERT INTO highlight_keyword VALUES (3, 14, 6);
INSERT INTO highlight_keyword VALUES (3, 1051, 6);
INSERT INTO highlight_keyword VALUES (3, 1072, 6);
INSERT INTO highlight_keyword VALUES (3, 1236, 6);
INSERT INTO highlight_keyword VALUES (3, 2361, 6);
INSERT INTO highlight_keyword VALUES (3, 2550, 6);
INSERT INTO highlight_keyword VALUES (3, 2765, 6);
INSERT INTO highlight_keyword VALUES (3, 2998, 6);
INSERT INTO highlight_keyword VALUES (3, 3104, 6);
INSERT INTO highlight_keyword VALUES (3, 3160, 6);
INSERT INTO highlight_keyword VALUES (3, 3457, 6);
INSERT INTO highlight_keyword VALUES (3, 3541, 6);
INSERT INTO highlight_keyword VALUES (3, 3677, 6);
INSERT INTO highlight_keyword VALUES (3, 4538, 6);
INSERT INTO highlight_keyword VALUES (3, 5230, 6);
INSERT INTO highlight_keyword VALUES (3, 5273, 6);
INSERT INTO highlight_keyword VALUES (3, 5491, 6);
INSERT INTO highlight_keyword VALUES (3, 5612, 6);
INSERT INTO highlight_keyword VALUES (3, 5729, 6);
INSERT INTO highlight_keyword VALUES (3, 5745, 6);
INSERT INTO highlight_keyword VALUES (3, 5908, 6);
INSERT INTO highlight_keyword VALUES (3, 6084, 6);
INSERT INTO highlight_keyword VALUES (3, 6201, 6);
INSERT INTO highlight_keyword VALUES (3, 2347, 6);
INSERT INTO highlight_keyword VALUES (3, 2569, 6);
INSERT INTO highlight_keyword VALUES (3, 2758, 6);
INSERT INTO highlight_keyword VALUES (3, 2971, 6);
INSERT INTO highlight_keyword VALUES (3, 2991, 6);
INSERT INTO highlight_keyword VALUES (3, 3203, 6);
INSERT INTO highlight_keyword VALUES (3, 3361, 6);
INSERT INTO highlight_keyword VALUES (3, 3450, 6);
INSERT INTO highlight_keyword VALUES (3, 3670, 6);
INSERT INTO highlight_keyword VALUES (3, 4285, 6);
INSERT INTO highlight_keyword VALUES (3, 2127, 7);
INSERT INTO highlight_keyword VALUES (3, 5220, 7);
INSERT INTO highlight_keyword VALUES (3, 977, 17);
INSERT INTO highlight_keyword VALUES (3, 2695, 11);
INSERT INTO highlight_keyword VALUES (3, 6813, 11);
INSERT INTO highlight_keyword VALUES (3, 1091, 10);
INSERT INTO highlight_keyword VALUES (3, 3899, 7);
INSERT INTO highlight_keyword VALUES (3, 3960, 7);
INSERT INTO highlight_keyword VALUES (3, 4872, 7);
INSERT INTO highlight_keyword VALUES (3, 5297, 7);
INSERT INTO highlight_keyword VALUES (3, 4693, 13);
INSERT INTO highlight_keyword VALUES (4, 85, 9);
INSERT INTO highlight_keyword VALUES (4, 98, 9);
INSERT INTO highlight_keyword VALUES (4, 194, 6);
INSERT INTO highlight_keyword VALUES (5, 79, 9);
INSERT INTO highlight_keyword VALUES (5, 92, 9);
INSERT INTO highlight_keyword VALUES (5, 188, 6);
INSERT INTO highlight_keyword VALUES (6, 1101, 5);
INSERT INTO highlight_keyword VALUES (6, 123, 2);
INSERT INTO highlight_keyword VALUES (6, 250, 2);
INSERT INTO highlight_keyword VALUES (6, 261, 2);
INSERT INTO highlight_keyword VALUES (6, 279, 2);
INSERT INTO highlight_keyword VALUES (6, 343, 9);
INSERT INTO highlight_keyword VALUES (6, 353, 3);
INSERT INTO highlight_keyword VALUES (6, 1300, 2);
INSERT INTO highlight_keyword VALUES (6, 1968, 2);
INSERT INTO highlight_keyword VALUES (6, 4647, 2);
INSERT INTO highlight_keyword VALUES (6, 4682, 3);
INSERT INTO highlight_keyword VALUES (6, 4780, 2);
INSERT INTO highlight_keyword VALUES (6, 4815, 3);
INSERT INTO highlight_keyword VALUES (6, 4913, 2);
INSERT INTO highlight_keyword VALUES (6, 4948, 3);
INSERT INTO highlight_keyword VALUES (6, 5046, 2);
INSERT INTO highlight_keyword VALUES (6, 5081, 3);
INSERT INTO highlight_keyword VALUES (6, 5322, 2);
INSERT INTO highlight_keyword VALUES (6, 5627, 2);
INSERT INTO highlight_keyword VALUES (6, 6890, 2);
INSERT INTO highlight_keyword VALUES (6, 6944, 2);
INSERT INTO highlight_keyword VALUES (6, 7087, 2);
INSERT INTO highlight_keyword VALUES (6, 7133, 2);
INSERT INTO highlight_keyword VALUES (6, 7179, 2);
INSERT INTO highlight_keyword VALUES (6, 7225, 2);
INSERT INTO highlight_keyword VALUES (6, 7335, 2);
INSERT INTO highlight_keyword VALUES (6, 7338, 2);
INSERT INTO highlight_keyword VALUES (6, 7384, 2);
INSERT INTO highlight_keyword VALUES (6, 7459, 2);
INSERT INTO highlight_keyword VALUES (6, 7520, 2);
INSERT INTO highlight_keyword VALUES (6, 7523, 2);
INSERT INTO highlight_keyword VALUES (6, 7609, 2);
INSERT INTO highlight_keyword VALUES (6, 7670, 2);
INSERT INTO highlight_keyword VALUES (6, 7673, 2);
INSERT INTO highlight_keyword VALUES (6, 7759, 2);
INSERT INTO highlight_keyword VALUES (6, 7820, 2);
INSERT INTO highlight_keyword VALUES (6, 7823, 2);
INSERT INTO highlight_keyword VALUES (6, 7909, 2);
INSERT INTO highlight_keyword VALUES (6, 7970, 2);
INSERT INTO highlight_keyword VALUES (6, 7973, 2);
INSERT INTO highlight_keyword VALUES (6, 8026, 2);
INSERT INTO highlight_keyword VALUES (6, 8091, 2);
INSERT INTO highlight_keyword VALUES (6, 8141, 2);
INSERT INTO highlight_keyword VALUES (6, 8179, 2);
INSERT INTO highlight_keyword VALUES (6, 8229, 2);
INSERT INTO highlight_keyword VALUES (6, 8324, 2);
INSERT INTO highlight_keyword VALUES (6, 8374, 2);
INSERT INTO highlight_keyword VALUES (6, 8412, 2);
INSERT INTO highlight_keyword VALUES (6, 8462, 2);
INSERT INTO highlight_keyword VALUES (6, 8557, 2);
INSERT INTO highlight_keyword VALUES (6, 8607, 2);
INSERT INTO highlight_keyword VALUES (6, 8645, 2);
INSERT INTO highlight_keyword VALUES (6, 8695, 2);
INSERT INTO highlight_keyword VALUES (6, 8790, 2);
INSERT INTO highlight_keyword VALUES (6, 8840, 2);
INSERT INTO highlight_keyword VALUES (6, 8878, 2);
INSERT INTO highlight_keyword VALUES (6, 8928, 2);
INSERT INTO highlight_keyword VALUES (6, 9060, 2);
INSERT INTO highlight_keyword VALUES (6, 9112, 2);
INSERT INTO highlight_keyword VALUES (6, 9152, 2);
INSERT INTO highlight_keyword VALUES (6, 9204, 2);
INSERT INTO highlight_keyword VALUES (6, 9256, 2);
INSERT INTO highlight_keyword VALUES (6, 9308, 2);
INSERT INTO highlight_keyword VALUES (6, 9348, 2);
INSERT INTO highlight_keyword VALUES (6, 9400, 2);
INSERT INTO highlight_keyword VALUES (6, 9452, 2);
INSERT INTO highlight_keyword VALUES (6, 9504, 2);
INSERT INTO highlight_keyword VALUES (6, 9545, 2);
INSERT INTO highlight_keyword VALUES (6, 9598, 2);
INSERT INTO highlight_keyword VALUES (6, 9651, 2);
INSERT INTO highlight_keyword VALUES (6, 9704, 2);
INSERT INTO highlight_keyword VALUES (6, 9745, 2);
INSERT INTO highlight_keyword VALUES (6, 9798, 2);
INSERT INTO highlight_keyword VALUES (6, 9924, 2);
INSERT INTO highlight_keyword VALUES (6, 9977, 2);
INSERT INTO highlight_keyword VALUES (6, 10018, 2);
INSERT INTO highlight_keyword VALUES (6, 10071, 2);
INSERT INTO highlight_keyword VALUES (6, 10124, 2);
INSERT INTO highlight_keyword VALUES (6, 10177, 2);
INSERT INTO highlight_keyword VALUES (6, 10218, 2);
INSERT INTO highlight_keyword VALUES (6, 10271, 2);
INSERT INTO highlight_keyword VALUES (6, 10324, 2);
INSERT INTO highlight_keyword VALUES (6, 10377, 2);
INSERT INTO highlight_keyword VALUES (6, 10418, 2);
INSERT INTO highlight_keyword VALUES (6, 10471, 2);
INSERT INTO highlight_keyword VALUES (6, 10524, 2);
INSERT INTO highlight_keyword VALUES (6, 10577, 2);
INSERT INTO highlight_keyword VALUES (6, 10618, 2);
INSERT INTO highlight_keyword VALUES (6, 10671, 2);
INSERT INTO highlight_keyword VALUES (6, 10798, 2);
INSERT INTO highlight_keyword VALUES (6, 10851, 2);
INSERT INTO highlight_keyword VALUES (6, 10892, 2);
INSERT INTO highlight_keyword VALUES (6, 10945, 2);
INSERT INTO highlight_keyword VALUES (6, 10998, 2);
INSERT INTO highlight_keyword VALUES (6, 11051, 2);
INSERT INTO highlight_keyword VALUES (6, 11092, 2);
INSERT INTO highlight_keyword VALUES (6, 11145, 2);
INSERT INTO highlight_keyword VALUES (6, 11198, 2);
INSERT INTO highlight_keyword VALUES (6, 11251, 2);
INSERT INTO highlight_keyword VALUES (6, 11292, 2);
INSERT INTO highlight_keyword VALUES (6, 11345, 2);
INSERT INTO highlight_keyword VALUES (6, 11398, 2);
INSERT INTO highlight_keyword VALUES (6, 11451, 2);
INSERT INTO highlight_keyword VALUES (6, 11492, 2);
INSERT INTO highlight_keyword VALUES (6, 11545, 2);
INSERT INTO highlight_keyword VALUES (6, 11672, 2);
INSERT INTO highlight_keyword VALUES (6, 11725, 2);
INSERT INTO highlight_keyword VALUES (6, 11766, 2);
INSERT INTO highlight_keyword VALUES (6, 11819, 2);
INSERT INTO highlight_keyword VALUES (6, 11872, 2);
INSERT INTO highlight_keyword VALUES (6, 11925, 2);
INSERT INTO highlight_keyword VALUES (6, 11966, 2);
INSERT INTO highlight_keyword VALUES (6, 12019, 2);
INSERT INTO highlight_keyword VALUES (6, 12072, 2);
INSERT INTO highlight_keyword VALUES (6, 12125, 2);
INSERT INTO highlight_keyword VALUES (6, 12166, 2);
INSERT INTO highlight_keyword VALUES (6, 12219, 2);
INSERT INTO highlight_keyword VALUES (6, 12272, 2);
INSERT INTO highlight_keyword VALUES (6, 12325, 2);
INSERT INTO highlight_keyword VALUES (6, 12366, 2);
INSERT INTO highlight_keyword VALUES (6, 12419, 2);
INSERT INTO highlight_keyword VALUES (6, 12515, 2);
INSERT INTO highlight_keyword VALUES (6, 704, 6);
INSERT INTO highlight_keyword VALUES (6, 474, 5);
INSERT INTO highlight_keyword VALUES (6, 680, 5);
INSERT INTO highlight_keyword VALUES (6, 433, 6);
INSERT INTO highlight_keyword VALUES (6, 664, 6);
INSERT INTO highlight_keyword VALUES (6, 1623, 6);
INSERT INTO highlight_keyword VALUES (6, 1653, 6);
INSERT INTO highlight_keyword VALUES (6, 3019, 7);
INSERT INTO highlight_keyword VALUES (6, 1138, 7);

INSERT INTO job VALUES (1, '2015-05-04 11:43:14.909163+02', 0, NULL, 'ReportTestfiles.tar', 1, NULL, 2, 2);


INSERT INTO jobqueue VALUES (1, 1, 'ununpack', '1', '2015-05-04 11:43:14.952183+02', '2015-05-04 11:43:16.129263+02', 'Completed', 1, NULL, 10, NULL, NULL, NULL, NULL);
INSERT INTO jobqueue VALUES (2, 1, 'adj2nest', '1', '2015-05-04 11:43:16.154304+02', '2015-05-04 11:43:16.167857+02', 'Completed', 1, NULL, 10, NULL, NULL, NULL, NULL);
INSERT INTO jobqueue VALUES (3, 1, 'copyright', '1', '2015-05-04 11:43:17.209319+02', '2015-05-04 11:43:17.317435+02', 'Completed', 1, NULL, 5, NULL, NULL, NULL, NULL);
INSERT INTO jobqueue VALUES (4, 1, 'monk', '1', '2015-05-04 11:43:17.19792+02', '2015-05-04 11:43:17.864866+02', 'Completed', 1, NULL, 5, NULL, NULL, NULL, NULL);
INSERT INTO jobqueue VALUES (5, 1, 'nomos', '1', '2015-05-04 11:43:17.205995+02', '2015-05-04 11:43:18.096835+02', 'Completed', 1, NULL, 5, NULL, NULL, NULL, NULL);
INSERT INTO jobqueue VALUES (6, 1, 'decider', '1', '2015-05-04 11:43:18.233253+02', '2015-05-04 11:43:19.307912+02', 'Completed', 1, NULL, 2, NULL, NULL, NULL, '-r1');
INSERT INTO jobqueue VALUES (7, 1, 'report', '1', '2015-05-04 11:53:18.233253+02', NULL, '', 0, NULL, 2, NULL, NULL, NULL, NULL);


INSERT INTO jobdepends VALUES (2, 1);
INSERT INTO jobdepends VALUES (3, 2);
INSERT INTO jobdepends VALUES (4, 2);
INSERT INTO jobdepends VALUES (5, 2);
INSERT INTO jobdepends VALUES (6, 2);
INSERT INTO jobdepends VALUES (6, 5);
INSERT INTO jobdepends VALUES (6, 4);
INSERT INTO jobdepends VALUES (6, 2);


INSERT INTO license_file VALUES (1, NULL, 8, NULL, '2015-05-04 11:43:17.700032+02', 6, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (2, NULL, 8, NULL, '2015-05-04 11:43:17.703617+02', 4, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (3, 485, 1, NULL, '2015-05-04 11:43:17.711948+02', 2, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (4, 485, 8, 100, '2015-05-04 11:43:17.816367+02', 2, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (5, NULL, 8, NULL, '2015-05-04 11:43:17.835671+02', 5, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (6, 272, 8, 98, '2015-05-04 11:43:17.850531+02', 3, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (7, 272, 1, NULL, '2015-05-04 11:43:17.957299+02', 3, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (8, 199, 1, NULL, '2015-05-04 11:43:17.997061+02', 4, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (9, 199, 1, NULL, '2015-05-04 11:43:18.014822+02', 5, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (10, 560, 1, NULL, '2015-05-04 11:43:18.064022+02', 6, 1, NULL, NULL, NULL, NULL);
INSERT INTO license_file VALUES (11, 561, 1, NULL, '2015-05-04 11:43:18.066447+02', 6, 1, NULL, NULL, NULL, NULL);


INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source) VALUES (485, 'lic A', 'LicenseRef-fossology-lic-A', 'Text of license A.', 'http://www.opensource.org', NULL, NULL, NULL, 'License A', NULL, NULL, NULL, '', NULL, false, true, false, 'eb59014579b4dda6991d5e9838506749', 1, NULL);
INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source) VALUES (272, 'lic B', 'LicenseRef-fossology-lic-B', 'License by Nomos.', NULL, NULL, NULL, NULL, 'License B', NULL, NULL, NULL, NULL, NULL, false, true, false, NULL, 2, NULL);
INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source) VALUES (199, 'lic C', 'LicenseRef-fossology-lic-C', 'License by Nomos
and new line.', NULL, NULL, NULL, NULL, 'License C', NULL, NULL, NULL, NULL, NULL, false, true, false, NULL, 2, NULL);
INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source) VALUES (560, 'lic Cpp', 'LicenseRef-fossology-lic-Cpp', 'License by Nomos plus plus.', NULL, NULL, NULL, NULL, 'License Cpp', NULL, NULL, NULL, NULL, NULL, false, true, false, NULL, 2, NULL);
INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source) VALUES (561, 'lic D', 'LicenseRef-fossology-lic-D', 'License by Nomos.', NULL, NULL, NULL, NULL, 'License D', NULL, NULL, NULL, NULL, NULL, false, true, false, NULL, 2, NULL);
INSERT INTO license_ref (rf_pk, rf_shortname, rf_spdx_id, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved",
                         rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora",
                         marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source)
VALUES (561, 'CC0-1.0', 'CC0-1.0', 'CC0-1.0', NULL, NULL, NULL, NULL, 'CC0 1.0', NULL, NULL, NULL, NULL, NULL, false,
        true, false, '407014723b7e45b6ee534a20cd7542a2', 2, NULL);


INSERT INTO monk_ars VALUES (4, 8, 1, true, NULL, '2015-05-04 11:43:17.619778+02', '2015-05-04 11:43:17.863761+02');


INSERT INTO nomos_ars VALUES (3, 1, 1, true, NULL, '2015-05-04 11:43:17.251076+02', '2015-05-04 11:43:18.095804+02');


INSERT INTO perm_upload VALUES (1, 10, 1, 2);


INSERT INTO sysconfig VALUES (1, 'SupportEmailLabel', 'Support', 'Support Email Label', 2, 'Support', 1, 'e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.', '');
INSERT INTO sysconfig VALUES (2, 'SupportEmailAddr', NULL, 'Support Email Address', 2, 'Support', 2, 'e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.', 'check_email_address');
INSERT INTO sysconfig VALUES (3, 'SupportEmailSubject', 'FOSSology Support', 'Support Email Subject line', 2, 'Support', 3, 'e.g. "fossology support"<br>Subject line to use on support email.', '');
INSERT INTO sysconfig VALUES (4, 'BannerMsg', NULL, 'Banner message', 3, 'Banner', 1, 'This is message will be displayed on every page with a banner.  HTML is ok.', '');
INSERT INTO sysconfig VALUES (5, 'LogoImage', NULL, 'Logo Image URL', 2, 'Logo', 1, 'e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.  If you change this URL, you MUST also enter a logo URL.', 'check_logo_image_url');
INSERT INTO sysconfig VALUES (6, 'LogoLink', NULL, 'Logo URL', 2, 'Logo', 2, 'e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo.  If you change the Logo URL, you MUST also enter a Logo Image.', 'check_logo_url');
INSERT INTO sysconfig VALUES (7, 'FOSSologyURL', 'vagrant-VirtualBox/repo/', 'FOSSology URL', 2, 'URL', 1, 'URL of this FOSSology server, e.g. vagrant-VirtualBox/repo/', 'check_fossology_url');
INSERT INTO sysconfig VALUES (8, 'NomostListNum', '2200', 'Number of Nomost List', 2, 'Number', 4, 'For the Nomos List/Nomost List Download, you can set the number of lines to list/download. Default 2200.', NULL);
INSERT INTO sysconfig VALUES (9, 'ShowJobsAutoRefresh', '10', 'ShowJobs Auto Refresh Time', 2, 'Number', NULL, 'No of seconds to refresh ShowJobs', NULL);
INSERT INTO sysconfig VALUES (10, 'Release', '2.6.3.1', 'Release', 2, 'Release', NULL, '', NULL);


INSERT INTO ununpack_ars VALUES (1, 14, 1, true, NULL, '2015-05-04 11:43:15.048687+02', '2015-05-04 11:43:15.1256+02');


INSERT INTO upload_clearing VALUES (1, 2, 1, 2, 1, NULL);


INSERT INTO upload_clearing_license VALUES (1, 2, 199);


INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (7, 6, 6, 1, 4, 33188, 4, 5, 'test1.dtd');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (8, 6, 6, 1, 5, 33188, 6, 7, 'test2.dtd');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (6, 1, 2, 1, 0, 536888320, 3, 8, 'Glide');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (10, 9, 9, 1, 6, 33188, 10, 11, 'hash_md5prime.c');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (9, 1, 2, 1, 0, 536888320, 9, 12, 'Beerware');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (4, 3, 3, 1, 2, 33188, 14, 15, 'Condor-1.0');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (5, 3, 3, 1, 3, 33188, 16, 17, 'condor-1.1');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (3, 1, 2, 1, 0, 536888320, 13, 18, 'Condor');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (2, 1, 1, 1, 0, 805323776, 2, 19, 'artifact.dir');
INSERT INTO uploadtree_a (uploadtree_pk,realparent,parent,upload_fk,pfile_fk,ufile_mode,lft,rgt,ufile_name) VALUES (1, NULL, NULL, 1, 1, 536904704, 1, 20, 'ReportTestfiles.tar');

