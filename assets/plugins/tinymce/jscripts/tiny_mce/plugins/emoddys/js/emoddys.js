tinyMCEPopup.requireLangPack();

var EmoddysDialog = {
	init : function(ed) {
	},

	insert : function(file, title) {
		var ed = tinyMCEPopup.editor, dom = ed.dom;

		tinyMCEPopup.execCommand('mceInsertContent', false, dom.createHTML('img', {
			src : tinyMCEPopup.getWindowArg('plugin_url') + '/img/' + file,
			alt : ed.getLang(title),
			title : ed.getLang(title),
			border : 0
		}));
	}
};

tinyMCEPopup.onInit.add(EmoddysDialog.init, EmoddysDialog);
