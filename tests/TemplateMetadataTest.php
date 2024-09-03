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
<bb>3</bb>
<c><cc>3</cc></c>
<d><dd>3</dd></d>
<e><ee>3</ee></e>
<f><ff>3</ff></f>
<g><gg>3</gg></g>
<h><hh>3</hh></h>
<i><ii>3</ii></i>
<k/>
<k/>
<k>
<kk>flag value</kk>
</k>
<l>3</l>
<n>3</n>
<o><oo>ch1</oo><oo>ch2</oo></o>
<pp>ch1</pp><pp>ch2</pp>
<q>single's</q>
<r>single's</r>
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
<l>URI</l>
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
<a>http://127.0.0.1/api/123</a>
<a>https://bar</a>
<a>https://foo</a>
<b>single's</b>
<c>3</c>
<d>http://127.0.0.1/api/345</d>
<d>http://127.0.0.1/api/346</d>
<d>http://127.0.0.1/api/347</d>
<e>John</e>
<e>Molly</e>
<e>Sue</e>
<f>http://127.0.0.1/api/456</f>
<f>http://127.0.0.1/api/457</f>
<g>inny</g>
<g>jeszcze jeden</g>
<g>one more</g>
<g>other</g>
<h>http://127.0.0.1/api/346</h>
<i>Sue</i>
<j>top</j>
<k>inny</k>
<k>jeszcze jeden</k>
<k>one more</k>
<k>other</k>
<l>3</l>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testValAs(): void {
        $tmpl     = $this->getMetadataObject('common', 'valAs');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>1</a>
<b>2</b>
<c>3</c>
<d>
<foo/>
</d>
<e>&lt;bar/&gt;</e>
<f>&lt;baz/&gt;</f>
<g foo="4"/>
<h bar="&lt;foobar&gt;"/>
<i attr2="foobar" attr1="6">5&lt;foo/&gt;<bar/>7</i>
<j id="8">
<jj>9</jj>
</j>
<k>
<kk>12</kk>11</k>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testValLang(): void {
        $tmpl     = $this->getMetadataObject('common', 'valLang');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>bar</a>
<a>foo</a>
<b>bar</b>
<b>foo</b>
<c>bar</c>
<c xml:lang="en">foo</c>
<d xml:lang="">bar</d>
<d xml:lang="en">foo</d>
<e>3</e>
<f>3</f>
<g>3</g>
<h xml:lang="">3</h>
<i xml:lang="und">barlang</i>
<i xml:lang="en">foolang</i>
<j xml:lang="und">barlang</j>
<j xml:lang="und">foolang</j>
<k foo="bar" xml:lang="und" bar="lang"/>
<k foo="foo" xml:lang="und" bar="lang"/>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testValAction(): void {
        $tmpl     = $this->getMetadataObject('common', 'valAction');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>1 2</a>
<b>3 4</b>
<c>6</c>
<d>8</d>
<e> 10</e>
<f foo=" 12">13 14</f>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testValTransform(): void {
        $tmpl     = $this->getMetadataObject('common', 'valTransform');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>https://foo</a>
<b>https://f#oo#</b>
<c>0003</c>
<d>-0100</d>
<e>0200-03-04 14</e>
<e>1200-07-03 12</e>
<f>bar</f>
<g>foo</g>
<h>0200-03</h>
<i>1200-07</i>
<j>0200-1200</j>
<k>TAG ONE</k>
<k>TAG THREE</k>
<l>JOHN</l>
<l>MOLLY</l>
<m>TAG FOUR</m>
<n xml:lang="en">Speech</n>
<n xml:lang="de">Rede</n>
<o>3</o>
<p xml:lang="en">foo</p>
<q>https://baz</q>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testForeach(): void {
        $tmpl     = $this->getMetadataObject('common', 'foreach');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>
<included current="http://127.0.0.1/api/123">
<includedInt>bar</includedInt>
<includedInt>foo</includedInt>
</included>
</a>
<a>
<included current="https://bar">
<includedInt/>
</included>
</a>
<a>
<included current="https://foo">
<includedInt/>
</included>
</a>
<b>
<ba>http://127.0.0.1/api/345</ba>
<bb>John</bb>
</b>
<b>
<ba>http://127.0.0.1/api/346</ba>
<bb>Sue</bb>
</b>
<b>
<ba>http://127.0.0.1/api/347</ba>
<bb>Molly</bb>
</b>
<c>
<included current="3">
<includedInt/>
</included>
</c>
<dd>bar</dd>
<dd xml:lang="en">foo</dd>
<e>
<ee xml:lang="pl">inny</ee>
</e>
<e>
<ee xml:lang="pl">jeszcze jeden</ee>
</e>
<e>
<ee xml:lang="und">one more</ee>
</e>
<e>
<ee xml:lang="und">other</ee>
</e>
<f>
<fa>http://127.0.0.1/api/456</fa>
<fb>inny</fb>
<fb>other</fb>
</f>
<f>
<fa>http://127.0.0.1/api/457</fa>
<fb>jeszcze jeden</fb>
<fb>one more</fb>
</f>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testEmpty(): void {
        $tmpl     = $this->getMetadataObject('common', 'empty');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<e/>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }

    public function testFetchCycle(): void {
        $tmpl     = $this->getMetadataObject('fetchCycle');
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<a>http://127.0.0.1/api/234</a>
<c>http://127.0.0.1/api/234</c>
<c>http://127.0.0.1/api/345</c>
<e>http://127.0.0.1/api/234</e>
<e>http://127.0.0.1/api/345</e>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);
    }
}
