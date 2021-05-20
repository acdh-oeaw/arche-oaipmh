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

namespace acdhOeaw\arche\oaipmh\metadata;

use DOMDocument;
use DOMElement;
use PDO;
use EasyRdf\Literal;
use EasyRdf\Resource;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

/**
 * Creates OAI-PMH &lt;metadata&gt; element in Dublin Core format from 
 * a FedoraResource RDF metadata.
 * 
 * It reads the metadata property mappings from the ontology being part of the
 * repository by searching for:
 *   [dcRes] --cfg:eqProp--> acdhProp
 *
 * Requires metadata format configuration properties:
 * - eqProp    - RDF property denoting properties equivalence
 * - titleProp - RDF property denoting a resource title/label
 * - acdhNmsp  - ACDH properties namespace
 * - mode      - URL/title/both - how RDF properties pointing to other resources
 *               should be handled (by providing thei URLs, their titles or both)
 * 
 * @author zozlak
 */
class AcdhDcMetadata implements MetadataInterface {

    const MODE_URL   = 'URL';
    const MODE_TITLE = 'title';
    const MODE_BOTH  = 'both';

    /**
     * Dublin Core namespace
     * @var string
     */
    static private $dcNmsp = 'http://purl.org/dc/elements/1.1/';

    /**
     * Stores metadata property to Dublic Core property mappings
     * @var ?array<string, string>
     */
    static private $mappings = null;

    /**
     * Fetches mappings from the triplestore
     * @param RepoDb $repo
     * @param MetadataFormat $format
     */
    static private function init(RepoDb $repo, MetadataFormat $format) {
        if (is_array(self::$mappings)) {
            return;
        }

        $query = "
            SELECT i1.ids AS dc, i2.ids AS acdh
            FROM
                relations r
                JOIN identifiers i1 USING (id)
                JOIN identifiers i2 ON r.target_id = i2.id
            WHERE
                i1.ids LIKE 'http://purl.org/dc/%'
                AND i2.ids LIKE ?
                AND r.property = ?
        ";
        $param = [$format->acdhNmsp . '%', $format->eqProp];
        $query = $repo->runQuery($query, $param);

        self::$mappings = array();
        while ($i              = $query->fetch(PDO::FETCH_OBJ)) {
            self::$mappings[(string) $i->acdh] = (string) $i->dc;
        }
    }

    /**
     * Repository resource object
     * @var RepoResourceDb
     */
    private $res;

    /**
     * Metadata format descriptor
     * @var MetadataFormat
     */
    private $format;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param object $searchResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                object $searchResultRow, MetadataFormat $format) {
        $this->res    = $resource;
        $this->format = $format;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        self::init($this->res->getRepo(), $this->format);

        $doc    = new DOMDocument();
        $parent = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $parent->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
        $doc->appendChild($parent);

        $titleOrBoth = in_array($this->format->mode, [self::MODE_TITLE, self::MODE_BOTH]);
        if ($titleOrBoth) {
            $this->res->loadMetadata(true, RepoResourceInterface::META_NEIGHBORS);
        }
        $meta       = $this->res->getGraph();
        $properties = array_intersect($meta->propertyUris(), array_keys(self::$mappings));
        foreach ($properties as $property) {
            $propInNs = str_replace(self::$dcNmsp, 'dc:', self::$mappings[$property]);
            foreach ($meta->all($property) as $value) {
                $el = $doc->createElementNS(self::$dcNmsp, $propInNs);
                if (is_a($value, Literal::class) || $this->format->mode == self::MODE_URL || $this->format->mode == self::MODE_BOTH) {
                    $el->appendChild($doc->createTextNode((string) $value));
                    $parent->appendChild($el);
                }
                if (is_a($value, Resource::class) && ($this->format->mode == self::MODE_TITLE || $this->format->mode == self::MODE_BOTH)) {
                    /* @var $value Resource */
                    $el->appendChild($doc->createTextNode((string) $value->get($this->format->titleProp)));
                    $parent->appendChild($el);
                }
                if (is_a($value, Literal::class) && !empty($value->getLang())) {
                    $el->setAttribute('xml:lang', $value->getLang());
                }
            }
        }
        return $parent;
    }

    /**
     * This implementation has no need to extend the search query.
     * 
     * @param MetadataFormat $format
     * @return QueryPart
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }

    /**
     * This implementation has no need to extend the search query.
     * 
     * @param MetadataFormat $format
     * @return QueryPart
     */
    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
    }
}
