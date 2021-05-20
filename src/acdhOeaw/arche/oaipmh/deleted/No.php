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

namespace acdhOeaw\arche\oaipmh\deleted;

use zozlak\queryPart\QueryPart;

/**
 * Implementation of the `acdhOeaw\arche\oaipmh\deleted\DeletedInterface` 
 * for repositories not providing data on deleted resources.
 *
 * @author zozlak
 */
class No implements DeletedInterface {

    private object $config;
    
    public function __construct(object $config) {
        $this->config = $config;
    }

    public function getDeletedRecord(): string {
        return 'no';
    }

    public function getDeletedData(): QueryPart {
        return new QueryPart("SELECT 0::bigint AS id, false AS deleted WHERE false");
    }

}
