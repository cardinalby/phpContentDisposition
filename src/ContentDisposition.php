<?php

namespace cardinalby\ContentDisposition;

use InvalidArgumentException;
use LogicException;

class ContentDisposition
{
    const TYPE_ATTACHMENT = 'attachment';
    const TYPE_INLINE = 'inline';

    private static $HEADER_NAME = 'Content-Disposition';
    private static $FILENAME_PARAM = 'filename';
    private static $FILENAME_EXT_PARAM = 'filename*';

    /**
     * RegExp to match percent encoding escape.
     */
    private static $HEX_ESCAPE_REGEXP = "/%[0-9A-Fa-f]{2}/u";

    /**
     * RegExp to match non-latin1 characters.
     * @private
     */
    private static $NON_LATIN1_REGEXP = "/[^\\x20-\\x7e\\xa0-\\xff]/u";

    /**
     * RegExp to match quoted-pair in RFC 2616
     *
     * quoted-pair = "\" CHAR
     * CHAR        = <any US-ASCII character (octets 0 - 127)>
     */
    private static $QESC_REGEXP = "/\\\\([\\x00-\\x7f])/u";

    /**
     * RegExp to match chars that must be quoted-pair in RFC 2616
     */
    private static $QUOTE_REGEXP = "/([\"])/u";

    /**
     * RegExp for various RFC 2616 grammar
     *
     * parameter     = token "=" ( token | quoted-string )
     * token         = 1*<any CHAR except CTLs or separators>
     * separators    = "(" | ")" | "<" | ">" | "@"
     *               | "," | ";" | ":" | "\" | <">
     *               | "/" | "[" | "]" | "?" | "="
     *               | "{" | "}" | SP | HT
     * quoted-string = ( <"> *(qdtext | quoted-pair ) <"> )
     * qdtext        = <any TEXT except <">>
     * quoted-pair   = "\" CHAR
     * CHAR          = <any US-ASCII character (octets 0 - 127)>
     * TEXT          = <any OCTET except CTLs, but including LWS>
     * LWS           = [CRLF] 1*( SP | HT )
     * CRLF          = CR LF
     * CR            = <US-ASCII CR, carriage return (13)>
     * LF            = <US-ASCII LF, linefeed (10)>
     * SP            = <US-ASCII SP, space (32)>
     * HT            = <US-ASCII HT, horizontal-tab (9)>
     * CTL           = <any US-ASCII control character (octets 0 - 31) and DEL (127)>
     * OCTET         = <any 8-bit sequence of data>
     */
    private static $PARAM_REGEXP = "/;[\\x09\\x20]*([!#$%&'*+.0-9A-Z^_`a-z|~-]+)[\\x09\\x20]*=[\\x09\\x20]*(\"(?:[\\x20!\\x23-\\x5b\\x5d-\\x7e\\x80-\\xff]|\\\\[\\x20-\\x7e])*\"|[!#$%&'*+.0-9A-Z^_`a-z|~-]+)[\\x09\\x20]*/u";
    private static $TEXT_REGEXP = "/^[\\x20-\\x7e\\x80-\\xff]+$/u";
    private static $TOKEN_REGEXP = "/^[!#$%&'*+.0-9A-Z^_`a-z|~-]+$/u";

    /**
     * RegExp for various RFC 5987 grammar
     *
     * ext-value     = charset  "'" [ language ] "'" value-chars
     * charset       = "UTF-8" / "ISO-8859-1" / mime-charset
     * mime-charset  = 1*mime-charsetc
     * mime-charsetc = ALPHA / DIGIT
     *               / "!" / "#" / "$" / "%" / "&"
     *               / "+" / "-" / "^" / "_" / "`"
     *               / "{" / "}" / "~"
     * language      = ( 2*3ALPHA [ extlang ] )
     *               / 4ALPHA
     *               / 5*8ALPHA
     * extlang       = *3( "-" 3ALPHA )
     * value-chars   = *( pct-encoded / attr-char )
     * pct-encoded   = "%" HEXDIG HEXDIG
     * attr-char     = ALPHA / DIGIT
     *               / "!" / "#" / "$" / "&" / "+" / "-" / "."
     *               / "^" / "_" / "`" / "|" / "~"
     */
    private static $EXT_VALUE_REGEXP = "/^([A-Za-z0-9!#$%&+\\-^_`{}~]+)'(?:[A-Za-z]{2,3}(?:-[A-Za-z]{3}){0,3}|[A-Za-z]{4,8}|)'((?:%[0-9A-Fa-f]{2}|[A-Za-z0-9!#$&+.^_`|~-])+)$/u";

