<?php

/*
 * The MIT License
 *
 * Copyright 2024 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\oaipmh\tests;

use DateTimeImmutable;

/**
 * Description of TemplateMetadataTest
 *
 * @author zozlak
 */
class TemplateMetadataTest extends TestBase {

    public function testCommentsWhitespaces(): void {
        $in       = file_get_contents(__DIR__ . '/data/keepComments.xml');
        $tmpl     = $this->getMetadataObject('common', $in);
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<foo/>
<baz/>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);

        $tmpl     = $this->getMetadataObject('keepComments', $in);
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<!-- some comment -->
<foo><!-- yet more comment --></foo>
<baz/>
<!-- multiline
         comment -->
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testIf(): void {
        $tmpl     = $this->getMetadataObject('common', 'if');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<bb/>
<c><cc/></c>
<d><dd/></d>
<e><ee/></e>
<f><ff/></f>
<g><gg/></g>
<h><hh/></h>
<i><ii/></i>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testForEach(): void {
        $tmpl     = $this->getMetadataObject('common', 'foreach');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a><included/></a>
<a><included/></a>
<b><bb/></b>
<b><bb/></b>
<b><bb/></b>
<c><included/></c>
<dd/>
<dd/>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testValSpecial(): void {
        $tmpl     = $this->getMetadataObject('common', 'valSpecial');
        $xml      = $this->asString($tmpl->getXml());
        $xml      = explode("\n", $xml);
        $expected = <<<OUT
<root>
<c>[0-9]+</c>
<d>NOWT[0-9]{2}:[0-9]{2}:[0-9]{2}[+][0-9]+</d>
<e>URI</e>
<f>METAURL</f>
<g>OAIID</g>
<h>OAIURL</h>
<i>[0-9]+</i>
<j>2</j>
<k>foo</k>
</root>
OUT;
        $expected = str_replace('NOW', (new DateTimeImmutable())->format('Y-m-d'), $expected);
        $expected = str_replace('URI', self::RES_URI, $expected);
        $expected = str_replace('METAURL', self::RES_URI . '/metadata', $expected);
        $expected = str_replace('OAIID', self::RES_OAI_URI, $expected);
        $expected = str_replace('OAIURL', self::BASE_URL . '[?]verb=GetRecord&amp;metadataPrefix=templateMetadata&amp;identifier=' . rawurldecode(self::RES_OAI_URI), $expected);
        $expected = explode("\n", $expected);
        $this->assertEquals(count($expected), count($xml));
        foreach ($expected as $i => $j) {
            $this->assertMatchesRegularExpression("`^$j$`u", $xml[$i]);
        }
    }

    public function testValPath(): void {
        $tmpl     = $this->getMetadataObject('common', 'valPath');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>https://foo</a>
<a>https://bar</a>
<b>single's</b>
<c>3</c>
<d>https://sue</d>
<d>https://john</d>
<d>https://molly</d>
<e>Sue</e>
<e>John</e>
<e>Molly</e>
<f>https://other</f>
<f>https://one/more</f>
<g>other</g>
<g>inny</g>
<g>one more</g>
<g>jeszcze jeden</g>
<h>https://sue</h>
<i>Sue</i>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }
}
