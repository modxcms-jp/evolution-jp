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


REPLACE INTO `{PREFIX}site_content` VALUES (6, 'document', 'text/html', 'Contact Us', '', '', 'contact-us', '', 1, 0, 0, 0, 0, '', '-', 0, 4, 14, 1, 0, 1, 1144904400, 1, 1159303922, 0, 0, 0, 0, 0, 'Contact us', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (7, 'document', 'text/html', '404 - Document Not Found', '', '', 'doc-not-found', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 4, 0, 1, 1, 1144904400, 1, 1159301173, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (9, 'document', 'text/html', 'Mini-Blog HOWTO', 'How to Start Posting with MODx Mini-Blogs', '', 'article-1126081344', '', 1, 0, 0, 2, 0, '', '<p>Setting up a mini-blog is relatively simple. Here''s what you need to do to get started with making new posts:</p>\r\n<ol>\r\n    <li>Login to the <a href="[(site_url)]manager/">MODx Control Panel</a>.</li>\r\n    <li>Press the plus-sign next to the Blog(2) container resource to see the blog entries posted there.</li>\r\n    <li>To make a new Blog entry, simply right-click the Blog container document and choose the "Create Resource here" menu option. To edit an existing blog article, right click the entry and choose the "Edit Resource" menu option.</li>\r\n    <!-- splitter -->\r\n    <li>Write or edit the content and press save, making sure the document is published.</li>\r\n    <li>Everything else is automatic; you''re done!</li>\r\n</ol>\r\n', 1, 4, 0, 1, 1, -1, 1144904400, 1, 1160171764, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);



REPLACE INTO `{PREFIX}site_content` VALUES (11, 'document', 'text/xml', 'RSS Feed', '[(site_name)] RSS Feed', '', 'feed.rss', '', 1, 0, 0, 0, 0, '', '-', 0, 0, 6, 0, 0, 1, 1144904400, 1, 1160062859, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (14, 'document', 'text/html', 'Content Management', 'Ways to manage content', '', 'cms', '', 1, 0, 0, 15, 0, '', '-', 0, 4, 3, 1, 1, 1, 1144904400, 1, 1158331927, 0, 0, 0, 0, 0, 'Manage Content', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (15, 'document', 'text/html', 'MODX Features', 'MODX Features', '', 'features', '', 1, 0, 0, 0, 1, '', '[!Wayfinder?startId=`[*id*]` &outerClass=`topnav`!]', 1, 4, 7, 1, 1, 1, 1144904400, 1, 1158452722, 0, 0, 0, 1144777367, 1, 'Features', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (16, 'document', 'text/html', 'Ajax', 'Ajax and Web 2.0 ready', '', 'ajax', '', 1, 1159264800, 0, 15, 0, '', '-', 1, 4, 1, 1, 1, 1, 1144904400, 1, 1159307504, 0, 0, 0, 0, 0, 'Ajax', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (18, 'document', 'text/html', 'Just a pretend, older post', 'This post should in fact be archived', '', 'article-1128398162', '', 1, 0, 0, 2, 0, '', '<p>Not so exciting, after all, eh?<br /></p>\r\n', 1, 4, 2, 1, 1, -1, 1144904400, 1, 1159306886, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (22, 'document', 'text/html', 'Menus and Lists', 'Flexible Menus and Lists', '', 'menus', '', 1, 1159178400, 0, 15, 0, '', '-', 1, 4, 2, 1, 1, 1, 1144904400, 1, 1160148522, 0, 0, 0, 0, 0, 'Menus and Lists', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (24, 'document', 'text/html', 'Extendable by design', 'Extendable by design', '', 'extendable', '', 1, 1159092732, 0, 15, 0, '', '-', 1, 4, 4, 1, 1, 2, 1144904400, 1, 1159309971, 0, 0, 0, 0, 0, 'Extendability', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (32, 'document', 'text/html', 'Design', 'Site Design', '', 'design', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 10, 1, 1, 2, 1144904400, 1, 1160112322, 0, 0, 0, 1144912754, 1, 'Design', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (33, 'document', 'text/html', 'Getting Help', 'Getting Help with MODX', '', 'geting-help', '', 1, 0, 0, 0, 0, '', '-', 1, 4, 8, 1, 1, 2, 1144904400, 2, 1144904400, 0, 0, 0, 0, 0, 'Getting Help', 0, 0, 0, 0, 0, 0, 0);


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


