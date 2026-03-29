<?php

declare(strict_types=1);

/**
 * Md2Html – Markdown to HTML Converter Library
 *
 * A secure, self-contained PHP library that converts Markdown files (or strings)
 * into beautiful, responsive HTML pages with light/dark theme support and
 * server-side syntax highlighting for PHP, JavaScript, SQL, Bash, and more.
 *
 * @package  Md2Html
 * @version  1.0.0
 * @license  MIT
 */
class Md2Html
{
    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    public const VERSION = '1.0.0';

    /** Maximum accepted file size (10 MiB). */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** Allowed Markdown file extensions (lower-cased). */
    private const ALLOWED_EXTENSIONS = ['md', 'markdown', 'txt'];

    // -----------------------------------------------------------------------
    // Properties
    // -----------------------------------------------------------------------

    /** @var array<string, mixed> Resolved configuration options. */
    private array $options;

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    /**
     * Create a new Md2Html instance.
     *
     * @param array<string, mixed> $options {
     *   @type bool        $headless        Output only the <body> content, no full page wrapper. Default false.
     *   @type string      $theme           'light' or 'dark'. Default 'light'.
     *   @type string      $title           HTML <title> value. When empty, derived from first H1.
     *   @type string      $cssPath         Relative or absolute URL/path to an external CSS file.
     *                                       When empty the bundled md2html.css is used.
     *   @type string      $customHeader    Raw HTML injected into <head> (e.g. extra <meta> tags,
     *                                       analytics snippets). Must be trusted content.
     *   @type string|null $allowedBasePath Restrict convertFile() to files under this directory.
     *                                       Highly recommended in web contexts. Default null (no restriction).
     * }
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'headless'        => false,
            'theme'           => 'light',
            'title'           => '',
            'cssPath'         => '',
            'customHeader'    => '',
            'allowedBasePath' => null,
        ], $options);

        // Sanitise theme
        if (!in_array($this->options['theme'], ['light', 'dark'], true)) {
            $this->options['theme'] = 'light';
        }
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Convert a Markdown *file* to HTML.
     *
     * @param  string $filePath Absolute or relative path to a Markdown file.
     * @return string           Full HTML page, or body fragment when headless mode is active.
     *
     * @throws \InvalidArgumentException When the path is rejected by security checks.
     * @throws \RuntimeException         When the file cannot be read.
     */
    public function convertFile(string $filePath): string
    {
        $filePath = $this->validateFilePath($filePath);

        $markdown = file_get_contents($filePath);
        if ($markdown === false) {
            throw new \RuntimeException(
                'Unable to read file: ' . basename($filePath)
            );
        }

        return $this->convert($markdown);
    }

