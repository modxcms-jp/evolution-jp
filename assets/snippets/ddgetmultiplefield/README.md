# ddGetMultipleField

`ddGetMultipleField` renders values saved by the `mm_ddMultipleFields` manager widget while keeping the same parameter layout as the original DivanDesign snippet. Existing snippets calls can be copied as-is, including legacy aliases such as `tpl`.

## Installation

1. Copy `assets/snippets/ddgetmultiplefield/snippet.ddgetmultiplefield.php` into your Evolution CMS installation.
2. In the manager, create a snippet named **ddGetMultipleField** and paste the file contents.
3. (Optional) Add the included documentation chunk as a reference for editors.

## Behavior overview

- Reads data from a ddMultipleFields TV (`tv`) or from a raw string (`inputString`).
- Splits rows using `rowDelimiter` (default `||`) and columns using `colSeparator` (default `::`).
- Generates placeholder names from `columns` or automatically (`col1`, `col2`, ...), and always exposes `[+idx+]` (configurable via `idxPlaceholder`).
- Supports filtering through `where` (JSON key/value pairs), sorting by a column (`sortBy`/`sortDir`), offsetting, and limiting via `display` (case-insensitive `all` renders every row).
- Renders each row with `rowTpl`/`tpl`, joins with `rowSeparator`, optionally wraps with `outerTpl`, and can output to a placeholder (`toPlaceholder`).
- Legacy placeholders `owner`, `ownerId`, and `api` are preserved for compatibility; when `owner` is present they are passed into the final template.

## Parameters

| Parameter | Description | Default |
| --- | --- | --- |
| `docId` | Document id to read the TV from. | Current document id |
| `tv` | TV name or id that stores the multiple field value. Ignored when `inputString` is provided. | — |
| `inputString` | Raw multiple field string (rows separated by `rowDelimiter`, columns by `colSeparator`). | — |
| `columns` | Comma-separated list of column keys used as placeholders inside templates. When omitted, keys are generated as `col1`, `col2`, etc. | — |
| `rowDelimiter` | Delimiter between rows. | `||` |
| `colSeparator` | Delimiter between columns in a row. | `::` |
| `rowTpl` | Chunk name or `@CODE:` snippet for rendering each row. Falls back to `tpl` when empty. | — |
| `tpl` | Legacy alias for `rowTpl`. Useful for existing calls that referenced the upstream snippet. | — |
| `outerTpl` | Chunk name or `@CODE:` snippet wrapping the joined rows. Receives `[+result+]` placeholder. | — |
| `rowSeparator` | Separator string inserted between rendered rows. | — |
| `offset` | Number of rows to skip from the start. | `0` |
| `display` | Maximum number of rows to render. Use `all` or empty (case-insensitive) to render everything. | `all` |
| `sortBy` | Column key used for sorting. | — |
| `sortDir` | Sort direction (`asc` or `desc`). | `asc` |
| `where` | JSON object with equality filters, e.g. `{"status":"published"}`. | — |
| `idxPlaceholder` | Placeholder name for the 1-based row index. | `idx` |
| `toPlaceholder` | When set, the rendered output is stored in the given placeholder instead of being printed. | — |
| `owner`, `ownerId`, `api` | Compatibility placeholders preserved for legacy calls; when `owner` is set, they are passed to the wrapping template. | — |

## Example

```html
[[ddGetMultipleField?
    &tv=`gallery`
    &columns=`title,description,url`
    &rowTpl=`@CODE:<li><a href="[+url+]">[+title+]</a><br />[+description+]</li>`
    &outerTpl=`@CODE:<ul>[+result+]</ul>`
    &sortBy=`title`
    &sortDir=`asc`
]]
```
