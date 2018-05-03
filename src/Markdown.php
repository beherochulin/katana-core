<?php
namespace BeeSoft;

use Mni\FrontYAML\Parser;

class Markdown {
    static function parse($text) {
        $parser = new \Parsedown();

        $text = static::cleanLeadingSpace($text);

        return $parser->text($text);
    }
    public static function parseWithYAML($text) {
        $parser = new Parser();

        $parsed = $parser->parse($text);

        return [$parsed->getContent(), $parsed->getYAML()];
    }
    private static function cleanLeadingSpace($text) {
        $i = 0;

        while ( ! $firstLine = explode("\n", $text)[$i] ) {
            $i ++;
        }

        preg_match('/^( *)/', $firstLine, $matches);

        return preg_replace('/^[ ]{'.strlen($matches[1]).'}/m', '', $text);
    }
}