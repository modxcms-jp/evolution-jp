-- Insert system records
-- Default Site Settings

INSERT IGNORE INTO `{PREFIX}system_settings` 
(setting_name, setting_value) VALUES 
('settings_version','0'),
('server_offset_time','0'),
('manager_language','{MANAGERLANGUAGE}'),
('modx_charset','UTF-8'),
('site_name','My MODX Site'),
('site_start','1'),
('error_page','1'),
('unauthorized_page','1'),
('site_status','1'),
('old_template',''),
('cache_type','1'),
('use_udperms','0'),
('udperms_allowroot','0'),
('failed_login_attempts','5'),
('blocked_minutes','10'),
('use_captcha','0'),
('emailsender','{ADMINEMAIL}'),
('use_editor','1'),
('use_browser','1'),
('fe_editor_lang','{MANAGERLANGUAGE}'),
('session.cookie.lifetime','604800'),
('manager_theme','RevoClassic'),
('theme_refresher','');

-- Default Site Template

INSERT INTO `{PREFIX}site_templates` 
(id, templatename, description, editor_type, category, icon, template_type, content, locked) VALUES ('1','Minimal Template','Default minimal empty template (content returned only)','0','0','','0','[*content*]','0');


-- Default Site Documents

INSERT INTO `{PREFIX}site_content` VALUES (1,'document','text/html','MODX CMS Install Success','Welcome to the MODX Content Management System','','minimal-base','',1,0,0,0,0,'','<h3>Install Successful!</h3>\r\n<p>You have successfully installed MODX.</p>\r\n\r\n<h3>Getting Help</h3>\r\n<p>The <a href=\"http://modxcms.com/forums/\" target=\"_blank\">MODX Community</a> provides a great starting point to learn all things MODX, or you can also <a href=\"http://modxcms.com/learn/it.html\">see some great learning resources</a> (books, tutorials, blogs and screencasts).</p>\r\n<p>Welcome to MODX!</p>\r\n',1,3,0,1,1,1,{DATE_NOW},1,{DATE_NOW},0,0,0,{DATE_NOW},1,'Base Install',0,0,0,0,0,0,0,1);

INSERT INTO `{PREFIX}manager_users` (id, username, password)
VALUES (1, '{ADMINNAME}', '{ADMINPASS}');

INSERT INTO `{PREFIX}user_attributes`
(id, internalKey, fullname, role, email, phone, mobilephone, blocked, blockeduntil, blockedafter, logincount, lastlogin,
 thislogin, failedlogincount, sessionid, dob, gender, country, state, zip, fax, photo, comment)
VALUES (1, 1, '{ADMINFULLNAME}', 2, '{ADMINEMAIL}', '', '', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, '', '', '', '', '', '');

INSERT INTO `{PREFIX}user_roles`
(id, name, description, frames, home, view_document, new_document, save_document, move_document, publish_document,
 delete_document, empty_trash, action_ok, logout, help, messages, new_user, edit_user, logs, edit_parser, save_parser,
 edit_template, settings, credits, new_template, save_template, delete_template, edit_snippet, new_snippet,
 save_snippet, delete_snippet, edit_chunk, new_chunk, save_chunk, delete_chunk, empty_cache, edit_document,
 change_password, error_dialog, about, file_manager, save_user, delete_user, save_password, edit_role, save_role,
 delete_role, new_role, access_permissions, bk_manager, new_plugin, edit_plugin, save_plugin, delete_plugin, new_module,
 edit_module, save_module, exec_module, delete_module, view_eventlog, delete_eventlog, manage_metatags,
 edit_doc_metatags, new_web_user, edit_web_user, save_web_user, delete_web_user, web_access_permissions,
 view_unpublished, import_static, export_static, remove_locks, view_schedule)
VALUES (2, 'Editor', 'Limited to managing content', 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0,
        0, 0, 0, 0, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 1, 0, 0, 1, 1),
       (3, 'Publisher', 'Editor with expanded permissions including manage users\, update Elements and site settings',
        1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1,
        1, 1, 1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 1, 0, 0, 1, 1);

