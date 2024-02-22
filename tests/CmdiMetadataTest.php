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

use GuzzleHttp\Psr7\Response;

/**
 * Description of CmdiMetadataTest
 *
 * @author zozlak
 */
class CmdiMetadataTest extends TestBase {

    public function testSimple(): void {
        $expected               = <<<OUT
<cmdiMetadata><foo/></cmdiMetadata>
OUT;
        $body                   = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $body->method('__toString')->willReturn($expected);
        $response               = $this->createMock(Response::class);
        $response->method('getBody')->willReturn($body);
        HttpClient::$response   = $response;
        HttpClient::$requestUri = 'https://meta/location';

        $tmpl = $this->getMetadataObject('resMetadata');
        $xml  = $this->asString($tmpl->getXml());
        $this->assertEquals($this->std($expected), $xml);
    }
}
