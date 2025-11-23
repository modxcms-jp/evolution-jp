# CKEditor5 Classic Plugin for MODX Evolution

This plugin integrates CKEditor5 (Classic editor) as a rich text editor for MODX Evolution.

## Features

- **Modern Editor**: Uses CKEditor5 Classic editor loaded from CDN
- **MCPUK Integration**: Integrates with MODX's MCPUK file browser for image and media management
- **Multiple Toolbar Themes**: Simple, Default, and Full toolbar configurations
- **Lightweight**: CDN-based loading keeps the plugin compact
- **Multilingual**: Supports English and Japanese

## Installation

1. The plugin is already included in the MODX Evolution distribution
2. Go to **Elements > Manage Elements > Plugins**
3. Find "CKEditor5" and enable it
4. Configure the plugin settings as needed

## Configuration

### System Events

The plugin responds to these events:
- `OnRichTextEditorRegister` - Registers CKEditor5 as an available editor
- `OnRichTextEditorInit` - Initializes the editor
- `OnInterfaceSettingsRender` - Renders configuration interface

### Toolbar Themes

#### Simple
Basic formatting options: undo/redo, bold, strikethrough, alignment, links, images, horizontal line

#### Default (Recommended)
Comprehensive editing tools including:
- Text formatting (bold, italic, strikethrough)
- Headings and font colors
- Links, images, tables, media embed
- Lists and indentation
- Block quotes and horizontal rules
- Source editing

#### Full
All available features including:
- Font size and family selection
- Subscript/superscript
- Code blocks
- Todo lists
- Find and replace
- Page breaks

### MCPUK File Browser

The plugin integrates with MODX's MCPUK file browser:
- **Images**: Browse and insert images
- **Media**: Browse and insert media files
- **Files**: Browse and insert file links

## File Structure

```
assets/plugins/ckeditor5-classic/
├── plugin.ckeditor5.php          # Main plugin file
├── functions.php                  # Core functionality
├── README.md                      # This file
├── js/
│   ├── ckeditor_init.inc.js      # Editor initialization
│   └── modx_fb.js.inc            # MCPUK browser integration
├── lang/
│   ├── english.inc.php           # English translations
│   └── japanese-utf8.inc.php     # Japanese translations
├── settings/
│   └── toolbar.settings.inc.php  # Toolbar configurations
├── inc/
│   └── gsettings.inc.html        # Settings interface
└── style/
    └── content.css               # Editor content styles
```

## Usage

### Selecting CKEditor5

1. Go to **Tools > Configuration**
2. Under "Interface & Features" tab
3. Find "Which editor to use" option
4. Select "CKEditor5"
5. Save configuration

### User-Level Settings

Individual users can override the global editor setting:
1. Click on user name in top right
2. Select "User Settings"
3. Choose preferred editor

## Technical Details

### CDN Version

This plugin uses CKEditor5 version 43.3.1 from the official CDN:
```
https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js
```

### Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11 is not supported (CKEditor5 limitation)

### Customization

To customize the toolbar, you can:
1. Use the "Custom" theme in settings
2. Provide your own JSON configuration
3. Modify `settings/toolbar.settings.inc.php`

## Comparison with TinyMCE

| Feature | CKEditor5 | TinyMCE3 |
|---------|-----------|----------|
| Version | Latest (43.3.1) | Legacy (3.x) |
| Loading | CDN | Local files |
| File Size | ~600KB (CDN) | ~2MB (local) |
| Modern UI | ✓ | Limited |
| Active Development | ✓ | Discontinued |

## Troubleshooting

### Editor not appearing
- Check if JavaScript is enabled
- Verify CDN is accessible
- Check browser console for errors

### File browser not working
- Verify MCPUK is installed
- Check file permissions on media directory

### Styles not applying
- Clear browser cache
- Check content.css path

## License

CKEditor5 is licensed under GPL 2 or later.
This plugin follows MODX Evolution's licensing.

## Credits

- CKEditor5: https://ckeditor.com/
- MODX Evolution: https://evo.modx.com/
