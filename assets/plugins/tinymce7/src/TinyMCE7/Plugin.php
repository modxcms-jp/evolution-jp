<?php
declare(strict_types=1);

namespace TinyMCE7;

use TinyMCE7\Editor\EditorInitializer;
use TinyMCE7\Settings\SystemSettingsRenderer;

final class Plugin
{
    public static function handle(): void
    {
        $event = evo()->event;
        $eventName = (string)($event->name ?? '');

        switch ($eventName) {
            case 'OnRichTextEditorRegister':
                $event->output('TinyMCE7');
                return;

            case 'OnRichTextEditorInit':
                (new EditorInitializer())->handle($event);
                return;

            case 'OnInterfaceSettingsRender':
                $event->output((new SystemSettingsRenderer())->render());
                return;
        }
    }
}
