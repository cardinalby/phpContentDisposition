[![Test Suite](https://github.com/cardinalby/phpContentDisposition/actions/workflows/test.yml/badge.svg)](https://github.com/cardinalby/phpContentDisposition/actions/workflows/test.yml)


PHP class for handling (parsing and formatting) a value of  HTTP 
[`Content-Disposition`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition) header. 

Requires **PHP 5.6** or newer.

## Installation

```sh
composer require cardinalby/content-disposition 
```

## Content-Disposition header

The text value of the header is in ISO-8859-1 charset. The header contains:

- `Type`. Can be `attachment`, `inline` or custom.
- `filename` parameter that contains ISO-8859-1 compatible file name.
- `filename*` parameter that contains a file name in a custom charset (with charset specified and URL-encoded)

## API
```php
use cardinalby\ContentDisposition\ContentDisposition;
```

### 🔻 static `create(...)`
```php
public static function create(
    $fileName = null, 
    $fallback = true, 
    $type = 'attachment'
)
```
#### 🔸 `$fileName`
File name, can contain Unicode symbols. Pass `null` to omit `filename` param. Depending on the symbols present in the string, value will be placed to `filename` or `filename*` param.

#### 🔸 `$fallback`
If the `$filename` argument is outside ISO-8859-1, then the file name is actually
stored in a supplemental `filename*` field for clients that support Unicode file names and
a ISO-8859-1 version of the file name is automatically generated.

This specifies the ISO-8859-1 file name to override the automatic generation or
disables the generation all together, defaults to `true`.

- A string will specify the ISO-8859-1 file name to use in place of automatic
  generation.
- `false` will disable including a ISO-8859-1 file name and only include the
  Unicode version (unless the file name is already ISO-8859-1).
- `true` will enable automatic generation if the file name is outside ISO-8859-1.

If the `$filename` argument is ISO-8859-1 and this option is specified and has a
different value, then the `$filename` option is encoded in the extended field
and this set as the fallback field, even though they are both ISO-8859-1.

#### 🔸 `$type`
Specifies the disposition type, defaults to `"attachment"`. This can also be
`"inline"`, or any other value (all values except inline are treated like
`attachment`, but can convey additional information if both parties agree to
it). The type is normalized to lower-case.

### 🔻 static `createAttachment(...)`
A shortcut for `ContentDisposition::create($filename, $fallback, 'attachment')`;

### 🔻 static `createInline(...)`
A shortcut for `ContentDisposition::create($filename, $fallback, 'inline')`;

### 🔻 `format()`
Generates the header string value. 

```php
$v = ContentDisposition::create('£ and € rates.pdf')->format();
// 'attachment; filename="£ and ? rates.pdf"; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf'
```

### 🔻 static `parse()`
Parses a `Content-Disposition` header string and returns `ContentDisposition` object.

```php
$cd = ContentDisposition::parse('attachment; filename="plans.pdf"');
assert($cd->getType() === 'attachment');
assert($cd->getFilename() === 'plans.pdf');
assert($cd->getParameters() === ['filename' => 'plans.pdf']);
```

```php
$cd = ContentDisposition::parse(
    'attachment; filename="EURO rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf'
    );
assert($cd->getType() === 'attachment');
// Unicode version is preferable
assert($cd->getFilename() === '€ rates.pdf');
assert($cd->getParameters() === [
    'filename' => 'EURO rates.pdf', 
    'filename*' => '€ rates.pdf'
]);
```

### 🔻 `getType()`
Returns the download type

### 🔻 `getFilename()`
Returns a value of `filename*` param or (if doesn't exist) a value of `filename` param or `null` (if none exists).

### 🔻 `getParameters()`
Get associative array of all parameters including `filename` and `filename*`.

### 🔻 `getCustomParameters()`
Get associative array of unknown parameters (except `filename` and `filename*`).

## References

Reference implementation: [content-disposition](https://github.com/jshttp/content-disposition) library for NodeJS.

- [RFC 2616: Hypertext Transfer Protocol -- HTTP/1.1][rfc-2616]
- [RFC 5987: Character Set and Language Encoding for Hypertext Transfer Protocol (HTTP) Header Field Parameters][rfc-5987]
- [RFC 6266: Use of the Content-Disposition Header Field in the Hypertext Transfer Protocol (HTTP)][rfc-6266]
- [Test Cases for HTTP Content-Disposition header field (RFC 6266) and the Encodings defined in RFCs 2047, 2231 and 5987][tc-2231]

[rfc-2616]: https://tools.ietf.org/html/rfc2616
[rfc-5987]: https://tools.ietf.org/html/rfc5987
[rfc-6266]: https://tools.ietf.org/html/rfc6266
[tc-2231]: http://greenbytes.de/tech/tc2231/

## License

[MIT](LICENSE)
