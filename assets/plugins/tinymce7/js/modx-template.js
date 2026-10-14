/*
 * MODX のチャンク・リソースを「ひな形」として本文へ挿入する TinyMCE 7 プラグイン。
 * TinyMCE 7 で廃止されたオープンソース版 template プラグインの代わり。
 * ひな形の一覧は PHP 側（TemplateRepository）が modx_templates オプションとして渡す。
 */
(function (global) {
  'use strict';

  const tinymce = global.tinymce;
  if (!tinymce || !tinymce.PluginManager) {
    return;
  }

  const DEFAULT_LABELS = Object.freeze({
    button: 'Insert template',
    title: 'Insert template',
    select: 'Template',
    preview: 'Preview',
    insert: 'Insert',
    cancel: 'Cancel'
  });

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // プレビューは管理画面の DOM に直接入れるため、エディターのスキーマで無害化した HTML を使う。
  // 挿入時もエディターが同じ処理をするので、実際に入る内容に近い表示になる。
  function sanitize(editor, html) {
    const fragment = editor.parser.parse(html, { insert: true });

    return tinymce.html.Serializer({}, editor.schema).serialize(fragment);
  }

  function previewHtml(editor, labels, template) {
    if (!template) {
      return '';
    }

    const description = template.description
      ? '<p class="modx-template__description" style="margin:8px 0 0;">' + escapeHtml(template.description) + '</p>'
      : '';

    return description
      + '<div class="modx-template__label" style="margin:12px 0 4px;">' + escapeHtml(labels.preview) + '</div>'
      + '<div class="modx-template__preview" style="max-height:320px;overflow:auto;border:1px solid #ccc;padding:8px;">'
      + sanitize(editor, template.content)
      + '</div>';
  }

  function openDialog(editor) {
    const templates = editor.options.get('modx_templates');
    const labels = Object.assign({}, DEFAULT_LABELS, editor.options.get('modx_template_labels'));

    if (!templates.length) {
      return;
    }

    const items = templates.map(function (template, index) {
      return { text: String(template.title), value: String(index) };
    });

    const bodyFor = function (index) {
      return {
        type: 'panel',
        items: [
          { type: 'listbox', name: 'template', label: labels.select, items: items },
          { type: 'htmlpanel', html: previewHtml(editor, labels, templates[Number(index)]) }
        ]
      };
    };

    const spec = function (index) {
      return {
        title: labels.title,
        size: 'large',
        body: bodyFor(index),
        initialData: { template: index },
        buttons: [
          { type: 'cancel', text: labels.cancel },
          { type: 'submit', text: labels.insert, primary: true }
        ],
        // htmlpanel は後から内容を差し替えられないので、選択が変わったらダイアログを組み直す
        onChange: function (api, details) {
          if (details.name === 'template') {
            api.redial(spec(api.getData().template));
          }
        },
        onSubmit: function (api) {
          const template = templates[Number(api.getData().template)];
          api.close();
          if (template) {
            editor.insertContent(String(template.content));
          }
        }
      };
    };

    editor.windowManager.open(spec('0'));
  }

  tinymce.PluginManager.add('modxtemplate', function (editor) {
    editor.options.register('modx_templates', { processor: 'object[]', default: [] });
    editor.options.register('modx_template_labels', { processor: 'object', default: {} });

    const labels = Object.assign({}, DEFAULT_LABELS, editor.options.get('modx_template_labels'));
    const action = function () {
      openDialog(editor);
    };

    // ツールバーは旧 template プラグインと同じ名前、メニューは TinyMCE 7 の Insert メニュー既定項目の名前で登録する
    editor.ui.registry.addButton('template', {
      icon: 'template',
      tooltip: labels.button,
      onAction: action
    });
    editor.ui.registry.addMenuItem('inserttemplate', {
      icon: 'template',
      text: labels.button + '...',
      onAction: action
    });

    return {
      getMetadata: function () {
        return { name: 'MODX templates' };
      }
    };
  });
})(window);
