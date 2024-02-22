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

use DOMElement;
use quickRdf\DatasetNode;
use quickRdf\DataFactory;
use quickRdfIo\Util as QuickRdfIoUtil;
use acdhOeaw\arche\oaipmh\data\HeaderData;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;
use acdhOeaw\arche\oaipmh\data\RepositoryInfo;
use acdhOeaw\arche\oaipmh\metadata\MetadataInterface;

/**
 * Description of TestBase
 *
 * @author zozlak
 */
class TestBase extends \PHPUnit\Framework\TestCase {

    const TMPDIR      = '/tmp';
    const RES_OAI_URI = 'https://foo';
    const RES_URI     = 'http://127.0.0.1/api/123';
    const RES_DATE    = '2024-01-22T18:20:31';
    const BASE_URL    = 'http://127.0.0.1/oaipmh';

    static public function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        mkdir(__DIR__ . self::TMPDIR);
    }

    static public function tearDownAfterClass(): void {
        parent::tearDownAfterClass();
        system("rm -fR '" . __DIR__ . self::TMPDIR . "'");
    }

    protected function getMetadataObject(string $caseName,
                                         string | null $template = null): MetadataInterface {
        $resMeta = new DatasetNode(DataFactory::namedNode(self::RES_URI));
        $resMeta->add(QuickRdfIoUtil::parse(__DIR__ . '/data/' . $caseName . '.ttl', new DataFactory()));
        $repoRes = $this->createStub(\acdhOeaw\arche\lib\RepoResourceDb::class);
        $repoRes->method('getGraph')->willReturn($resMeta);
        $repoRes->method('getMetadata')->willReturn($resMeta);
        $repoRes->method('getUri')->willReturn($resMeta->getNode());

        $oaiFormat = new MetadataFormat((object) yaml_parse_file(__DIR__ . '/data/' . $caseName . '.yml'));
        if (empty($template)) {
            $oaiFormat->templatePath = __DIR__ . '/data/' . $caseName . '.xml';
        } elseif (file_exists(__DIR__ . '/data/' . $template . '.xml')) {
            $oaiFormat->templatePath = __DIR__ . '/data/' . $template . '.xml';
        } else {
            $oaiFormat->templatePath = tempnam(__DIR__ . self::TMPDIR, '');
            file_put_contents($oaiFormat->templatePath, $template);
        }
        $oaiFormat->info = new RepositoryInfo((object) ['baseURL' => self::BASE_URL]);

        $hd         = new HeaderData();
        $hd->id     = self::RES_OAI_URI;
        $hd->repoid = self::RES_URI;
        $hd->date   = self::RES_DATE;
        
        $class      = $oaiFormat->class;
        return new $class($repoRes, $hd, $oaiFormat);
    }

    protected function asString(DOMElement $el): string {
        return $this->std($el->ownerDocument->saveXML($el));
    }

    protected function std(string $str): string {
        return str_replace('><', ">\n<", $str);
    }
}
