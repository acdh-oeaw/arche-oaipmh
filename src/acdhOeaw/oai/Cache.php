<?php

/**
 * The MIT License
 *
 * Copyright 2017 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\oai;

use DOMDocument;
use acdhOeaw\oai\data\HeaderData;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Implements simple caching 
 *
 * @author zozlak
 */
class Cache {

    private $cacheDir;

    public function __construct(string $cacheDir) {
        $this->cacheDir = $cacheDir;
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0750);
        }
    }

    public function check(HeaderData $header, MetadataFormat $format): bool {
         $path = $this->getPath($header->id, $format->metadataPrefix);
         if (!file_exists($path)) {
             return false;
         }
         $cacheDate = date('Y-m-d\TH:i:s\Z', filemtime($path));
         return $cacheDate > $header->date;
    }

    public function put(HeaderData $header, MetadataFormat $format, DOMDocument $doc): void {
        $doc->C14NFile($this->getPath($header->id, $format->metadataPrefix));
    }

    public function get(HeaderData $header, MetadataFormat $format): string {
        return file_get_contents($this->getPath($header->id, $format->metadataPrefix));
    }

    private function getPath(string $id, string $metaPrefix): string {
        return $this->cacheDir . '/' . sha1($metaPrefix . ':' . $id);
    }

}

