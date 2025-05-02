<?php
/** @noinspection PhpMissingReturnTypeInspection */
/** @noinspection PhpMissingParamTypeInspection */
/** @noinspection PhpUnused */

namespace Ocallit\Route;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Throwable;

/**
 * ToCode: beautified php code from variables value
 *
 * @version 1.0.2 2019-11-21 DateInterval and DatePeriod.
 */
class ToCode {
    /** @var string new line character */
    private static string $nl = "\r\n";
    /** @var string indent code character(s) */
    private static string $tab = "\t";

    /**
     * Exports a variable to PHP code representation
     *
     * @param mixed $value The value to convert to PHP code
     * @return string PHP code representation of the value
     */
    public static function export($value): string {
        // Handle simple scalar types first
        if($value === NULL) {
            return 'null';
        }
        if(is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if(is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if(is_string($value)) {
            if(str_contains($value, "'")) {
                return '"' . str_replace('"', '\\"', $value) . '"';
            }
            return "'" . $value . "'";
        }
        if(is_array($value)) {
            $items = [];
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            foreach($value as $k => $v) {
                $exportedValue = self::export($v);
                if($isAssoc) {
                    $key = is_string($k) ? self::export($k) : $k;
                    $items[] = "$key => $exportedValue";
                } else {
                    $items[] = $exportedValue;
                }
            }

            return '[' . implode(', ', $items) . ']';
        }
        if(is_object($value)) {
            if($value instanceof DateTime || $value instanceof DateTimeImmutable) {
                return "new \\" . get_class($value) . "('" . $value->format('c') . "')";
            }
            // For other objects, return a basic serialized form
            return var_export($value, TRUE);
        }

        // Fallback for other types
        return var_export($value, TRUE);
    }

    /**
     * Nicely printed php code for variable $name, contents $value
     *
     * @param string $name name of the variable, on empty return, ends with ( asumes function call
     * @param mixed $value
     * @param array<int|string, string> $comments array of strings for array elements by key
     * @param string $indent string to indent code
     * @return string php code to initialize variable
     * @noinspection PhpUnused
     */
    public static function variable($name, $value, $comments = [], $indent = "\t\t"): string {
        if(is_array($value)) {
            return "$indent\$$name = [" .
              self::$nl .
              self::arrayToCode($value, "$indent" .
                self::$tab, $comments) .
              self::$nl . "$indent];" .
              self::$nl;
        }
        $itemComment = array_key_exists($name, $comments) ? ' // ' . $comments[$name] : '';
        if(empty($name)) {
            return "$indent\return " . self::valueToCode($value, $name, $indent, $comments) . ';' . $itemComment;
        }
        if(str_contains($name, '(')) {
            return "$indent\$$name" . self::valueToCode($value, $name, $indent, $comments) . ');' . $itemComment;
        }
        return "$indent\$$name = " . self::valueToCode($value, $name, $indent, $comments) . ';' . $itemComment;
    }

    public static function show($var, $label = '', $print_r = FALSE) {
        if($label !== '')
            $label = "<legend style='border:1px darkblue;color:darkblue'>$label</legend>";
        return
          "<fieldset style='margin:1em;padding:0.1em 1em;border:6px inset lightblue;font-family:Courier monospace, monospace'>$label<pre style='margin:0;padding:0;line-height: 1.5em'>" .
          ($print_r ? print_r($var, TRUE) :
            self::variable('out', $var, indent: "&nbsp;&nbsp;")) .
          "</pre></fieldset>";
    }

    /**
     * invalidate ToCode constructor, it is a static class
     */
    private function __construct() {}

    /**
     * Process array to php code output
     *
     * @param array<int|string, mixed> $arr
     * @param string $indent
     * @param array<int|string, string> $comments comment keys when the match to $arr's keys
     * @return string
     */
    private static function arrayToCode($arr, $indent, $comments) {
        try {
            $lines = [];
            foreach($arr as $k => $v) {
                $itemComment = array_key_exists($k, $comments) ? ' // ' . $comments[$k] : '';
                $keyOut = $indent . (is_string($k) ? self::php_string($k) : $k) . ' => ';

                if(is_array($v)) {
                    $lines[] = $keyOut . "[$itemComment" . self::$nl .
                      self::arrayToCode($v, "$indent" . self::$tab, $comments) . self::$nl . "$indent],";
                } else {
                    $lines[] = $keyOut . self::valueToCode($v, $k, $indent, $comments) . ', ' . $itemComment;
                }
            }
            return implode(self::$nl, $lines);
        } catch(Throwable $t) {
            return "Error: " . $t->getMessage() . self::$nl . self::$tab .
              $t->getFile() . " line: " . $t->getLine();
        }
    }

    /**
     * Process not array values to php code output
     *
     * @param mixed $v
     * @param string|int|float $k
     * @param string $indent spaces to indents
     * @param array<int|string, string> $comments comments to add, if any
     * @return string|int|float value v properly formatted
     */
    private static function valueToCode($v, $k, $indent, $comments) {

        if($v === NULL) {
            return 'NULL';
        }
        if(is_bool($v)) {
            return ($v ? 'true' : 'false');
        }
        if(is_string($v)) {
            return self::php_string($v);
        }
        if(is_numeric($v)) {
            return !str_contains("$v", '.') ? $v : round($v, 12);
        }
        if(is_array($v)) {
            return "[" . self::arrayToCode($v, "$indent" . self::$tab, $comments) . self::$nl . "$indent]";
        }
        if($v instanceof DateTimeImmutable) {
            return "new DateTimeImmutable(" . $v->format("'c'") . ")";
        }
        if($v instanceof DateTime) {
            return "new DateTime(" . $v->format("'c'") . ")";
        }
        if($v instanceof DateInterval) {
            return self::dateIntervalSpec($v);
        }
        // if($v instanceof DatePeriod) {return self::datePeriodSpec($v);}
        if(is_object($v)) {
            return self::php_string("new " . get_class($v) . '()') . ' /* @TODO FIX class name, parameters */';
        }
        // is_callable?
        // closure?
        return self::php_string($k) . ' /* @TODO FIX */';
    }

    /**
     * Protect quotes within php string
     *
     * @param string|int|float $v
     * @return string
     */
    private static function php_string($v) {
        $vString = "$v";
        if(
          (str_starts_with($v, "{") && str_ends_with($v, "}")) ||
          (str_ends_with($v, "[") && str_ends_with($v, "]"))
        ) {
            $json = json_decode($v, TRUE);
            if($json !== NULL) {
                $da = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if($da !== NULL)
                    return "json_encode(strim(<<< JSON\r\n$da\r\nJSON), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)";
            }
        }

        if(str_contains($vString, "\r") || str_contains($vString, "\n")) {
            return "<<< VARIABLESET" . self::$nl . $v . self::$nl . "VARIABLESET;" . self::$nl;
        }
        if(!str_contains($vString, "'")) {
            return "'$v'";
        }
        if(!str_contains($vString, '"')) {
            return '"' . $v . '"';
        }
        return '"' . str_replace('"', '\\' . '"', $vString) . '"';
    }

    /**
     * @param DateInterval $interval
     * @return string
     */
    private static function dateIntervalSpec($interval) { // $interval->f needs php 7.1.0
        if($interval->days . 'D' < 0 || $interval->invert !== 0 || $interval->f > 0) {
            return "'" . serialize($interval) . "'";
        }
        if($interval->days !== false) {
            return "new \DateInterval('P" . abs($interval->days . 'D') . "')";
        }
        $p = implode('', [
          ($interval->y == 0 ? '' : abs($interval->y) . 'Y'),
          ($interval->m == 0 ? '' : abs($interval->m) . 'M'),
          ($interval->d == 0 ? '' : abs($interval->d) . 'D'),
        ]);
        $t = implode('', [
          ($interval->h == 0 ? '' : abs($interval->h) . 'H'),
          ($interval->i == 0 ? '' : abs($interval->i) . 'M'),
          ($interval->s == 0 ? '' : abs($interval->s) . 'S'),
            //($interval->f == 0 ? '' : abs($interval->f).'F'),
        ]);
        return "new \\DateInterval('" . (!empty($p) ? 'P' . $p : '') . (!empty($t) ? 'T' . $t : '') . "')";
    }
}
