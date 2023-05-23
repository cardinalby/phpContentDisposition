<?php
require_once '../../src/ContentDisposition.php';

use cardinalby\ContentDisposition\ContentDisposition;

$file = 'Ø.txt';
file_put_contents($file, "hello");

$contentDispositionHeader = isset($_GET['no-fallback'])
    ? ContentDisposition::createAttachment($file, null)->formatHeaderLine()
    : ContentDisposition::createAttachment($file)->formatHeaderLine();

header("Cache-Control: public");
header("Content-Description: File Transfer");
header($contentDispositionHeader);
header("Content-Type: text/plain");
header("Content-Transfer-Encoding: binary");

readfile('./' . $file);