    /**
     * Convert a Markdown *string* to HTML.
     *
     * @param  string $markdown Raw Markdown text.
     * @return string           Full HTML page, or body fragment when headless mode is active.
     */
    public function convert(string $markdown): string
    {
        // Normalise line endings
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        $bodyHtml = $this->parseMarkdown($markdown);

        if ($this->options['headless']) {
            return $bodyHtml;
        }

        $title = $this->options['title'] !== ''
            ? htmlspecialchars($this->options['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : $this->extractTitle($bodyHtml);

        return $this->renderPage($bodyHtml, $title);
    }

    // -----------------------------------------------------------------------
    // Security helpers
    // -----------------------------------------------------------------------

    /**
     * Validate a file path before reading it.
     *
     * Checks:
     *  – The path resolves to a real, readable file.
     *  – The extension is in the allow-list.
     *  – The file is within $options['allowedBasePath'] when that option is set.
     *  – The file does not exceed MAX_FILE_SIZE.
     *
     * @param  string $filePath
     * @return string Resolved real path.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function validateFilePath(string $filePath): string
    {
        // Resolve to an absolute path (also strips ../ sequences)
        $realPath = realpath($filePath);

        if ($realPath === false || !is_file($realPath)) {
            throw new \InvalidArgumentException('File not found or is not a regular file.');
        }

        if (!is_readable($realPath)) {
            throw new \InvalidArgumentException('File is not readable.');
        }

        // Extension check
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Disallowed file extension "%s". Allowed: %s.',
                    $ext,
                    implode(', ', self::ALLOWED_EXTENSIONS)
                )
            );
        }

        // Allowed base path restriction
        if ($this->options['allowedBasePath'] !== null) {
            $basePath = realpath((string) $this->options['allowedBasePath']);
            if ($basePath === false) {
                throw new \InvalidArgumentException('allowedBasePath does not exist.');
            }
            // Ensure realPath starts with basePath (with trailing separator to avoid
            // partial directory name matches such as /tmp/foo matching /tmp/foobar).
            $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strncmp($realPath, $basePath, strlen($basePath)) !== 0) {
                throw new \InvalidArgumentException(
                    'Access denied: file is outside the allowed base path.'
                );
            }
        }

        // File size limit
        $size = filesize($realPath);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException(
                sprintf(
                    'File exceeds the maximum allowed size of %d bytes.',
                    self::MAX_FILE_SIZE
                )
            );
        }

        return $realPath;
    }

    // -----------------------------------------------------------------------
    // Markdown parser
    // -----------------------------------------------------------------------

    /**
     * Convert a Markdown string to an HTML fragment (no <html>/<body> wrapper).
     */
    private function parseMarkdown(string $markdown): string
    {
        $lines  = explode("\n", $markdown);
        $output = '';
        $i      = 0;
        $count  = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // ----------------------------------------------------------------
            // Fenced code block  ```lang … ```
            // ----------------------------------------------------------------
            if (preg_match('/^(`{3,}|~{3,})\s*(\S*)\s*$/', $line, $m)) {
                $fence    = $m[1];
                $lang     = strtolower($m[2]);
                $code     = [];
                $i++;
                while ($i < $count && !preg_match('/^' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}\s*$/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // skip closing fence
                $output .= $this->renderCodeBlock(implode("\n", $code), $lang);
                continue;
            }

            // ----------------------------------------------------------------
            // Setext-style headings (must check before paragraph)
            // ----------------------------------------------------------------
            if (isset($lines[$i + 1]) && preg_match('/^=+\s*$/', $lines[$i + 1]) && trim($line) !== '') {
                $output .= '<h1>' . $this->parseInline($line) . "</h1>\n";
                $i += 2;
                continue;
            }
            if (isset($lines[$i + 1]) && preg_match('/^-+\s*$/', $lines[$i + 1]) && trim($line) !== '') {
                $output .= '<h2>' . $this->parseInline($line) . "</h2>\n";
                $i += 2;
                continue;
            }

            // ----------------------------------------------------------------
            // ATX headings  # … ######
            // ----------------------------------------------------------------
            if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?\s*$/', $line, $m)) {
                $level = strlen($m[1]);
                $id    = $this->slugify($m[2]);
                $output .= sprintf(
                    '<h%1$d id="%2$s">%3$s</h%1$d>' . "\n",
                    $level,
                    htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    $this->parseInline($m[2])
                );
                $i++;
                continue;
            }

            // ----------------------------------------------------------------
            // Horizontal rule
            // ----------------------------------------------------------------
            if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $line)) {
                $output .= "<hr>\n";
                $i++;
                continue;
            }

            // ----------------------------------------------------------------
            // Blockquote
            // ----------------------------------------------------------------
            if (preg_match('/^>\s?(.*)/', $line, $m)) {
                $quoteLines = [$m[1]];
                $i++;
                while ($i < $count && preg_match('/^>\s?(.*)/', $lines[$i], $m2)) {
                    $quoteLines[] = $m2[1];
                    $i++;
                }
                $inner = $this->parseMarkdown(implode("\n", $quoteLines));
                $output .= "<blockquote>\n$inner</blockquote>\n";
                continue;
            }

            // ----------------------------------------------------------------
            // Unordered list
            // ----------------------------------------------------------------
            if (preg_match('/^([ \t]*)([*\-+])\s+(.*)/', $line, $m)) {
                [$listHtml, $i] = $this->parseList($lines, $i, 'ul');
                $output .= $listHtml;
                continue;
            }

            // ----------------------------------------------------------------
            // Ordered list
            // ----------------------------------------------------------------
            if (preg_match('/^([ \t]*)\d+\.\s+(.*)/', $line, $m)) {
                [$listHtml, $i] = $this->parseList($lines, $i, 'ol');
                $output .= $listHtml;
                continue;
            }

            // ----------------------------------------------------------------
            // HTML content is escaped for security – raw HTML blocks are not
            // passed through, as this would enable XSS injection.
            // ----------------------------------------------------------------
            // Table  (GFM-style)
            // ----------------------------------------------------------------
            if (strpos($line, '|') !== false
                && isset($lines[$i + 1])
                && preg_match('/^\|?[\s:\-|]+\|[\s:\-|]*$/', $lines[$i + 1])
            ) {
                [$tableHtml, $i] = $this->parseTable($lines, $i);
                $output .= $tableHtml;
                continue;
            }

            // ----------------------------------------------------------------
            // Blank line
            // ----------------------------------------------------------------
            if (trim($line) === '') {
                $i++;
                continue;
            }

            // ----------------------------------------------------------------
            // Paragraph  (collect until blank line or block element)
            // ----------------------------------------------------------------
            $paraLines = [$line];
            $i++;
            while ($i < $count
                && trim($lines[$i]) !== ''
                && !preg_match('/^(#{1,6}\s|`{3,}|~{3,}|>\s?|(?:[*\-+]|\d+\.)\s|(?:\*{3,}|-{3,}|_{3,})\s*$)/', $lines[$i])
            ) {
                $paraLines[] = $lines[$i];
                $i++;
            }
            $output .= '<p>' . $this->parseInline(implode(' ', $paraLines)) . "</p>\n";
        }

