# Default Site Settings
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
('failed_login_attempts','3'),
('blocked_minutes','60'),
('use_captcha','0'),
('emailsender','{ADMINEMAIL}'),
('use_editor','1'),
('use_browser','1'),
('fe_editor_lang','{MANAGERLANGUAGE}'),
('session.cookie.lifetime','604800'),
('manager_theme','RevoStyle'),
('theme_refresher','');


REPLACE INTO `{PREFIX}user_roles` 
(id,name,description,frames,home,view_document,new_document,save_document,publish_document,delete_document,empty_trash,action_ok,logout,help,messages,new_user,edit_user,logs,edit_parser,save_parser,edit_template,settings,credits,new_template,save_template,delete_template,edit_snippet,new_snippet,save_snippet,delete_snippet,edit_chunk,new_chunk,save_chunk,delete_chunk,empty_cache,edit_document,change_password,error_dialog,about,file_manager,save_user,delete_user,save_password,edit_role,save_role,delete_role,new_role,access_permissions,bk_manager,new_plugin,edit_plugin,save_plugin,delete_plugin,new_module,edit_module,save_module,exec_module,delete_module,view_eventlog,delete_eventlog,manage_metatags,edit_doc_metatags,new_web_user,edit_web_user,save_web_user,delete_web_user,web_access_permissions,view_unpublished,import_static,export_static,remove_locks,view_schedule) VALUES 
(1, 'Administrator', 'Site administrators have full access to all functions',1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1);


