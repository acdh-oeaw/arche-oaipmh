<?php

/*
 * The MIT License
 *
 * Copyright 2017 zozlak.
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

namespace acdhOeaw\oai\metadata;

use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\oai\MetadataFormat;
use acdhOeaw\oai\OaiException;
use acdhOeaw\util\RepoConfig as RC;

/**
 * Specialization of ResMetadata class checking if the CMDI schema matches
 * metadata format requested by the user.
 *
 * @author zozlak
 */
class CmdiMetadata extends ResMetadata {

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param FedoraResource $resource repository resource for which the
     *   metadata should be returned
     * @param MetadataFormat $format metadata format description
     */
    public function __construct(FedoraResource $resource, MetadataFormat $format) {
        parent::__construct($resource, $format);

        $schemas = $resource->getMetadata()->allResources(RC::get('cmdiSchemaProp'));
        $match   = false;
        foreach ($schemas as $schema) {
            if ($schema->getUri() === $format->schema) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            throw new OaiException('wrong resource schema');
        }
    }

}
