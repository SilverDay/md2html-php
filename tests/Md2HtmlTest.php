<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Md2Html.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Md2Html
 */
class Md2HtmlTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Basic Markdown → HTML conversion
    // -----------------------------------------------------------------------

    public function testHeadings(): void
    {
        $converter = new Md2Html(['headless' => true]);

        self::assertStringContainsString('<h1', $converter->convert('# Heading 1'));
        self::assertStringContainsString('<h2', $converter->convert('## Heading 2'));
        self::assertStringContainsString('<h3', $converter->convert('### Heading 3'));
        self::assertStringContainsString('<h6', $converter->convert('###### Heading 6'));
    }

    public function testSetextHeadings(): void
    {
        $converter = new Md2Html(['headless' => true]);

        $h1 = $converter->convert("Title\n=====");
        self::assertStringContainsString('<h1>', $h1);

        $h2 = $converter->convert("Subtitle\n--------");
        self::assertStringContainsString('<h2>', $h2);
    }

    public function testParagraph(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('Hello, World!');
        self::assertStringContainsString('<p>', $html);
        self::assertStringContainsString('Hello, World!', $html);
    }

    public function testBold(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('**bold**');
        self::assertStringContainsString('<strong>', $html);
    }

    public function testItalic(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('*italic*');
        self::assertStringContainsString('<em>', $html);
    }

    public function testBoldItalic(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('***bold italic***');
        self::assertStringContainsString('<strong><em>', $html);
    }

    public function testStrikethrough(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('~~strike~~');
        self::assertStringContainsString('<del>', $html);
    }

    public function testInlineCode(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('Use `echo` to print.');
        self::assertStringContainsString('<code>', $html);
        self::assertStringContainsString('echo', $html);
    }

    public function testHorizontalRule(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('---');
        self::assertStringContainsString('<hr>', $html);
    }

    public function testBlockquote(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('> A quote');
        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringContainsString('A quote', $html);
    }

    public function testUnorderedList(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert("- Item A\n- Item B");
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<li>', $html);
    }

    public function testOrderedList(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert("1. First\n2. Second");
        self::assertStringContainsString('<ol>', $html);
        self::assertStringContainsString('<li>', $html);
    }

    public function testLink(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('[GitHub](https://github.com)');
        self::assertStringContainsString('href="https://github.com"', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function testImage(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('![Alt text](image.png)');
        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('alt="Alt text"', $html);
    }

    public function testTable(): void
    {
        $md = "| Name  | Age |\n|-------|-----|\n| Alice | 30  |";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<th>', $html);
        self::assertStringContainsString('<td>', $html);
    }

    // -----------------------------------------------------------------------
    // Code blocks and syntax highlighting
    // -----------------------------------------------------------------------

    public function testFencedCodeBlock(): void
    {
        $md        = "```php\n\$x = 1;\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('<pre>', $html);
        self::assertStringContainsString('<code', $html);
    }

    public function testSyntaxHighlightingJs(): void
    {
        $md        = "```js\nconst x = 1;\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('hl-keyword', $html);
    }

    public function testSyntaxHighlightingSql(): void
    {
        $md        = "```sql\nSELECT * FROM users;\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('hl-keyword', $html);
    }

    public function testSyntaxHighlightingBash(): void
    {
        $md        = "```bash\necho \"hello\"\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('hl-keyword', $html);
    }

    public function testSyntaxHighlightingPython(): void
    {
        $md        = "```python\ndef hello():\n    return True\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('hl-keyword', $html);
    }

    public function testSyntaxHighlightingHtml(): void
    {
        $md        = "```html\n<p>Hello</p>\n```";
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert($md);
        self::assertStringContainsString('hl-tag', $html);
    }

    // -----------------------------------------------------------------------
    // Full page rendering
    // -----------------------------------------------------------------------

    public function testFullPageContainsDoctype(): void
    {
        $converter = new Md2Html();
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html', $html);
        self::assertStringContainsString('<head>', $html);
        self::assertStringContainsString('<body>', $html);
    }

    public function testLightThemeAttribute(): void
    {
        $converter = new Md2Html(['theme' => 'light']);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('data-theme="light"', $html);
    }

    public function testDarkThemeAttribute(): void
    {
        $converter = new Md2Html(['theme' => 'dark']);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('data-theme="dark"', $html);
    }

    public function testCustomTitle(): void
    {
        $converter = new Md2Html(['title' => 'My Custom Title']);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('<title>My Custom Title</title>', $html);
    }

    public function testTitleDerivedFromH1(): void
    {
        $converter = new Md2Html();
        $html      = $converter->convert('# My Document Heading');
        self::assertStringContainsString('<title>My Document Heading</title>', $html);
    }

    public function testHeadlessMode(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('# Hello');
        self::assertStringNotContainsString('<!DOCTYPE html>', $html);
        self::assertStringNotContainsString('<head>', $html);
        self::assertStringContainsString('<h1', $html);
    }

    public function testCustomHeader(): void
    {
        $meta      = '<meta name="author" content="Test">';
        $converter = new Md2Html(['customHeader' => $meta]);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('name="author"', $html);
    }

    public function testCustomCssPath(): void
    {
        $converter = new Md2Html(['cssPath' => '/my/custom.css']);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('href="/my/custom.css"', $html);
    }

    public function testInvalidThemeFallsBackToLight(): void
    {
        $converter = new Md2Html(['theme' => 'rainbow']);
        $html      = $converter->convert('# Hello');
        self::assertStringContainsString('data-theme="light"', $html);
    }

    // -----------------------------------------------------------------------
    // File conversion security
    // -----------------------------------------------------------------------

    public function testConvertFileReadsValidFile(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convertFile(__DIR__ . '/../examples/example.md');
        self::assertStringContainsString('<h1', $html);
    }

    public function testConvertFileRejectsNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $converter = new Md2Html();
        $converter->convertFile('/tmp/this-file-does-not-exist.md');
    }

    public function testConvertFileRejectsDisallowedExtension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Create a temp file with a disallowed extension
        $tmp = tempnam(sys_get_temp_dir(), 'md2html_test_') . '.php';
        file_put_contents($tmp, '<?php echo "x"; ?>');
        try {
            $converter = new Md2Html();
            $converter->convertFile($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testConvertFileRejectsPathOutsideAllowedBase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $allowedBase = sys_get_temp_dir() . '/md2html_allowed_' . uniqid();
        mkdir($allowedBase);
        // Try to read a file in /tmp that is outside the allowed base
        $tmp = tempnam(sys_get_temp_dir(), 'md2html_outside_') . '.md';
        file_put_contents($tmp, '# Hello');
        try {
            $converter = new Md2Html(['allowedBasePath' => $allowedBase]);
            $converter->convertFile($tmp);
        } finally {
            @unlink($tmp);
            rmdir($allowedBase);
        }
    }

    // -----------------------------------------------------------------------
    // XSS / security checks
    // -----------------------------------------------------------------------

    public function testXssInPlainText(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('<script>alert(1)</script>');
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testJavascriptUrlInLinkIsRejected(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('[click](javascript:alert(1))');
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('href="#"', $html);
    }

    public function testDataUrlInImageIsRejected(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('![x](data:text/html,<script>alert(1)</script>)');
        self::assertStringNotContainsString('data:text', $html);
    }

    public function testHeadingIdIsEscaped(): void
    {
        $converter = new Md2Html(['headless' => true]);
        $html      = $converter->convert('# Hello <script>');
        self::assertStringNotContainsString('<script>', $html);
    }
}
