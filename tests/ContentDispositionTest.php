<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
namespace cardinalby\ContentDisposition\Tests;

use cardinalby\ContentDisposition\ContentDisposition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContentDispositionTest extends TestCase
{
    public function testCreateEmpty() {
        $this->assertEquals(
            'attachment',
            ContentDisposition::create()->format()
        );
    }

    public function testCreateWithInvalidFilename() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::create(123);
    }

    public function testCreateWithInvalidFilenameType() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::create('e', 'привет');
    }

    public function testCreateWithFileNames()
    {
        $this->assertEquals(
            'attachment; filename="plans.pdf"',
            ContentDisposition::createAttachment('plans.pdf')->format(),
            'should create a header with file name'
        );
        $this->assertEquals(
            'attachment; filename="plans.pdf"',
            ContentDisposition::create('/path/to/plans.pdf')->format(),
            'should use the basename of the string'
        );
    }

    public function testCreateWithFilenameUsAscii()
    {
        $this->assertEquals(
            'attachment; filename="the \\"plans\\".pdf"',
            ContentDisposition::create('the "plans".pdf')->format(),
            'should escape quotes'
        );
    }

    public function testCreateWithFilenameISO88591() {
        // when "filename" is ISO-8859-1
        $this->assertEquals(
            'attachment; filename="«plans».pdf"',
            ContentDisposition::create('«plans».pdf')->format(),
            'should only include filename parameter'
        );
        $this->assertEquals(
            'attachment; filename="the \\"plans\\" (1µ).pdf"',
            ContentDisposition::create('the "plans" (1µ).pdf')->format(),
            'should escape quotes'
        );
    }

    public function testForceExtFilenameOnly() {
        $this->assertEquals(
            "Content-Disposition: attachment; filename*=UTF-8''%C3%98.txt",
            ContentDisposition::createAttachment("Ø.txt", null)->formatHeaderLine(),
            'should encode special characters'
        );
    }

    public function testCreateWithFilenameIsUnicode() {
        // when "filename" is Unicode
        $this->assertEquals(
            'attachment; filename="?????.pdf"; filename*=UTF-8\'\'%D0%BF%D0%BB%D0%B0%D0%BD%D1%8B.pdf',
            ContentDisposition::create('планы.pdf')->format(),
            'should include filename* parameter'
        );
        $this->assertEquals(
            'attachment; filename="£ and ? rates.pdf"; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf',
            ContentDisposition::create('£ and € rates.pdf')->format(),
            'should include filename fallback'
        );
        $this->assertEquals(
            'attachment; filename="? rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf',
            ContentDisposition::create('€ rates.pdf')->format(),
            'should include filename fallback'
        );
        $this->assertEquals(
            'attachment; filename="?\'*%().pdf"; filename*=UTF-8\'\'%E2%82%AC%27%2A%25%28%29.pdf',
            ContentDisposition::create('€\'*%().pdf')->format(),
            'should encode special characters'
        );
    }

    public function testCreateWithFilenameContainsHexEscape() {
        // when "filename" contains hex escape
        $this->assertEquals(
            'attachment; filename="the%20plans.pdf"; filename*=UTF-8\'\'the%2520plans.pdf',
            ContentDisposition::create('the%20plans.pdf')->format(),
            'should include filename* parameter'
        );
        $this->assertEquals(
            'attachment; filename="?%20£.pdf"; filename*=UTF-8\'\'%E2%82%AC%2520%C2%A3.pdf',
            ContentDisposition::create('€%20£.pdf')->format(),
            'should handle Unicode'
        );
    }

    public function testCreateWithFallbackDefaultOption() {
        // with "fallback" option
        $this->assertEquals(
            'attachment; filename="? rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf',
            ContentDisposition::create('€ rates.pdf')->format(),
            'should default to true'
        );
    }

    public function testCreateWithFallbackFalse() {
        // when fallback === false
        $this->assertEquals(
            'attachment; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf',
            ContentDisposition::create('£ and € rates.pdf', false)->format(),
            'should not generate ISO-8859-1 fallback'
        );
        $this->assertEquals(
            'attachment; filename="£ rates.pdf"',
            ContentDisposition::create('£ rates.pdf', false)->format(),
            'should keep ISO-8859-1 filename'
        );
    }

    public function testCreateWithFallbackTrue() {
        // when fallback === true
        $this->assertEquals(
            'attachment; filename="£ and ? rates.pdf"; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf',
            ContentDisposition::create('£ and € rates.pdf', true)->format(),
            'should generate ISO-8859-1 fallback'
        );
        $this->assertEquals(
            'attachment; filename="£ rates.pdf"',
            ContentDisposition::create('£ rates.pdf')->format(),
            'should pass through ISO-8859-1 filename'
        );
    }

    public function testCreateWithFallbackInvalidString() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::create('€ rates.pdf', '€ rates.pdf');
    }

    public function testCreateWithFallbackString() {
        $this->assertEquals(
            'attachment; filename="£ and EURO rates.pdf"; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf',
            ContentDisposition::create('£ and € rates.pdf', '£ and EURO rates.pdf')->format(),
            'should use as ISO-8859-1 fallback'
        );
        $this->assertEquals(
            'attachment; filename="£ rates.pdf"; filename*=UTF-8\'\'%22%C2%A3%20rates%22.pdf',
            ContentDisposition::create('"£ rates".pdf', '£ rates.pdf')->format(),
            'should use as fallback even when filename is ISO-8859-1'
        );
        $this->assertEquals(
            'attachment; filename="plans.pdf"',
            ContentDisposition::create('plans.pdf', 'plans.pdf')->format(),
            'should do nothing if equal to filename'
        );
        $this->assertEquals(
            'attachment; filename="EURO rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf',
            ContentDisposition::create('€ rates.pdf', '/path/to/EURO rates.pdf')->format(),
            'should use the basename of the string'
        );
        $this->assertEquals(
            'attachment',
            ContentDisposition::create(null, 'plans.pdf')->format(),
            'should do nothing without filename option'
        );
    }

    public function testCreateWithInvalidType() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::create(null, false, 42);
    }

    public function testCreateWithInvalidTypeString() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::create(null, false, 'invlaid;type');
    }

    public function testCreateWithSpecifiedType() {
        $this->assertEquals(
            'attachment',
            ContentDisposition::create()->format(),
            'should default to attachment'
        );
        $this->assertEquals(
            'inline',
            ContentDisposition::createInline(null, false)->format(),
            'should create a header with inline type'
        );
        $this->assertEquals(
            'inline; filename="plans.pdf"',
            ContentDisposition::create('plans.pdf', true, 'inline')->format(),
            'should create a header with inline type & filename'
        );
        $this->assertEquals(
            'inline',
            ContentDisposition::create(null, false, 'INLINE')->format(),
            'should normalize type'
        );
    }

    public function testParseInvalidType() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse(32);
    }

    public function testParseTypeQuotedValue() {
        // should reject quoted value
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('"attachment"');
    }

    public function testParseTypeTrailingSemicolon() {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment;');
    }

    public function testParseAttachmentWithNoParams()
    {
        $cd = ContentDisposition::parse('attachment');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals([], $cd->getParameters());
    }

    public function testParseInlineWithNoParams()
    {
        $cd = ContentDisposition::parse('inline');
        $this->assertEquals('inline', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals([], $cd->getParameters());
    }

    public function testParseFormDataWithNoParams()
    {
        $cd = ContentDisposition::parse('form-data');
        $this->assertEquals('form-data', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals([], $cd->getParameters());
    }

    public function testParseWithTrailingLWS()
    {
        $cd = ContentDisposition::parse("attachment \t ");
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals([], $cd->getParameters());
    }

    public function testParseShouldNormalizeTypeToLowercase()
    {
        $cd = ContentDisposition::parse("ATTACHMENT");
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals([], $cd->getParameters());
    }

    public function testParseParamsTrailingSemicolon()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename="rates.pdf";');
    }

    public function testParseParamsInvalidParameterName()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename@="rates.pdf"');
    }

    public function testParseParamsMissingParameterValue()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename=');
    }

    public function testParseParamsInvalidParameterValue()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename=trolly,trains');
    }

    public function testParseParamsInvalidParameters()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename=total/; foo=bar');
    }

    public function testParseParamsDuplicateParameters()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename=foo; filename=bar');
    }

    public function testParseParamsMissingType()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('filename="plans.pdf"');
    }

    public function testParseParamsMissingTypeAndSemicolon()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('; filename="plans.pdf"');
    }

    public function testParseParamsLoweCaseParameterName()
    {
        $cd = ContentDisposition::parse('attachment; FILENAME="plans.pdf"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('plans.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'plans.pdf'], $cd->getParameters());
    }

    public function testParseQuotedParamValue()
    {
        $cd = ContentDisposition::parse('attachment; filename="plans.pdf"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('plans.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'plans.pdf'], $cd->getParameters());
    }

    public function testParseAndUnescapeQuotedValue()
    {
        $cd = ContentDisposition::parse('attachment; filename="the \\"plans\\".pdf"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('the "plans".pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'the "plans".pdf'], $cd->getParameters());
    }

    public function testParseShouldIncludeAllParameters()
    {
        $cd = ContentDisposition::parse('attachment; filename="plans.pdf"; foo=bar');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('plans.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'plans.pdf', 'foo' => 'bar'], $cd->getParameters());
    }

    public function testParseTokenFilename()
    {
        $cd = ContentDisposition::parse('attachment; filename=plans.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('plans.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'plans.pdf'], $cd->getParameters());
    }

    public function testParseISO88591Filename()
    {
        $cd = ContentDisposition::parse('attachment; filename="£ rates.pdf"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('£ rates.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => '£ rates.pdf'], $cd->getParameters());
    }

    public function testParseQuotedExtendedParam()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename*="UTF-8\'\'%E2%82%AC%20rates.pdf"');
    }

    public function testParseUtf8ExtendedFilename()
    {
        $cd = ContentDisposition::parse('attachment; filename*=utf-8\'\'%E2%82%AC%20rates.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('€ rates.pdf', $cd->getFilename());
        $this->assertEquals(['filename*' => '€ rates.pdf'], $cd->getParameters());

        $cd = ContentDisposition::parse('attachment; filename*=UTF-8\'\'%EF%BF%BD');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals("�", $cd->getFilename());
    }

    public function testParseISO88591ExtendedFilename()
    {
        $cd = ContentDisposition::parse('attachment; filename*=ISO-8859-1\'\'%A3%20rates.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('£ rates.pdf', $cd->getFilename());

        $cd = ContentDisposition::parse('attachment; filename*=ISO-8859-1\'\'%82%20rates.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('? rates.pdf', $cd->getFilename());
    }

    public function testParseShouldRejectUnsupportedCharset()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename*=ISO-8859-2\'\'%A4%20rates.pdf');
    }

    public function testParseShouldRejectInvalidUTF8Encoding()
    {
        $this->expectException(InvalidArgumentException::class);
        ContentDisposition::parse('attachment; filename*=UTF-8\'\'f%oo.html');
    }

    public function testParseShouldParseWithEmbeddedLanguage()
    {
        $cd = ContentDisposition::parse('attachment; filename*=UTF-8\'en\'%E2%82%AC%20rates.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('€ rates.pdf', $cd->getFilename());
    }

    public function testParseShouldPreferExtendedParameterValue()
    {
        $cd = ContentDisposition::parse('attachment; filename="EURO rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('€ rates.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'EURO rates.pdf', 'filename*' => '€ rates.pdf'], $cd->getParameters());

        $cd = ContentDisposition::parse('attachment; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf; filename="EURO rates.pdf"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('€ rates.pdf', $cd->getFilename());
        $this->assertEquals(['filename' => 'EURO rates.pdf', 'filename*' => '€ rates.pdf'], $cd->getParameters());
    }

    public function testParseOtherCases()
    {
        $cd = ContentDisposition::parse('attachment; filename="foo-%41.html"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals('foo-%41.html', $cd->getFilename());

        $cd = ContentDisposition::parse('attachment; filename*0="foo."; filename*1="html"');
        $this->assertEquals('attachment', $cd->getType());
        $this->assertEquals(null, $cd->getFilename());
        $this->assertEquals(['filename*0' => 'foo.', 'filename*1' => 'html'], $cd->getCustomParameters());
    }
}
