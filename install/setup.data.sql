# MODx Database Script for New/Upgrade Installations
#
# Each sql command is separated by double lines


#
# Dumping data for table `documentgroup_names`
#


REPLACE INTO `{PREFIX}document_groups` VALUES ('1','1','3');


REPLACE INTO `{PREFIX}documentgroup_names` VALUES ('1','Site Admin Pages','0','0');


#
# Dumping data for table `site_content`
#


REPLACE INTO `{PREFIX}site_content` VALUES (1, 'document', 'text/html', 'Home', 'Welcome to MODX', 'Introduction to MODX', 'index', '', 1, 0, 0, 0, 0, 'Create and do amazing things with MODX', '-', 1, 4, 1, 1, 1, 1, 1144904400, 1, 1160262629, 0, 0, 0, 0, 0, 'Home', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (2, 'document', 'text/html', 'Blog', 'My Blog', '', 'blog', '', 1, 0, 0, 0, 1, '', '-', 1, 4, 2, 0, 0, 1, 1144904400, 1, 1159818696, 0, 0, 0, 0, 0, 'Blog', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (4, 'document', 'text/html', '[*loginName*]', 'Login to Enable to Comments', '', 'login', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 11, 0, 0, 1, 1144904400, 1, 1144904400, 0, 0, 0, 0, 0, '[*loginName*]', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (5, 'document', 'text/html', 'Request an Account', '', '', 'request-an-account', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 3, 0, 0, 1, 1144904400, 1, 1158320704, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (6, 'document', 'text/html', 'Contact Us', '', '', 'contact-us', '', 1, 0, 0, 0, 0, '', '-', 0, 4, 14, 1, 0, 1, 1144904400, 1, 1159303922, 0, 0, 0, 0, 0, 'Contact us', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (7, 'document', 'text/html', '404 - Document Not Found', '', '', 'doc-not-found', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 4, 0, 1, 1, 1144904400, 1, 1159301173, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (8, 'document', 'text/html', 'Search Results', 'Your Search Results', '', 'search-results', '', 1, 0, 0, 0, 0, '', '-', 0, 4, 5, 0, 0, 1, 1144904400, 1, 1158613055, 0, 0, 0, 0, 0, '', 1, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (9, 'document', 'text/html', 'Mini-Blog HOWTO', 'How to Start Posting with MODx Mini-Blogs', '', 'article-1126081344', '', 1, 0, 0, 2, 1, '', '<p>Setting up a mini-blog is relatively simple. Here''s what you need to do to get started with making new posts:</p>\r\n<ol>\r\n    <li>Login to the <a href="[(site_url)]manager/">MODx Control Panel</a>.</li>\r\n    <li>Press the plus-sign next to the Blog(2) container resource to see the blog entries posted there.</li>\r\n    <li>To make a new Blog entry, simply right-click the Blog container document and choose the "Create Resource here" menu option. To edit an existing blog article, right click the entry and choose the "Edit Resource" menu option.</li>\r\n    <!-- splitter -->\r\n    <li>Write or edit the content and press save, making sure the document is published.</li>\r\n    <li>Everything else is automatic; you''re done!</li>\r\n</ol>\r\n{{Comments}}', 1, 4, 0, 1, 1, -1, 1144904400, 1, 1160171764, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);



REPLACE INTO `{PREFIX}site_content` VALUES (11, 'document', 'text/xml', 'RSS Feed', '[(site_name)] RSS Feed', '', 'feed.rss', '', 1, 0, 0, 0, 0, '', '-', 0, 0, 6, 0, 0, 1, 1144904400, 1, 1160062859, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (14, 'document', 'text/html', 'Content Management', 'Ways to manage content', '', 'cms', '', 1, 0, 0, 15, 0, '', '-', 0, 4, 3, 1, 1, 1, 1144904400, 1, 1158331927, 0, 0, 0, 0, 0, 'Manage Content', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (15, 'document', 'text/html', 'MODX Features', 'MODX Features', '', 'features', '', 1, 0, 0, 0, 1, '', '[!Wayfinder?startId=`[*id*]` &outerClass=`topnav`!]', 1, 4, 7, 1, 1, 1, 1144904400, 1, 1158452722, 0, 0, 0, 1144777367, 1, 'Features', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (16, 'document', 'text/html', 'Ajax', 'Ajax and Web 2.0 ready', '', 'ajax', '', 1, 1159264800, 0, 15, 0, '', '-', 1, 4, 1, 1, 1, 1, 1144904400, 1, 1159307504, 0, 0, 0, 0, 0, 'Ajax', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (18, 'document', 'text/html', 'Just a pretend, older post', 'This post should in fact be archived', '', 'article-1128398162', '', 1, 0, 0, 2, 0, '', '<p>Not so exciting, after all, eh?<br /></p>\r\n', 1, 4, 2, 1, 1, -1, 1144904400, 1, 1159306886, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (22, 'document', 'text/html', 'Menus and Lists', 'Flexible Menus and Lists', '', 'menus', '', 1, 1159178400, 0, 15, 0, '', '-', 1, 4, 2, 1, 1, 1, 1144904400, 1, 1160148522, 0, 0, 0, 0, 0, 'Menus and Lists', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (24, 'document', 'text/html', 'Extendable by design', 'Extendable by design', '', 'extendable', '', 1, 1159092732, 0, 15, 0, '', '-', 1, 4, 4, 1, 1, 2, 1144904400, 1, 1159309971, 0, 0, 0, 0, 0, 'Extendability', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (32, 'document', 'text/html', 'Design', 'Site Design', '', 'design', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 10, 1, 1, 2, 1144904400, 1, 1160112322, 0, 0, 0, 1144912754, 1, 'Design', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (33, 'document', 'text/html', 'Getting Help', 'Getting Help with MODX', '', 'geting-help', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 8, 1, 1, 2, 1144904400, 2, 1144904400, 0, 0, 0, 0, 0, 'Getting Help', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (37, 'document', 'text/html', '[*loginName*]', 'The page you''re trying to reach requires a login', '', 'blog-login', '', 1, 0, 0, 0, 0, '', '<p>In order to add a blog entry, you must be logged in as a Site Admin webuser. Also, commenting on posts requires a login. <a href="[~6~]">Contact the site owner</a> for permissions to create new post, or <a href="[~5~]">create a web user account</a> to automatically receive commenting privileges. If you already have an account, please login below.</p>\r\n\r\n[!WebLogin? &tpl=`WebLoginSideBar` &loginhomeid=`3`!]', 1, 4, 12, 0, 0, 1, 1144904400, 1, 1158599931, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (46, 'document', 'text/html', 'Thank You', '', '', 'thank-you', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 13, 1, 1, 1, 1159302141, 1, 1159302892, 0, 0, 0, 1159302182, 1, '', 0, 0, 0, 0, 0, 0, 1);


#
# Dumping data for table `system_settings`
#


REPLACE INTO `{PREFIX}system_settings` VALUES('error_page', '7');


REPLACE INTO `{PREFIX}system_settings` VALUES('unauthorized_page', '4');


#
# Dumping data for table `web_groups`
#


REPLACE INTO `{PREFIX}web_groups` VALUES ('1','1','1');


#
# Dumping data for table `web_user_attributes`
#


REPLACE INTO `{PREFIX}web_user_attributes` VALUES ('1','1','Site Admin','0','{ADMINEMAIL}','','','0','0','0','25','1129049624','1129063123','0','f426f3209310abfddf2ee00e929774b4','0','0','','','','','','');


#
# Dumping data for table `web_users`
#


REPLACE INTO `{PREFIX}web_users` VALUES ('1','siteadmin','5f4dcc3b5aa765d61d8327deb882cf99','');


#
# Dumping data for table `webgroup_access`
#


REPLACE INTO `{PREFIX}webgroup_access` VALUES ('1','1','1');


#
# Dumping data for table `webgroup_names`
#


REPLACE INTO `{PREFIX}webgroup_names` VALUES ('1','Site Admins');


#
# Table structure for table `jot_content`
#


CREATE TABLE IF NOT EXISTS `{PREFIX}jot_content` (`id` int(10) NOT NULL auto_increment, `title` varchar(255) default NULL, `tagid` varchar(50) default NULL, `published` int(1) NOT NULL default '0', `uparent` int(10) NOT NULL default '0', `parent` int(10) NOT NULL default '0', `flags` varchar(25) default NULL, `secip` varchar(32) default NULL, `sechash` varchar(32) default NULL, `content` mediumtext, `customfields` mediumtext, `mode` int(1) NOT NULL default '1', `createdby` int(10) NOT NULL default '0', `createdon` int(20) NOT NULL default '0', `editedby` int(10) NOT NULL default '0', `editedon` int(20) NOT NULL default '0', `deleted` int(1) NOT NULL default '0', `deletedon` int(20) NOT NULL default '0', `deletedby` int(10) NOT NULL default '0', `publishedon` int(20) NOT NULL default '0', `publishedby` int(10) NOT NULL default '0', PRIMARY KEY  (`id`), KEY `parent` (`parent`), KEY `secip` (`secip`), KEY `tagidx` (`tagid`), KEY `uparent` (`uparent`)) ENGINE=MyISAM {CHAR_COLLATE};


#
# Dumping data for table `jot_content`
#


REPLACE INTO `{PREFIX}jot_content` VALUES ('9','The first comment','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','This is the first comment.','<custom><name></name><email></email></custom>','0','0','1160420310','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('10','Second comment','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','This is the second comment and uses an alternate row color. I also supplied a name, but i\'m not logged in.','<custom><name>Armand</name><email></email></custom>','0','0','1160420453','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('11','No abuse','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','Notice that I can\'t abuse <b>html</b>, ,  or [+placeholder+] tags.\r\n\r\nA new line also doesn\'t come unnoticed.','<custom><name>Armand</name><email></email></custom>','0','0','1160420681','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('12','Posting when logged in','','1','9','0','','87.211.130.14','58fade927c1df50ba6131f2b0e53c120','When you are logged in your own posts have a special color so you can easily spot them from the comment view. \r\n\r\nThe form also does not display any guest fields when logged in.','<custom></custom>','0','-1','1160421310','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('13','Managers','','1','9','0','','87.211.130.14','91e230cf219e3ade10f32d6a41d0bd4d','Comments posted when only logged in as a manager user will use your manager name.\r\n\r\nModerators options are always shown when you are logged in as manager user.','<custom></custom>','0','1','1160421487','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('14','Moderation','','1','9','0','','87.211.130.14','58fade927c1df50ba6131f2b0e53c120','In this setup the Site Admins group is defined as being the moderator for this particular comment view. These users will have extra moderation options \r\n\r\nManager users, Moderators or Trusted users can post bad words like: dotNet.','<custom></custom>','0','-1','1160422081','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('15','I\'m untrusted','','0','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','Untrusted users however can NOT post bad words like: dotNet. When they do the posts will be unpublished.','<custom><name></name><email></email></custom>','0','0','1160422167','0','0','0','0','0','0','0');


#
# Table structure for table `jot_subscriptions`
#


CREATE TABLE IF NOT EXISTS `{PREFIX}jot_subscriptions` (`id` mediumint(10) NOT NULL auto_increment, `uparent` mediumint(10) NOT NULL default '0', `tagid` varchar(50) NOT NULL default '', `userid` mediumint(10) NOT NULL default '0', PRIMARY KEY  (`id`), KEY `uparent` (`uparent`), KEY `tagid` (`tagid`), KEY `userid` (`userid`)) ENGINE=MyISAM {CHAR_COLLATE};


