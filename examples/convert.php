<?php

declare(strict_types=1);

/**
 * examples/convert.php
 *
 * Demonstrates the most common usages of the md2html-php library.
 * Run from the command line:
 *
 *   php examples/convert.php
 *
 * Or pass a custom Markdown file:
 *
 *   php examples/convert.php path/to/my-doc.md
 */

require_once dirname(__DIR__) . '/src/Md2Html.php';

// ---- 1. Determine input file -----------------------------------------------

$mdFile = isset($argv[1]) ? (string) $argv[1] : __DIR__ . '/example.md';

// ---- 2. Full HTML page (light theme, defaults) -----------------------------

$converter = new Md2Html([
    'theme'           => 'light',
    'allowedBasePath' => __DIR__,   // Only allow files inside examples/
]);

try {
    $html = $converter->convertFile($mdFile);
    $outputFile = __DIR__ . '/output-light.html';
    file_put_contents($outputFile, $html);
    echo "Light theme → $outputFile\n";
} catch (Exception $e) {
    echo "Error (light): " . $e->getMessage() . "\n";
}

// ---- 3. Full HTML page (dark theme) ----------------------------------------

$converterDark = new Md2Html([
    'theme'           => 'dark',
    'title'           => 'md2html-php Demo – Dark',
    'allowedBasePath' => __DIR__,
]);

try {
    $html = $converterDark->convertFile($mdFile);
    $outputFile = __DIR__ . '/output-dark.html';
    file_put_contents($outputFile, $html);
    echo "Dark theme  → $outputFile\n";
} catch (Exception $e) {
    echo "Error (dark): " . $e->getMessage() . "\n";
}

// ---- 4. Headless mode (body fragment only) ----------------------------------

$headless = new Md2Html(['headless' => true]);
$fragment = $headless->convert('# Hello, *World*!');
echo "\nHeadless fragment:\n$fragment\n";

// ---- 5. Custom header (e.g. extra meta tags, analytics) --------------------

$customHeader = new Md2Html([
    'customHeader' => '<meta name="author" content="Klaus-E. Klingner">',
    'cssPath'      => '../assets/css/md2html.css',
    'title'        => 'Custom Header Example',
]);

try {
    $html = $customHeader->convertFile($mdFile);
    $outputFile = __DIR__ . '/output-custom.html';
    file_put_contents($outputFile, $html);
    echo "Custom header → $outputFile\n";
} catch (Exception $e) {
    echo "Error (custom): " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
