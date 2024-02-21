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

use DOMDocument;
use DOMElement;
use quickRdf\DataFactory;
use quickRdf\DatasetNode;
use quickRdfIo\Util as QuickRdfIoUtil;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\RepositoryInfo;
use acdhOeaw\arche\oaipmh\metadata\TemplateMetadata;

/**
 * Description of TemplateMetadataTest
 *
 * @author zozlak
 */
class TemplateMetadataTest extends \PHPUnit\Framework\TestCase {

    const TMPDIR   = '/tmp';
    const RES_URI  = 'https://foo';
    const BASE_URL = 'http://127.0.0.1/oaipmh';

    static public function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        mkdir(__DIR__ . self::TMPDIR);
    }

    static public function tearDownAfterClass(): void {
        parent::tearDownAfterClass();
        system("rm -fR '" . __DIR__ . self::TMPDIR . "'");
    }

    public function testCommentsWhitespaces(): void {
        $in       = file_get_contents(__DIR__ . '/data/templateMetadata/keepComments.xml');
        $tmpl     = $this->getTemplateObject('common', $in);
        $xml      = $this->asString($tmpl->getXml());
        $expected = <<<OUT
<root>
<foo/>
<baz/>
</root>
OUT;
        $this->assertEquals($this->std($expected), $xml);

        $tmpl     = $this->getTemplateObject('keepComments', $in);
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
        $tmpl     = $this->getTemplateObject('common', 'if');
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
        $tmpl     = $this->getTemplateObject('common', 'foreach');
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

    private function getTemplateObject(string $caseName,
                                       string | null $template = null): TemplateMetadata {
        $resMeta = new DatasetNode(DataFactory::namedNode(self::RES_URI));
        $resMeta->add(QuickRdfIoUtil::parse(__DIR__ . '/data/templateMetadata/' . $caseName . '.ttl', new DataFactory()));
        $repoRes = $this->createStub(\acdhOeaw\arche\lib\RepoResourceDb::class);
        $repoRes->method('getGraph')->willReturn($resMeta);
        $repoRes->method('getMetadata')->willReturn($resMeta);

        $oaiFormat = new MetadataFormat((object) yaml_parse_file(__DIR__ . '/data/templateMetadata/' . $caseName . '.yml'));
        if (empty($template)) {
            $oaiFormat->templatePath = __DIR__ . '/data/templateMetadata/' . $caseName . '.xml';
        } elseif (file_exists(__DIR__ . '/data/templateMetadata/' . $template . '.xml')) {
            $oaiFormat->templatePath = __DIR__ . '/data/templateMetadata/' . $template . '.xml';
        } else {
            $oaiFormat->templatePath = tempnam(__DIR__ . self::TMPDIR, '');
            file_put_contents($oaiFormat->templatePath, $template);
        }
        $oaiFormat->info = new RepositoryInfo((object) ['baseURL' => self::BASE_URL]);

        return new TemplateMetadata($repoRes, (object) [], $oaiFormat);
    }

    private function asString(DOMElement $el): string {
        return $this->std($el->ownerDocument->saveXML($el));
    }

    private function std(string $str): string {
        return str_replace('><', ">\n<", $str);
    }
}