        return $output;
    }

    // -----------------------------------------------------------------------
    // Inline element parser
    // -----------------------------------------------------------------------

    /**
     * Parse inline Markdown elements within a single line (or joined lines).
     */
    private function parseInline(string $text): string
    {
        // Escape HTML entities first (before applying our own tags)
        // We process in "segments": plain text gets escaped, code spans are kept raw.

        $result = '';
        // Split on backtick code spans to avoid processing them
        $parts = preg_split('/(`+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $parts = [$text];
        }

        $inCode    = false;
        $codeDelim = '';
        $codeAcc   = '';

        foreach ($parts as $part) {
            if (preg_match('/^`+$/', $part)) {
                if (!$inCode) {
                    $inCode    = true;
                    $codeDelim = $part;
                    $codeAcc   = '';
                } elseif ($part === $codeDelim) {
                    $inCode  = false;
                    $result .= '<code>' . htmlspecialchars(trim($codeAcc), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                    $codeAcc   = '';
                    $codeDelim = '';
                } else {
                    $codeAcc .= $part;
                }
            } elseif ($inCode) {
                $codeAcc .= $part;
            } else {
                $result .= $this->applyInlineFormatting($part);
            }
        }

        // If code span was never closed, emit it as literal
        if ($inCode) {
            $result .= htmlspecialchars($codeDelim . $codeAcc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $result;
    }

    /**
     * Apply inline formatting rules to a plain-text segment (no code spans).
     */
    private function applyInlineFormatting(string $text): string
    {
        // Escape HTML
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Bold+Italic  ***text*** or ___text___
        $text = preg_replace(
            '/(\*{3}|_{3})(?=\S)(.+?)(?<=\S)\1/',
            '<strong><em>$2</em></strong>',
            $text
        ) ?? $text;

        // Bold  **text** or __text__
        $text = preg_replace(
            '/(\*{2}|_{2})(?=\S)(.+?)(?<=\S)\1/',
            '<strong>$2</strong>',
            $text
        ) ?? $text;

        // Italic  *text* or _text_
        $text = preg_replace(
            '/(\*|_)(?=\S)(.+?)(?<=\S)\1/',
            '<em>$2</em>',
            $text
        ) ?? $text;

        // Strikethrough  ~~text~~
        $text = preg_replace(
            '/~~(?=\S)(.+?)(?<=\S)~~/',
            '<del>$1</del>',
            $text
        ) ?? $text;

        // Images  ![alt](url "title")  – must come before links
        // URL pattern allows balanced parentheses so that sanitiseUrl() can
        // reject dangerous schemes (javascript:, data:, etc.) before output.
        $text = preg_replace_callback(
            '/!\[([^\[\]]*)\]\(((?:[^\s"()]|\([^\s"()]*\))+)(?:\s+"([^"]*)")?\)/',
            function (array $m): string {
                $alt   = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $src   = $this->sanitiseUrl($m[2]);
                $title = isset($m[3]) ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
                return '<img src="' . $src . '" alt="' . $alt . '"' . $title . ' loading="lazy">';
            },
            $text
        ) ?? $text;

        // Links  [text](url "title")
        $text = preg_replace_callback(
            '/\[([^\[\]]+)\]\(((?:[^\s"()]|\([^\s"()]*\))+)(?:\s+"([^"]*)")?\)/',
            function (array $m): string {
                $url   = $this->sanitiseUrl($m[2]);
                $title = isset($m[3]) ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
                // Add rel="noopener noreferrer" and target="_blank" for external links
                $rel = '';
                if (preg_match('#^https?://#i', $m[2])) {
                    $rel = ' target="_blank" rel="noopener noreferrer"';
                }
                return '<a href="' . $url . '"' . $title . $rel . '>' . $m[1] . '</a>';
            },
            $text
        ) ?? $text;

        // Auto-links  <http://example.com>
        // After htmlspecialchars(), < and > are &lt; and &gt;
        $text = preg_replace_callback(
            '/&lt;(https?:\/\/[^&]+)&gt;/',
            function (array $m): string {
                $url = $this->sanitiseUrl($m[1]);
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
            },
            $text
        ) ?? $text;

        // Hard line break  (two trailing spaces + newline – already joined to space in paragraphs;
        //  handle explicit \n in inline context)
        $text = preg_replace('/  \n/', '<br>', $text) ?? $text;

        return $text;
    }

    // -----------------------------------------------------------------------
    // List parser
    // -----------------------------------------------------------------------

    /**
     * Parse a list block starting at line index $i.
     *
     * @param  string[] $lines
     * @param  int      $i      Current line index.
     * @param  string   $type   'ul' or 'ol'.
     * @return array{0: string, 1: int}  [html, new index]
     */
    private function parseList(array $lines, int $i, string $type): array
    {
        $html   = "<$type>\n";
        $count  = count($lines);

        // Determine the base indentation level from the first item
        preg_match('/^([ \t]*)/', $lines[$i], $indentMatch);
        $baseIndent = strlen($indentMatch[1]);

        while ($i < $count) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }

            preg_match('/^([ \t]*)/', $line, $indentMatch);
            $indent = strlen($indentMatch[1]);

            if ($indent < $baseIndent) {
                break; // Back to parent level
            }

            if ($indent > $baseIndent) {
                // Nested list – recurse
                $nestedType    = preg_match('/^[ \t]*\d+\./', $line) ? 'ol' : 'ul';
                [$nested, $i]  = $this->parseList($lines, $i, $nestedType);
                // Append to the last <li>
                $html = preg_replace('/<\/li>\s*$/', $nested . '</li>', $html, 1) ?? $html;
                continue;
            }

            // Current indentation matches – this is an item at the current level
            if ($type === 'ul' && preg_match('/^[ \t]*[*\-+]\s+(.*)/', $line, $m)) {
                $html .= '<li>' . $this->parseInline($m[1]) . "</li>\n";
                $i++;
            } elseif ($type === 'ol' && preg_match('/^[ \t]*\d+\.\s+(.*)/', $line, $m)) {
                $html .= '<li>' . $this->parseInline($m[1]) . "</li>\n";
                $i++;
            } else {
                break; // Not a list item at any level – stop
            }
        }

        $html .= "</$type>\n";
        return [$html, $i];
    }

    // -----------------------------------------------------------------------
    // Table parser
    // -----------------------------------------------------------------------

    /**
     * Parse a GFM-style pipe table.
     *
     * @param  string[] $lines
     * @param  int      $i
     * @return array{0: string, 1: int}
     */
    private function parseTable(array $lines, int $i): array
    {
        $headerLine    = $lines[$i];
        $separatorLine = $lines[$i + 1];
        $i += 2;

        // Parse alignments from separator row
        $separatorCells = $this->splitTableRow($separatorLine);
        $alignments     = [];
        foreach ($separatorCells as $cell) {
            $cell = trim($cell);
            if (str_starts_with($cell, ':') && str_ends_with($cell, ':')) {
                $alignments[] = 'center';
            } elseif (str_ends_with($cell, ':')) {
                $alignments[] = 'right';
            } elseif (str_starts_with($cell, ':')) {
                $alignments[] = 'left';
            } else {
                $alignments[] = '';
            }
        }

        // Header row
        $headerCells = $this->splitTableRow($headerLine);
        $html        = "<div class=\"table-responsive\">\n<table>\n<thead>\n<tr>\n";
        foreach ($headerCells as $j => $cell) {
            $align = isset($alignments[$j]) && $alignments[$j] !== ''
                ? ' style="text-align:' . $alignments[$j] . '"'
                : '';
            $html .= '<th' . $align . '>' . $this->parseInline(trim($cell)) . "</th>\n";
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";

        // Data rows
        $count = count($lines);
        while ($i < $count && strpos($lines[$i], '|') !== false) {
            $cells = $this->splitTableRow($lines[$i]);
            $html .= "<tr>\n";
            foreach ($cells as $j => $cell) {
                $align = isset($alignments[$j]) && $alignments[$j] !== ''
                    ? ' style="text-align:' . $alignments[$j] . '"'
                    : '';
                $html .= '<td' . $align . '>' . $this->parseInline(trim($cell)) . "</td>\n";
            }
            $html .= "</tr>\n";
            $i++;
        }

        $html .= "</tbody>\n</table>\n</div>\n";
        return [$html, $i];
    }

    /**
     * Split a GFM pipe-table row into cell strings.
     *
     * @param  string $line
     * @return string[]
     */
    private function splitTableRow(string $line): array
    {
        $line  = trim($line);
        $line  = trim($line, '|');
        return explode('|', $line);
    }

    // -----------------------------------------------------------------------
    // Code block / syntax highlighting
    // -----------------------------------------------------------------------

    /**
     * Render a fenced code block with optional syntax highlighting.
     */
    private function renderCodeBlock(string $code, string $lang): string
    {
        $langClass = $lang !== '' ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
        $highlighted = $this->highlight($code, $lang);
        return "<pre><code$langClass>$highlighted</code></pre>\n";
    }

    /**
     * Apply server-side syntax highlighting.
     *
     * Returns HTML-safe highlighted code. The language name is already lower-cased.
     */
    private function highlight(string $code, string $lang): string
    {
        return match ($lang) {
            'php'                           => $this->highlightPhp($code),
            'js', 'javascript', 'ts', 'typescript'
                                            => $this->highlightJs($code),
            'sql'                           => $this->highlightSql($code),
            'bash', 'sh', 'shell', 'bsh'   => $this->highlightBash($code),
            'html', 'xml', 'svg'            => $this->highlightHtml($code),
            'css', 'scss', 'less'           => $this->highlightCss($code),
            'python', 'py'                  => $this->highlightPython($code),
            'json'                          => $this->highlightJson($code),
            default                         => htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        };
    }

    // -----------------------------------------------------------------------
    // Language-specific highlighters
    // -----------------------------------------------------------------------

    private function highlightPhp(string $code): string
    {
        // Use PHP's built-in highlighter; convert its inline style colours to
        // our CSS classes so that the dark/light theme tokens still work.
        // PHP 8.x highlight_string() produces: <pre><code style="…">…</code></pre>
        if (extension_loaded('tokenizer')) {
            // Ensure opening tag so highlight_string() works
            $wrap   = !str_contains($code, '<?');
            $source = $wrap ? "<?php\n$code" : $code;

            ob_start();
            highlight_string($source);
            $highlighted = (string) ob_get_clean();

            // Strip outer <pre> wrapper (PHP 8.x)
            $highlighted = preg_replace('#^\s*<pre[^>]*>#i', '', $highlighted) ?? $highlighted;
            $highlighted = preg_replace('#</pre>\s*$#i', '', $highlighted) ?? $highlighted;
            // Strip outer <code …> / </code> wrapper
            $highlighted = preg_replace('#^\s*<code[^>]*>#i', '', $highlighted) ?? $highlighted;
            $highlighted = preg_replace('#</code>\s*$#i', '', $highlighted) ?? $highlighted;

            if ($wrap) {
                // Remove the injected "<?php" span (and optional newline span after it)
                $highlighted = preg_replace('#<span[^>]*>&lt;\?php</span>\s*(<br\s*/?>)?\n?#i', '', $highlighted) ?? $highlighted;
            }

            // Convert inline style colours to our CSS classes for dark/light theme support.
            // PHP built-in colour map (defaults, may vary by php.ini ini settings):
            //   #0000BB → default / variables / object refs
            //   #007700 → keywords / operators
            //   #DD0000 → strings
            //   #FF8000 → comments
            //   #000000 → plain text
            $colourMap = [
                '#0000BB' => 'hl-variable',
                '#007700' => 'hl-keyword',
                '#DD0000' => 'hl-string',
                '#FF8000' => 'hl-comment',
                '#000000' => '',   // plain text – no class needed
            ];
            $highlighted = preg_replace_callback(
                '/<span\s+style="color:\s*(#[0-9A-Fa-f]{6})">(.*?)<\/span>/s',
                static function (array $m) use ($colourMap): string {
                    $colour = strtoupper($m[1]);
                    $class  = $colourMap[$colour] ?? 'hl-variable';
                    return $class !== ''
                        ? '<span class="' . $class . '">' . $m[2] . '</span>'
                        : $m[2]; // no wrapper for plain text
                },
                $highlighted
            ) ?? $highlighted;

            return $highlighted;
        }

        return $this->highlightGeneric($code, [
            // Keywords
            'keyword' => '/\b(abstract|and|array|as|break|callable|case|catch|class|clone|const|continue'
                . '|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif'
                . '|endswitch|endwhile|extends|final|finally|fn|for|foreach|function|global|goto|if'
                . '|implements|include|include_once|instanceof|insteadof|interface|isset|list|match'
                . '|namespace|new|or|print|private|protected|public|readonly|require|require_once'
                . '|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|null|true|false)\b/i',
            // Strings
            'string'  => '/(\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*")/s',
            // Comments
            'comment' => '#(//[^\n]*|/\*[\s\S]*?\*/|#[^\n]*)#',
            // Variables
            'variable' => '/(\$[a-zA-Z_]\w*)/',
            // Numbers
            'number'  => '/\b(\d+\.?\d*)\b/',
        ]);
    }

    private function highlightJs(string $code): string
    {
        return $this->highlightGeneric($code, [
            'keyword' => '/\b(async|await|break|case|catch|class|const|continue|debugger|default|delete'
                . '|do|else|export|extends|finally|for|from|function|if|import|in|instanceof|let'
                . '|new|null|of|return|static|super|switch|this|throw|true|false|try|typeof'
                . '|undefined|var|void|while|with|yield|=>)\b/',
            'string'  => '/(\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|`[^`\\\\]*(?:\\\\.[^`\\\\]*)*`)/s',
            'comment' => '#(//[^\n]*|/\*[\s\S]*?\*/)#',
            'number'  => '/\b(\d+\.?\d*)\b/',
        ]);
    }

    private function highlightSql(string $code): string
    {
        return $this->highlightGeneric($code, [
            'keyword' => '/\b(ADD|ALL|ALTER|AND|AS|ASC|BETWEEN|BY|CASE|CHECK|COLUMN|CONSTRAINT|CREATE'
                . '|CROSS|DATABASE|DEFAULT|DELETE|DESC|DISTINCT|DROP|ELSE|END|EXISTS|FOREIGN|FROM'
                . '|FULL|GROUP|HAVING|INDEX|INNER|INSERT|INTO|IS|JOIN|KEY|LEFT|LIKE|LIMIT|NOT|NULL'
                . '|ON|OR|ORDER|OUTER|PRIMARY|REFERENCES|RIGHT|SELECT|SET|TABLE|THEN|TRUNCATE|UNION'
                . '|UNIQUE|UPDATE|VALUES|WHEN|WHERE|WITH)\b/i',
            'string'  => '/(\'[^\']*\'|"[^"]*")/',
            'comment' => '#(--[^\n]*|/\*[\s\S]*?\*/)#',
            'number'  => '/\b(\d+\.?\d*)\b/',
        ]);
    }

    private function highlightBash(string $code): string
    {
        return $this->highlightGeneric($code, [
            'keyword' => '/\b(alias|bg|bind|break|builtin|caller|case|cd|command|compgen|complete'
                . '|compopt|continue|declare|dirs|disown|echo|enable|eval|exec|exit|export|false'
                . '|fc|fg|getopts|hash|help|history|if|then|else|elif|fi|for|while|do|done'
                . '|in|local|logout|mapfile|popd|printf|pushd|pwd|read|readarray|readonly|return'
                . '|set|shift|shopt|source|suspend|test|time|times|trap|true|type|typeset|ulimit'
                . '|umask|unalias|unset|until|wait|select|function)\b/',
            'string'  => '/(\'[^\']*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*")/',
            'comment' => '/(#[^\n]*)/',
            'number'  => '/\b(\d+)\b/',
        ]);
    }

    private function highlightHtml(string $code): string
    {
        $escaped = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Colorise HTML tags and attributes
        $escaped = preg_replace(
            '/(&lt;\/?)([\w\-]+)((?:\s[^&]*?)?)(\/?&gt;)/',
            '<span class="hl-tag">$1$2</span>$3<span class="hl-tag">$4</span>',
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace(
            '/\s([\w\-:]+)(=)/',
            ' <span class="hl-attribute">$1</span>$2',
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace(
            '/(=)(&quot;[^&]*&quot;)/',
            '$1<span class="hl-string">$2</span>',
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace(
            '/(&lt;!--[\s\S]*?--&gt;)/',
            '<span class="hl-comment">$1</span>',
            $escaped
        ) ?? $escaped;

        return $escaped;
    }

    private function highlightCss(string $code): string
    {
        return $this->highlightGeneric($code, [
            'comment' => '#(/\*[\s\S]*?\*/)#',
            'string'  => '/(\'[^\']*\'|"[^"]*")/',
            'keyword' => '/(@[\w\-]+)/',
            'number'  => '/\b(\d+\.?\d*(?:px|em|rem|%|vh|vw|s|ms|deg)?)\b/',
        ]);
    }

    private function highlightPython(string $code): string
    {
        return $this->highlightGeneric($code, [
            'keyword' => '/\b(and|as|assert|async|await|break|class|continue|def|del|elif|else|except'
                . '|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise'
                . '|return|try|while|with|yield|None|True|False)\b/',
            'string'  => '/("""[\s\S]*?"""|\'\'\'[\s\S]*?\'\'\'|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*")/s',
            'comment' => '/(#[^\n]*)/',
            'number'  => '/\b(\d+\.?\d*)\b/',
        ]);
    }

    private function highlightJson(string $code): string
    {
        $escaped = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Keys
        $escaped = preg_replace('/"([^"]+)"(\s*:)/', '<span class="hl-attribute">&quot;$1&quot;</span>$2', $escaped) ?? $escaped;
        // String values
        $escaped = preg_replace('/:\s*(&quot;[^&]*(?:&[^;]+;[^&]*)*&quot;)/', ': <span class="hl-string">$1</span>', $escaped) ?? $escaped;
        // Numbers / booleans / null
        $escaped = preg_replace('/:\s*(\d+\.?\d*|true|false|null)\b/', ': <span class="hl-number">$1</span>', $escaped) ?? $escaped;
        return $escaped;
    }

    /**
     * Generic multi-pattern highlighter.
     *
     * Applies a set of named regex patterns to the source code. The patterns are
     * applied in order; already-matched regions are replaced with a placeholder to
     * avoid double-processing.
     *
     * @param  string   $code
     * @param  string[] $patterns  Associative array of className => regex
     * @return string              HTML-safe highlighted code
     */
    private function highlightGeneric(string $code, array $patterns): string
    {
        // We work on the raw code and collect (start, length, class, text) tuples,
        // then reassemble with HTML escaping applied to non-matched segments.

        /** @var array<array{start: int, end: int, class: string, text: string}> $tokens */
        $tokens = [];

        foreach ($patterns as $class => $pattern) {
            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] ?? $matches[0] as $match) {
                    $tokens[] = [
                        'start' => $match[1],
                        'end'   => $match[1] + strlen($match[0]),
                        'class' => 'hl-' . $class,
                        'text'  => $match[0],
                    ];
                }
            }
        }

        if (empty($tokens)) {
            return htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        // Sort by start position; when two overlap, keep the one that started first.
        usort($tokens, static fn($a, $b) => $a['start'] <=> $b['start']);

        $result = '';
        $pos    = 0;
        $len    = strlen($code);

        foreach ($tokens as $token) {
            if ($token['start'] < $pos) {
                continue; // Skip overlapping match
            }
            // Emit plain text before this token
            if ($token['start'] > $pos) {
                $result .= htmlspecialchars(
                    substr($code, $pos, $token['start'] - $pos),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            }
            $result .= '<span class="' . $token['class'] . '">'
                . htmlspecialchars($token['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span>';
            $pos = $token['end'];
        }

        // Remaining text after last token
        if ($pos < $len) {
            $result .= htmlspecialchars(substr($code, $pos), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // URL sanitisation
    // -----------------------------------------------------------------------

    /**
     * Sanitise a URL, rejecting dangerous schemes.
     *
     * Only http, https, ftp, ftps, mailto, and relative paths are permitted.
     * Anything else is replaced with '#'.
     */
    private function sanitiseUrl(string $url): string
    {
        $url = trim($url);
        // Allow relative paths, anchors, and safe schemes
        if (preg_match('#^(https?|ftps?|mailto)://#i', $url)
            || preg_match('/^[\/?\#]/', $url)
            || preg_match('#^\.\./|^\./|^[a-zA-Z0-9_\-./]+$#', $url)
        ) {
            return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        // Reject javascript:, data:, vbscript: etc.
        return '#';
    }

    // -----------------------------------------------------------------------
    // Utilities
    // -----------------------------------------------------------------------

    /**
     * Create a URL-safe slug from a heading string.
     */
    private function slugify(string $text): string
    {
        // Strip inline Markdown
        $text = preg_replace('/[*_`~#\[\]()]/', '', $text) ?? $text;
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s\-]/', '', $text) ?? $text;
        $text = preg_replace('/[\s\-]+/', '-', $text) ?? $text;
        return trim($text, '-');
    }

    /**
     * Extract the text of the first <h1> element as a page title.
     */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $text = strip_tags($m[1]);
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return 'Document';
    }

    // -----------------------------------------------------------------------
    // Page renderer
    // -----------------------------------------------------------------------

    /**
     * Wrap the HTML body fragment in a complete, responsive HTML5 page.
     *
     * @param  string $bodyHtml HTML fragment for the page body.
     * @param  string $title    Already-escaped page title.
     * @return string           Complete HTML5 document.
     */
    private function renderPage(string $bodyHtml, string $title): string
    {
        $theme        = $this->options['theme'];
        $customHeader = $this->options['customHeader']; // Trusted; caller is responsible for sanitising

        // CSS path: use the bundled stylesheet by default
        $cssPath = $this->options['cssPath'] !== ''
            ? htmlspecialchars((string) $this->options['cssPath'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : null;

        // Build <link> tags
        $cssLinks = '';
        if ($cssPath !== null) {
            $cssLinks = '<link rel="stylesheet" href="' . $cssPath . '">' . "\n";
        } else {
            // Emit the bundled CSS inline via a <style> block so the library is usable
            // without any file-serving infrastructure.
            $cssDir = dirname(__DIR__) . '/assets/css/md2html.css';
            if (is_readable($cssDir)) {
                $cssContent = file_get_contents($cssDir);
                if ($cssContent !== false) {
                    $cssLinks = '<style>' . "\n" . $cssContent . "\n</style>\n";
                }
            }
        }

        $version = self::VERSION;

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en" data-theme="{$theme}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="generator" content="md2html-php v{$version}">
            <meta http-equiv="X-Content-Type-Options" content="nosniff">
            <title>{$title}</title>
            {$cssLinks}
            {$customHeader}
        </head>
        <body>
            <article class="md-body">
                {$bodyHtml}
            </article>
        </body>
        </html>
        HTML;
    }
}
