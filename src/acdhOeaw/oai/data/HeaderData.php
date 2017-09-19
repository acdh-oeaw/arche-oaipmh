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

namespace acdhOeaw\oai\data;

use stdClass;

/**
 * Container for data required to generate OAI-PMH resource's header.
 *
 * @author zozlak
 */
class HeaderData {

    /**
     * Resource's OAI-PMH id
     * @var string
     */
    public $id;

    /**
     * Resource's last modification date
     * @var string
     */
    public $date;

    /**
     * List of <setSpec> values denoting sets a resource belongs to
     * @var array
     */
    public $sets = array();

    /**
     * Creates a HeaderData object optionally copying data from a provided object.
     * @param stdClass $src data to copy from
     */
    public function __construct(stdClass $src = null) {
        if ($src === null) {
            return;
        }
        if (isset($src->id)) {
            $this->id = (string) $src->id;
        }
        if (isset($src->date)) {
            $this->date = (string) $src->date;
        }
        if (isset($src->sets)) {
            $tmp = is_array($src->sets) ? $src->sets : array($src->sets);
            foreach ($tmp as $i) {
                $this->sets[] = (string) $i;
            }
        }
    }

}