    /**
     * RegExp for various RFC 6266 grammar
     *
     * disposition-type = "inline" | "attachment" | disp-ext-type
     * disp-ext-type    = token
     * disposition-parm = filename-parm | disp-ext-parm
     * filename-parm    = "filename" "=" value
     *                  | "filename*" "=" ext-value
     * disp-ext-parm    = token "=" value
     *                  | ext-token "=" ext-value
     * ext-token        = <the characters in token, followed by "*">
     */
    private static $DISPOSITION_TYPE_REGEXP = "/^([!#$%&'*+.0-9A-Z^_`a-z|~-]+)[\\x09\\x20]*(?:$|;)/u";

    /**
     * Quote a string for HTTP.
     *
     * @param string $val
     * @return string
     */
    private static function quoteString($val) {
        $val = preg_replace(self::$QUOTE_REGEXP, '\\\\$1', $val);
        if (!is_string($val)) {
            throw new LogicException('val should be string');
        }
        return '"' . $val . '"';
    }

    /**
     * Quote a string for HTTP.
     *
     * @param string $stringInQuotes
     * @return string
     */
    private static function unquoteString($stringInQuotes) {
        return preg_replace(
            self::$QESC_REGEXP,
            '$1',
            substr($stringInQuotes, 1, -1)
        );
    }

    /**
     * Encode a Unicode string for HTTP (RFC 5987).
     *
     * @param string $val
     * @return string
     */
    private static function toUtf8String ($val) {
        return 'UTF-8\'\'' . rawurlencode($val);
    }

    private static function getLatin1($val) {
        // simple Unicode -> ISO-8859-1 transformation
        return preg_replace(self::$NON_LATIN1_REGEXP, '?', $val);
    }

    /**
     * @param string|null $filename
     * @param string|bool|null $fallback
     * @return array
     */
    private static function createParams($filename, $fallback = true)
    {
        if ($filename === null) {
            return [];
        }
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename must be a string');
        }
        if (!is_string($fallback) && !is_bool($fallback) && !is_null($fallback)) {
            throw new InvalidArgumentException('fallback must be string, boolean or null');
        }

        if (is_string($fallback) && preg_match(self::$NON_LATIN1_REGEXP, $fallback)) {
            throw new InvalidArgumentException('fallback must be ISO-8859-1 string');
        }

        // restrict to file base name
        $name = basename($filename);

        // determine if name is suitable for quoted string
        $canPutIntoQuotedString = preg_match(self::$TEXT_REGEXP, $name);

        // generate fallback name
        if (is_string($fallback)) {
            $fallbackName = basename($fallback);
        } else if ($fallback) {
            $fallbackName = self::getLatin1($name);
        } else {
            $fallbackName = null;
        }
        $hasFallback = $fallbackName !== null && $fallbackName !== $name;

        $params = [];

        // set extended filename parameter
        if ($hasFallback ||
            $fallback === null ||
            !$canPutIntoQuotedString ||
            preg_match(self::$HEX_ESCAPE_REGEXP, $name)
        ) {
            $params[self::$FILENAME_EXT_PARAM] = $name;
        }

        // set filename parameter
        if ($fallback !== null && ($canPutIntoQuotedString || $hasFallback)) {
            $params[self::$FILENAME_PARAM] = $hasFallback ? $fallbackName : $name;
        }

