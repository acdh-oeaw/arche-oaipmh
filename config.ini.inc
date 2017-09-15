; Fedora and SPARQL API URLs
fedoraApiUrl="https://fedora.domain/fcrepo/rest"
sparqlUrl="https://sparql.domain/sparql/path"

; Information reported for `verb=Identify`
; (all the other properties reported for `verb=Identify` are 
;  hardcoded at the moment but it is not difficult to move them here)
oaiApiUrl="https://my.oai.endpoint/root"
oaiAdminEmail="oai-admin@my.domain"
oaiRepositoryName="myRepositoryName"

; RDF property used in repository CMDI resources' metadata to denote the CMDI
; record schema
cmdiSchemaProp="https://vocabs.acdh.oeaw.ac.at/#hasSchema"

; Metadata formats description:
; 
; Each section describes a single metadata format:
; - Section name is not important (it has to be unique but nothing more then that).
; - `metadataPrefix`, `schema` and `metadataNamespace` configuration properties 
;   are simply OAI properties reported for `verb=ListMetadataFormats`
; - `class`  configuration property denotes a PHP class responsible for generating
;   OAI serialization of a given metadata format.
;   At the moment two classes are available:
;   - `\acdhOeaw\oai\DcMetata` - takes resource RDF metadata and extracts all RDF
;     properties in `dc` and `dcterms` namespaces.
;   - `acdhOeaw\oai\ResMetadata` - fetches metadata from a binary content of other
;     repository resource. The resource providing actual metadata must be denoted 
;     by a proper RDF triple - see the `rdfProperty` configuration property
;     description below.
;     This class can be used to e.g. provide CMDI records stored as separate
;     repository resources.
; - `rdfProperty` - if the class is `acdhOeaw\oai\ResMetadata` this configuration
;   property stores an RDF property used to denote repository resource storing
;    actual metadata.

[oai_dc]
metadataPrefix="oai_dc"
schema="http://www.openarchives.org/OAI/2.0/oai_dc.xsd"
metadataNamespace="http://www.openarchives.org/OAI/2.0/oai_dc/"
rdfProperty=""
class="\\acdhOeaw\oai\metadata\DcMetadata"

[cmdi_collection]
metadataPrefix="cmdi_collection"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1345561703620/xsd"
metadataNamespace="http://www.clarin.eu/cmd/"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/#hasMetadata"
class="\\acdhOeaw\oai\metadata\CmdiMetadata"

[cmdi_lexRes]
metadataPrefix="cmdi_lexRes"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1290431694579/xsd"
metadataNamespace="http://www.clarin.eu/cmd/"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/#hasMetadata"
class="\\acdhOeaw\oai\metadata\CmdiMetadata"

[cmdi_teiHdr]
metadataPrefix="cmdi_teiHdr"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1380106710826/xsd"
metadataNamespace="http://www.clarin.eu/cmd/"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/#hasMetadata"
class="\\acdhOeaw\oai\metadata\CmdiMetadata"

[cmdi_textCorpus]
metadataPrefix="cmdi_textCorpus"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1290431694580/xsd"
metadataNamespace="http://www.clarin.eu/cmd/"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/#hasMetadata"
class="\\acdhOeaw\oai\metadata\CmdiMetadata"

[cmdi_frequencyList]
metadataPrefix="cmdi_frequencyList"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1357720977490/xsd"
metadataNamespace="http://www.clarin.eu/cmd/"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/#hasMetadata"
class="\\acdhOeaw\oai\metadata\CmdiMetadata"

