# md2html-php

A **secure**, **self-contained** PHP library that converts Markdown files (or
strings) into beautiful, responsive HTML pages — with light/dark themes,
syntax highlighting for PHP, JavaScript, SQL, Bash, Python, HTML, CSS and
JSON, customisable headers, and headless mode.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration Options](#configuration-options)
- [Usage Examples](#usage-examples)
  - [Convert a file — full HTML page](#1-convert-a-file--full-html-page)
  - [Convert a Markdown string](#2-convert-a-markdown-string)
  - [Dark theme](#3-dark-theme)
  - [Headless mode — body fragment only](#4-headless-mode--body-fragment-only)
  - [Custom page title](#5-custom-page-title)
  - [Custom &lt;head&gt; content](#6-custom-head-content)
  - [External CSS path](#7-external-css-path)
  - [Restrict file access to a directory](#8-restrict-file-access-to-a-directory)
- [Supported Markdown Features](#supported-markdown-features)
- [Syntax Highlighting](#syntax-highlighting)
- [CSS Themes](#css-themes)
- [Security](#security)
- [Running the Tests](#running-the-tests)
- [License](#license)

---

## Requirements

| Requirement | Version |
|:------------|:--------|
| PHP         | ≥ 8.1   |
| Extensions  | `tokenizer` (optional, improves PHP highlighting) |

No third-party runtime dependencies.

---

## Installation

**Via Composer (recommended)**

```bash
composer require silverday/md2html-php
```

**Manual**

Copy `src/Md2Html.php` and `assets/css/md2html.css` into your project and
`require` the class file directly.

---

## Quick Start

```php
<?php
require_once 'src/Md2Html.php';

$converter = new Md2Html();
$html = $converter->convertFile('docs/readme.md');
file_put_contents('output.html', $html);
```

Run the bundled demo:

```bash
php examples/convert.php
# or pass your own file
php examples/convert.php path/to/my-doc.md
```

---

## Configuration Options

All options are passed as an associative array to the constructor.

| Option            | Type      | Default  | Description |
|:------------------|:----------|:---------|:------------|
| `headless`        | `bool`    | `false`  | When `true`, return only the HTML body fragment (no `<!DOCTYPE>`, `<html>`, `<head>`, or `<body>` wrapper). |
| `theme`           | `string`  | `'light'`| Page colour scheme. `'light'` or `'dark'`. Auto-detection via `prefers-color-scheme` still works when using the bundled CSS. |
| `title`           | `string`  | `''`     | HTML `<title>` text. When empty, the text of the first `<h1>` is used; falls back to `'Document'`. |
| `cssPath`         | `string`  | `''`     | URL or path to an **external** CSS file. When empty, the bundled `md2html.css` is embedded inline via a `<style>` block. |
| `customHeader`    | `string`  | `''`     | Trusted raw HTML injected into `<head>` (e.g. extra `<meta>` tags, an analytics snippet). **You are responsible for sanitising this value.** |
| `allowedBasePath` | `?string` | `null`   | When set, `convertFile()` will reject any file whose real path is not inside this directory. Strongly recommended in web-facing applications. |

---

## Usage Examples

### 1. Convert a file — full HTML page

```php
$converter = new Md2Html();
echo $converter->convertFile('docs/guide.md');
```

### 2. Convert a Markdown string

```php
$converter = new Md2Html();
$html = $converter->convert('# Hello, *World*!');
echo $html;
```

### 3. Dark theme

```php
$converter = new Md2Html(['theme' => 'dark']);
$html = $converter->convertFile('docs/guide.md');
```

### 4. Headless mode — body fragment only

Headless mode returns only the converted HTML content, without any page
wrapper. Useful when embedding in an existing page or template.

```php
$converter = new Md2Html(['headless' => true]);
$fragment = $converter->convert('# Hello');
// → <h1 id="hello">Hello</h1>
```

### 5. Custom page title

```php
$converter = new Md2Html(['title' => 'My Documentation']);
$html = $converter->convertFile('docs/guide.md');
```

### 6. Custom `<head>` content

```php
$converter = new Md2Html([
    'customHeader' => '<meta name="author" content="Jane Doe">'
                    . '<link rel="canonical" href="https://example.com/guide">',
]);
$html = $converter->convertFile('docs/guide.md');
```

### 7. External CSS path

```php
// Link to the bundled stylesheet served as a static file
$converter = new Md2Html([
    'cssPath' => '/assets/md2html.css',
]);
$html = $converter->convertFile('docs/guide.md');
```

To use the **dark-only** or **light-only** theme stylesheets:

```php
$converter = new Md2Html(['cssPath' => '/assets/css/dark.css']);
```

### 8. Restrict file access to a directory

Always set `allowedBasePath` in web contexts to prevent path-traversal attacks.

```php
$converter = new Md2Html([
    'allowedBasePath' => '/var/www/docs',
]);

// Safe — inside the allowed directory
$html = $converter->convertFile('/var/www/docs/intro.md');

// Throws InvalidArgumentException — outside the allowed directory
$html = $converter->convertFile('/etc/passwd.md');
```

---

## Supported Markdown Features

| Feature | Syntax |
|:--------|:-------|
| ATX headings | `# H1` … `###### H6` |
| Setext headings | Underline with `===` or `---` |
| Heading anchors | Auto-generated `id` attribute (slug) |
| Paragraphs | Blank-line separated text |
| **Bold** | `**text**` or `__text__` |
| *Italic* | `*text*` or `_text_` |
| ***Bold italic*** | `***text***` |
| ~~Strikethrough~~ | `~~text~~` |
| Inline code | `` `code` `` |
| Fenced code blocks | ` ```lang … ``` ` or `~~~lang … ~~~` |
| Blockquotes | `> text` |
| Unordered lists | `- `, `* `, or `+ ` |
| Ordered lists | `1. `, `2. ` … |
| Nested lists | Indent child items by 2+ spaces |
| Tables (GFM) | Pipe-delimited with alignment |
| Links | `[text](url)` or `[text](url "title")` |
| Auto-links | `<https://example.com>` |
| Images | `![alt](url)` |
| Hard line breaks | Two trailing spaces |
| Horizontal rules | `---`, `***`, `___` |

---

## Syntax Highlighting

Server-side highlighting is applied to fenced code blocks. The language is
specified after the opening fence:

````
```php
echo 'Hello';
```
````

| Language identifier(s)       | Language         |
|:-----------------------------|:-----------------|
| `php`                        | PHP (uses built-in `highlight_string` when tokenizer ext. is available) |
| `js`, `javascript`, `ts`, `typescript` | JavaScript / TypeScript |
| `sql`                        | SQL              |
| `bash`, `sh`, `shell`, `bsh` | Bash / Shell     |
| `html`, `xml`, `svg`         | HTML / XML       |
| `css`, `scss`, `less`        | CSS              |
| `python`, `py`               | Python           |
| `json`                       | JSON             |

Unknown languages are rendered as plain, escaped text.

Highlighted tokens receive CSS classes that are styled by `md2html.css`:

| CSS class      | Token type |
|:---------------|:-----------|
| `hl-keyword`   | Language keywords |
| `hl-string`    | String literals |
| `hl-comment`   | Comments |
| `hl-number`    | Numeric literals |
| `hl-variable`  | Variables (e.g. `$var` in PHP) |
| `hl-attribute` | Attributes / object keys |
| `hl-tag`       | HTML/XML tag names |

---

## CSS Themes

Three stylesheets are provided in `assets/css/`:

| File          | Description |
|:--------------|:------------|
| `md2html.css` | **Main stylesheet.** Includes both light and dark variables. Light is the default; dark activates via `prefers-color-scheme: dark` media query *and* when `data-theme="dark"` is set on `<html>`. |
| `light.css`   | Imports `md2html.css` and pins `color-scheme: light`, ignoring OS preference. |
| `dark.css`    | Imports `md2html.css` and forces dark variables regardless of OS preference. |

When no `cssPath` is provided, the contents of `md2html.css` are embedded
inline in a `<style>` block so the library works without file-serving
infrastructure.

---

## Security

Security is a first-class concern in this library.

| Threat | Mitigation |
|:-------|:-----------|
| **XSS via Markdown content** | All plain text, heading text, link text, and alt attributes are processed through `htmlspecialchars()` before output. |
| **XSS via URLs** | Link `href` and image `src` values are validated: only `http`, `https`, `ftp`, `ftps`, `mailto`, and safe relative paths are allowed. Dangerous schemes like `javascript:` and `data:` are replaced with `#`. |
| **Path traversal** | `convertFile()` calls `realpath()` to resolve the canonical path and rejects values that are not regular files. When `allowedBasePath` is set, the resolved path must be a strict descendant of that directory. |
| **Disallowed file types** | Only files with extensions `md`, `markdown`, or `txt` are accepted by `convertFile()`. |
| **Oversized files** | Files larger than 10 MiB are rejected. |
| **`X-Content-Type-Options` header** | The generated page includes `<meta http-equiv="X-Content-Type-Options" content="nosniff">`. |
| **External link safety** | Links to external URLs automatically receive `target="_blank" rel="noopener noreferrer"`. |
| **`customHeader` trust boundary** | The `customHeader` option accepts raw HTML and is **not** sanitised by the library. Only pass trusted values here. |

---

## Running the Tests

```bash
# Install dev dependencies
composer install

# Run PHPUnit tests
composer test
```

Or directly:

```bash
vendor/bin/phpunit tests
```

Expected output:

```
OK (38 tests, 59 assertions)
```

---

## License

MIT — see [LICENSE](LICENSE).