        return $params;
    }

    /**
     * Decode an RFC 5987 field value (gracefully).
     *
     * @param string $str
     * @return string
     * @private
     */
    private static function decodeField($str) {
        $matches = [];
        if (!preg_match(self::$EXT_VALUE_REGEXP, $str, $matches)) {
            throw new InvalidArgumentException('invalid extended field value');
        }

        $charset = strtolower($matches[1]);
        $encoded = $matches[2];
        $decoded = rawurldecode($encoded);

        switch ($charset) {
            case 'iso-8859-1':
                return self::getLatin1(self::utf8encode($decoded));
            case 'utf-8':
            case 'utf8':
                return $decoded;
            default:
                throw new InvalidArgumentException('unsupported charset in extended field');
        }
    }

    /**
     * @param string $str ISO-8859-1 str
     * @return string
     */
    private static function utf8encode($str) {
        if (extension_loaded('mbstring')) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        return utf8_encode($str);
    }

    /**
     * @param string|null $fileName File name, can contain Unicode symbols. Pass `null` to omit `filename` param.
     *                              Depending on the symbols present in the string, value will be placed to
     *                              `filename` or `filename*` param.
     * @param string|boolean|null $fallback fallback ISO-8859-1 file name, will be placed to `filename` param if
     *                                 differs from $filename
     * @param string $type 'attachment' (default) or 'inline' type of the download
     *
     * @return ContentDisposition
     * @throws InvalidArgumentException
     */
    public static function create($fileName = null, $fallback = true, $type = self::TYPE_ATTACHMENT)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('Type should be a string');
        }
        if (!preg_match(self::$TOKEN_REGEXP, $type)) {
            throw new InvalidArgumentException('Invalid type');
        }
        $params = self::createParams($fileName, $fallback);
        return new ContentDisposition($params, $type);
    }

    /**
     * @param string|null $fileName File name, can contain Unicode symbols. Pass `null` to omit `filename` param.
     *                              Depending on the symbols present in the string, value will be placed to
     *                              `filename` or `filename*` param.
     * @param string|boolean|null $fallback fallback ISO-8859-1 file name, will be placed to `filename` param if
     *                                 differs from $filename
     * @return ContentDisposition
     * @throws InvalidArgumentException
     */
    public static function createAttachment($fileName = null, $fallback = true)
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        return self::create($fileName, $fallback, self::TYPE_ATTACHMENT);
    }

    /**
     * @param string|null $fileName File name, can contain Unicode symbols. Pass `null` to omit `filename` param.
     *                              Depending on the symbols present in the string, value will be placed to
     *                              `filename` or `filename*` param.
     * @param string|boolean|null $fallback fallback ISO-8859-1 file name, will be placed to `filename` param if
     *                                 differs from $filename
     * @return ContentDisposition
     * @throws InvalidArgumentException
     */
    public static function createInline($fileName = null, $fallback = true)
    {
        return self::create($fileName, $fallback, self::TYPE_INLINE);
    }

    /**
     * @param string $contentDispositionStr
     * @return ContentDisposition
     * @throws InvalidArgumentException
     */
    public static function parse($contentDispositionStr) {
        if (!$contentDispositionStr || !is_string($contentDispositionStr)) {
            throw new InvalidArgumentException("Argument must be not empty string");
        }

        $matches = [];
        if (!preg_match(self::$DISPOSITION_TYPE_REGEXP, $contentDispositionStr, $matches)) {
            throw new InvalidArgumentException('Invalid format');
        }

        // normalize type
        $offset = strlen($matches[0]);
        $type = strtolower($matches[1]);
        $names = [];
        $params = [];

        // calculate index to start at
        if (substr($matches[0], -1, 1) === ';') {
            --$offset;
        }

        // match parameters
        $matches = [];
        $matchesNumber = preg_match_all(self::$PARAM_REGEXP, $contentDispositionStr, $matches, PREG_PATTERN_ORDER, $offset);
        for ($matchIndex = 0; $matchIndex < $matchesNumber; ++$matchIndex) {
            $paramKey = strtolower($matches[1][$matchIndex]);
            $paramValue = $matches[2][$matchIndex];
            $offset += strlen($matches[0][$matchIndex]);

            if (isset($names[$paramKey])) {
                throw new InvalidArgumentException("Duplicated parameter: $paramKey");
            }
            $names[$paramKey] = true;

            if (strpos($paramKey, '*') === strlen($paramKey) - 1) {
                $paramValue = self::decodeField($paramValue);
            } else if ($paramValue[0] === '"') {
                $paramValue = self::unquoteString($paramValue);
            }

            $params[$paramKey] = $paramValue;
        }
        if ($offset !== strlen($contentDispositionStr)) {
            throw new InvalidArgumentException('Invalid parameters format');
        }

        return new ContentDisposition($params, $type);
    }

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @param array $parameters
     * @param string $type
     */
    private function __construct($parameters, $type)
    {
        $this->parameters = $parameters;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function format() {
        $result = strtolower($this->type);

        // append parameters
        $paramKeys = array_keys($this->parameters);
        sort($paramKeys);

        foreach ($paramKeys as $paramName) {
            $val = $paramName[strlen($paramName) - 1] === '*'
                ? self::toUtf8String($this->parameters[$paramName])
                : self::quoteString($this->parameters[$paramName]);
            $result .= "; $paramName=$val";
        }

        return $result;
    }

    /**
     * @return string
     */
    public function formatHeaderLine() {
        return sprintf("%s: %s", self::$HEADER_NAME, $this->format());
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return string[]
     */
    public function getParameters() {
        return $this->parameters;
    }

    /**
     * @return string[]
     */
    public function getCustomParameters() {
        return array_filter(
            $this->parameters,
            static function ($key) {
                return $key !== self::$FILENAME_PARAM && $key !== self::$FILENAME_EXT_PARAM;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return string|null
     */
    public function getFilename() {
        if (isset($this->parameters[self::$FILENAME_EXT_PARAM])) {
            return $this->parameters[self::$FILENAME_EXT_PARAM];
        }
        if (isset($this->parameters[self::$FILENAME_PARAM])) {
            return $this->parameters[self::$FILENAME_PARAM];
        }
        return null;
    }
}
