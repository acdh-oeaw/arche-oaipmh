<?php

/*
 * The MIT License
 *
 * Copyright 2022 zozlak.
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

namespace acdhOeaw\arche\oaipmh\data;

/**
 * Description of ResumptionTokenData
 *
 * @author zozlak
 */
class ResumptionTokenData {

    public string $token;
    public string $expirationDate;
    public ?int $completeListSize;
    public ?int $cursor;

    public function __construct(string $token, string $expirationDate = '',
                                ?int $completeListSize = null,
                                ?int $cursor = null) {
        $this->token            = $token;
        $this->expirationDate   = $expirationDate;
        $this->completeListSize = $completeListSize;
        $this->cursor           = $cursor;
    }

    public function asXml(): string {
        return '<resumptionToken ' .
            'expirationDate="' . htmlentities($this->expirationDate) . '" ' .
            'completeListSize="' . htmlentities((string) $this->completeListSize) . '" ' .
            'cursor="' . htmlentities((string) $this->cursor) . '">' .
            htmlentities($this->token) .
            "</resumptionToken>";
    }
}
