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

namespace acdhOeaw\oai\set;

use stdClass;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\MetadataFormat;
use acdhOeaw\oai\data\SetInfo;
use acdhOeaw\oai\metadata\DcMetadata;
use acdhOeaw\util\RepoConfig as RC;

/**
 * Provides full sets support.
 * 
 * 
 *
 * @author zozlak
 */
class Complex extends SetInterface {

    /**
     * Creates a part of the SPARQL search query fetching information on sets 
     * a resource belongs to.
     * @param string $resVar SPARQL variable denoting the resource URI
     * @param string $setVar SPARQL variable which should denoted the setSpec 
     *   value in the returned SPARQL query part
     * @return string
     */
    public static function getSetFilter(string $resVar, string $set): string {
        $param = array(
            RC::get('oaiSetProp'),
            RC::get('oaiSetIdProp'),
            RC::get('oaiSetSpecProp'),
            $set
        );
        $query = new SimpleQuery($resVar . ' ?@ / ^?@ / ?@ ?# .', $param);
        return $query->getQuery();
    }

    /**
     * Creates a part of the SPARQL search query fetching information on sets 
     * a resource belongs to.
     * @param string $resVar SPARQL variable denoting the resource URI
     * @param string $setVar SPARQL variable which should denoted the setSpec 
     *   value in the returned SPARQL query part
     * @return string
     */
    public static function getSetClause(string $resVar, string $setVar): string {
        $param = array(
            RC::get('oaiSetProp'),
            RC::get('oaiSetIdProp'),
            RC::get('oaiSetSpecProp')
        );
        $query = $resVar . ' ?@ / ^?@ / ?@ ' . $setVar . ' .';
        $query = new SimpleQuery($query, $param);
        return $query->getQuery();
    }

    /**
     * Handles the `ListSets` OAI-PMH request.
     * @param Fedora $fedora repository connection object
     * @return array
     */
    public static function listSets(Fedora $fedora): array {
        $param   = array(
            RC::get('oaiIdProp'),
            RC::get('oaiSetProp'),
            RC::get('oaiSetIdProp'),
            RC::get('oaiSetSpecProp'),
            RC::get('oaiSetTitleProp')
        );
        $query   = "
            SELECT DISTINCT ?set ?spec ?name WHERE {
                ?res ?@ ?pid .
                ?res ?@ / ^?@ ?set .
                ?set ?@ ?spec .
                ?set ?@ ?name .
            }
        ";
        $query   = new SimpleQuery($query, $param);
        //echo $query->getQuery();
        $results = $fedora->runQuery($query);

        $ret = array();
        foreach ($results as $i) {
            $metaRes = $fedora->getResourceByUri($i->set);
            $meta    = new DcMetadata($metaRes, new stdClass(), new MetadataFormat());
            $ret[]   = new SetInfo((string) $i->spec, (string) $i->name, $meta->getXml());
        }
        return $ret;
    }

}
