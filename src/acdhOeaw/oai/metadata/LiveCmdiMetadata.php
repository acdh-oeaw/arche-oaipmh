<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace acdhOeaw\oai\metadata;

use DOMDocument;
use DOMElement;
use stdClass;
use EasyRdf\Literal;
use EasyRdf\Resource;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Description of LiveCmdiMetadata
 *
 * @author zozlak
 */
class LiveCmdiMetadata implements MetadataInterface {

    /**
     * Stores URI to label cache
     * @var array
     */
    static private $labelCache = [];

    /**
     * Resolves an URI to its label
     * @param string $uri
     * @param Fedora $fedora
     * @param MetadataFormat $format
     * @return string
     */
    static private function getLabel(string $uri, Fedora $fedora,
                                     MetadataFormat $format): string {
        if (!isset(self::$labelCache[$uri])) {
            $query = new SimpleQuery('SELECT ?label WHERE {?@ ^?@ / ?@ ?label.}');
            $query->setValues([$uri, $format->idProp, $format->labelProp]);
            $res   = $fedora->runQuery($query);
            if (count($res) > 0) {
                self::$labelCache[$uri] = (string) $res[0]->label;
            }
        }
        return self::$labelCache[$uri] ?? $uri;
    }

    /**
     * Repository resource object
     * @var \acdhOeaw\fedora\FedoraResource
     */
    private $res;

    /**
     * Metadata format descriptor
     * @var \acdhOeaw\oai\data\MetadataFormat
     */
    private $format;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param FedoraResource $resource repository resource object
     * @param stdClass $sparqlResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(FedoraResource $resource,
                                stdClass $sparqlResultRow,
                                MetadataFormat $format) {
        $this->res    = $resource;
        $this->format = $format;
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->load($this->format->template);
        $this->processElement($doc->documentElement);
        return $doc->documentElement;
    }

    /**
     * This implementation has no need to extend the SPRARQL search query.
     * 
     * @param MetadataFormat $format
     * @param string $resVar
     * @return string
     */
    public static function extendSearchQuery(MetadataFormat $format,
                                             string $resVar): string {
        return '';
    }

    /**
     * Recursively processes all XML elements
     * @param DOMElement $el DOM element to be processed
     */
    private function processElement(DOMElement $el): bool {
        $toRemove = [];
        foreach ($el->childNodes as $ch) {
            if ($ch instanceof DOMElement) {
                $res = $this->processElement($ch);
                if ($res) {
                    $toRemove[] = $ch;
                }
            }
        }
        foreach ($toRemove as $i) {
            $el->removeChild($i);
        }

        $remove = false;
        if ($el->hasAttribute('val')) {
            $remove = $this->insertValue($el);
        }
        return $remove;
    }

    /**
     * Injects metadata values into a given DOM element of the CMDI template.
     * @param DOMElement $el DOM element to be processes
     */
    private function insertValue(DOMElement $el): bool {
        $val = $el->getAttribute('val');

        $remove = true;
        if ($val === 'NOW') {
            $el->textContent = date('Y-m-d');
            $remove          = false;
        } else if ($val === 'URI') {
            $el->textContent = $this->res->getMetadata()->getResource($this->format->uriProp)->getUri();
            $remove          = false;
        } else if ($val === 'OAIURI') {
            $id              = urlencode($this->res->getMetadata()->getResource($this->format->uriProp)->getUri());
            $prefix          = urlencode($this->format->metadataPrefix);
            $el->textContent = $this->format->info->baseUrl . '?verb=GetRecord&metadataPrefix=' . $prefix . '&identifier=' . $id;
            $remove          = false;
        } else if (strpos($val, 'RES/') === 0) {
            $this->insertMetaValues($el, substr($val, 4));
        }

        $el->removeAttribute('val');
        return $remove;
    }

    /**
     * Fetches values from repository resource's metadata and creates corresponing
     * CMDI parts.
     * @param DOMElement $el
     * @param string $val
     * @return null
     */
    private function insertMetaValues(DOMElement $el, string $val) {
        $prop     = $this->format->propNmsp . $val;
        $count    = $el->getAttribute('count');
        $lang     = ($el->getAttribute('lang') ?? '' ) === 'true';
        $getLabel = ($el->getAttribute('getLabel') ?? '') == 'true';
        $values   = [];
        foreach ($this->res->getMetadata()->all($prop) as $i) {
            $language = '';
            $value    = (string) $i;
            if ($i instanceof Literal) {
                $language = $i->getLang();
            }
            if ($i instanceof Resource && $getLabel) {
                $value = self::getLabel($value, $this->res->getFedora(), $this->format);
            }
            if (!isset($values[$language])) {
                $values[$language] = [];
            }
            $values[$language][] = $value;
        }
        if (count($values) === 0) {
            return;
        }

        if ($count === '1') {
            if (isset($values[$this->format->defaultLang])) {
                $values = [$this->format->defaultLang => [$values[$this->format->defaultLang][0]]];
            } else if (isset($values[''])) {
                $values = ['' => [$values[''][0]]];
            } else {
                $values = ['' => [$values[array_keys($values)[0]][0]]];
            }
        }
        $parent = $el->parentNode;
        foreach ($values as $language => $tmp) {
            foreach ($tmp as $value) {
                $ch              = $el->cloneNode(true);
                $ch->removeAttribute('val');
                $ch->removeAttribute('count');
                $ch->removeAttribute('lang');
                $ch->removeAttribute('getLabel');
                $ch->textContent = $value;
                if ($lang && $language !== '') {
                    $ch->setAttribute('xml:lang', $language);
                }
                $parent->appendChild($ch);
            }
        }
    }

}