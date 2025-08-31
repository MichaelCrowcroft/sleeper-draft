<?php

namespace App\Helpers;

use Tempest\Highlight\Highlighter;

class CodeHighlighter
{
    private static ?Highlighter $highlighter = null;

    public static function getHighlighter(): Highlighter
    {
        if (self::$highlighter === null) {
            self::$highlighter = new Highlighter();
        }

        return self::$highlighter;
    }

    public static function highlightJson(string $json): string
    {
        return self::getHighlighter()->parse($json, 'json');
    }

    public static function highlight(string $code, string $language = 'json'): string
    {
        return self::getHighlighter()->parse($code, $language);
    }
}
