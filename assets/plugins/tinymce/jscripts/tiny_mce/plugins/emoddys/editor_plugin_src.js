/**
 * $Id: editor_plugin_src.js 520 2008-04-05 16:30:32Z yama $
 *
 * @author kyms
 */

(function() {
	tinymce.create('tinymce.plugins.EmoddysPlugin', {
		init : function(ed, url) {
			// Register commands
			ed.addCommand('mceEmoddys', function() {
				ed.windowManager.open({
					file : url + '/emoddys.htm',
					width : 300 + parseInt(ed.getLang('emoddys.delta_width', 0)),
					height : 280 + parseInt(ed.getLang('emoddys.delta_height', 0)),
					inline : 1
				}, {
					plugin_url : url
				});
			});

			// Register buttons
			ed.addButton('emoddys', {
			title : 'emoddys.emoddys_desc',
			cmd : 'mceEmoddys',
			image : url + '/img/emoddys.gif'
			});
		},

		getInfo : function() {
			return {
				longname : 'Emoddys',
				author : 'yama',
				authorurl : 'http://kyms.ne.jp',
				infourl : 'http://kyms.ne.jp',
				version : tinymce.majorVersion + "." + tinymce.minorVersion
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('emoddys', tinymce.plugins.EmoddysPlugin);
})();