<?php
/**
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: ZenExpert - 26 Dec 2025 $
 */

/**
 * scans for any 'KEY' => 'VALUE' pattern, regardless of file structure
 * ignores whether it's inside a return array or a variable assignment
 */
function get_raw_language_defs($filepath) {
    if (!file_exists($filepath)) return [];

    $source = file_get_contents($filepath);
    $tokens = token_get_all($source);
    $defs = [];

    // state: 0=find key, 1=find arrow, 2=capture value
    $state = 0;
    $current_key = '';
    $buffer = '';

    foreach ($tokens as $token) {
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;

        // skip whitespace and comments during search phases
        if (($state === 0 || $state === 1) && ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT)) {
            continue;
        }

        switch ($state) {
            case 0: // FIND KEY
                // ignore T_VARIABLE ($locales) or logic words (return, if, etc)
                if ($id === T_CONSTANT_ENCAPSED_STRING) {
                    $current_key = trim($text, "'\"");
                    $state = 1; // found a string, check if it's a key
                }
                break;

            case 1: // FIND ARROW
                if ($text === '=>') {
                    $state = 2; // found a key, start capturing value
                    $buffer = '';
                } else {
                    // previous string was not a key (e.g. 'en_US' in a list) - reset and look for the next string
                    $state = 0;

                    // if token is a string, it might be the start of a real key
                    if ($id === T_CONSTANT_ENCAPSED_STRING) {
                        $current_key = trim($text, "'\"");
                        $state = 1;
                    }
                }
                break;

            case 2: // CAPTURE VALUE
                // capture everything until we hit a comma or array closer
                // token_get_all keeps quoted strings together, so we don't worry about commas inside text
                if ($text === ',' || $text === ']' || $text === ')') {
                    $defs[$current_key] = trim($buffer);
                    $state = 0; // reset
                } else {
                    $buffer .= $text;
                }
                break;
        }
    }

    // catch the very last item if file ends without trailing comma
    if ($state === 2 && !empty($current_key)) {
        $defs[$current_key] = trim($buffer);
    }

    return $defs;
}

/**
 * extracts the executable header (locales, setup) from a language file
 * returns everything up to but not including the main "return [" or "return array"
 */
function get_file_header($filepath) {
    if (!file_exists($filepath)) return "<?php\n";

    $source = file_get_contents($filepath);

    // look for the position where the main array return starts
    // search for "return [" or "return array" (case insensitive)
    if (preg_match('/return\s*(\[|array)/i', $source, $matches, PREG_OFFSET_CAPTURE)) {
        $split_point = $matches[0][1];
        $header = substr($source, 0, $split_point);
        // make sure it ends with meaningful spacing
        return rtrim($header) . "\n\n";
    }

    // fallback: If no array return found, return basic php tag
    return "<?php\n";
}

/**
 * checks if a string contains complex PHP (concatenation or constants)
 * Basic users should not touch these
 */
function is_complex_php($raw_val) {
    // if it contains a dot (.) outside of quotes, or no quotes at all, it's complex
    $first = substr($raw_val, 0, 1);
    $last = substr($raw_val, -1);
    if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
        // it is wrapped in quotes. check if the middle contains concatenation
        // naive check but sufficient for 99% of language files
        if (preg_match('/[\'"]\s*\.\s*/', $raw_val)) return true;
        return false;
    }
    return true; // if no quotes: constant or number -> complex.
}

/**
 * syntax checker for Advanced Mode
 */
function is_valid_php_expression($code) {
    if (trim($code) === '') return false;
    $test_code = "return " . $code . ";";
    try {
        if (@eval($test_code) === false) return false;
    } catch (Throwable $e) {
        return false;
    }
    return true;
}

/**
 * scan directory helper
 */
function scan_lang_dir($dir, $prefix = '') {
    $results = [];
    if (is_dir($dir) && $handle = opendir($dir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') continue;
            if (substr($entry, -4) === '.php') {
                $results[] = $prefix . $entry;
            }
        }
        closedir($handle);
    }
    sort($results);
    return $results;
}

/**
 * recursively copy a directory and its content.
 */
function recursive_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst); // Create destination
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursive_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}
