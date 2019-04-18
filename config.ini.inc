;; Remark - when metadata triples/graphs are listed in the comments below
;   [res] --property--> value 
;  means that a Fedora resource `res` has a metadata property `property` with a value `value`.
;  If a `[res]` is used on the value side, like
;   [res] --property--> [otherRes]
;  it denotes the `[otherRes]` URI.

;; Fedora and SPARQL connection data
; leave `fedoraUser` and `FedoraPswd` empty if your repository and SPARQL 
; endpoint don't require authentication
fedoraApiUrl="https://fedora.domain/fcrepo/rest"
sparqlUrl="https://sparql.domain/sparql/path"
fedoraUser="oai"
fedoraPswd="***"

;; A configuration property required by the underlying repo-php-util library (its value doesn't matter)
fedoraIdProp="http://dummy.uri/"

;; Information reported for `verb=Identify`
; Properties defined by the OAI-PMH specification but missing here:
; - 'granularity' is fixed to "YYYY-MM-DDThh:mm:ssZ" because that's the granularity provided by the Fedora
; - 'deletedRecord' is fetched from the class providing deleted resources handling - see the section below
oaiRepositoryName="myRepositoryName"
oaiBaseUrl="https://my.oai.endpoint/root"
oaiEarliestDatestamp="1900-01-01T00:00:00Z"
oaiAdminEmail="oai-admin@my.domain"

;; Class used to implement deleted records handling
;
; Class implementing deleted resources handling. There are two implementations
; available:
; - \acdhOeaw\oai\deleted\No - when the feature is not supported
; - \acdhOeaw\oai\deleted\RdfProperty - when the deleted resources have a given
;   RDF property values (and not deleted resources don't)
oaiDeletedClass="acdhOeaw\oai\deleted\RdfProperty"
; Value to be reported for the `deletedRecord` property for the OAI-PMH 
; `identify` request (can be "transient" or "persistent" - 
; see https://www.openarchives.org/OAI/openarchivesprotocol.html#DeletedRecords).
; Needed only if `oaiDeletedClass` other then "acdhOeaw\oai\deleted\no".
oaiDeletedRecord="transient"
; RDF property marking a resource as a deleted (resources having this property
; will be reported as deleted).
; Needed only if `oaiDeletedClass` equals "acdhOeaw\oai\deleted\RdfProperty".
oaiDeletedProp="https://vocabs.acdh.oeaw.ac.at/schema#isDeleted"

;; OAI search facets (datestamp and identifier) to RDF property mappings 
oaiDateProp="http://fedora.info/definitions/v4/repository#lastModified"
oaiIdProp="https://vocabs.acdh.oeaw.ac.at/schema#hasPid"

; Class used to implement the search.
; There is only one implementation provided \acdhOeaw\oai\search\BaseSearch.
; If it doesn't fit your needs, provide your own and update this property.
oaiSearchClass="\acdhOeaw\oai\search\BaseSearch"

;; Set-related config
;
; Class implementing sets. There are four implementations available:
; - \acdhOeaw\oai\set\NoSets - when your repository doesn't use sets
; - \acdhOeaw\oai\set\Simple - when the set(s) resource belongs to is denoted
;   by a given RDF property value(s) but there is no other iformation about
;   the sets (<setName> equals <setSet>, no <setDescription> in reply to ListSets OAI-PMH request)
; - \acdhOeaw\oai\set\Acdh - like the `\acdhOeaw\oai\set\Simple`, just also
;   parent resources (in ACDH resource inheritance terms) are searched for the
    RDF property describing the set
; - \acdhOeaw\oai\set\Complex - when a given RDF property points to other
;   repository resource describing the set. In this case full set information
;   can be provided in reply to ListSets OAI-PMH request but of course corresponding
;   resources describing sets must exist in your repository. <setDescripiotn> is
;   gathered using the `\acdhOeaw\oai\metadata\DcMetadata` class created for the
;   resource describing a set.
; If none of them fits your needs, provide your own implementation and update this property.
oaiSetClass="\acdhOeaw\oai\set\Complex"
; Only when class equals \acdhOeaw\oai\set\Simple, \acdhOeaw\oai\set\Acdh or 
; \acdhOeaw\oai\set\Complex
; RDF property denoting sets the resource is part of
oaiSetProp="http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
; Only when class equals \acdhOeaw\oai\set\Complex
; RDF property used to link set id to the repository resource describing the set:
;   [oaiResource] --oaiSetProp-->   setId 
;   [setResource] --oaiSetIdProp--> setId
oaiSetIdProp="https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier"
; Only when class equals \acdhOeaw\oai\set\Complex
; RDF property storing OAI-PMH <setSpec> value:
;   [setResource] --oaiSetSpecProp--> setSpecValue
oaiSetSpecProp="http://www.w3.org/2000/01/rdf-schema#label"
; Only when class equals \acdhOeaw\oai\set\Complex
; RDF property storing OAI-PMH <setName> value
;   [setResource] --oaiSetTitleProp--> setTitleValue
oaiSetTitleProp="http://www.w3.org/2000/01/rdf-schema#label"

;; Metadata formats description
; 
; Each section describes a single metadata format:
; - Section name is not important (it has to be unique but nothing more then that).
; - `metadataPrefix`, `schema` and `metadataNamespace` configuration properties 
;   are simply OAI properties reported for `verb=ListMetadataFormats`
; - `class`  configuration property denotes a PHP class implementing the given metadata format.
;   At the moment five classes are available:
;   - `\acdhOeaw\oai\metatada\DcMetata` - takes resource's RDF metadata and 
;     extracts all RDF properties in `dc` and `dcterms` namespaces.
;     Doesn't require any additional configuration properties.
;   - `\acdhOeaw\oai\metadata\RdfXml` - takes resource's RDF metadata and serialises
;     them as an RDF-XML.
;     Doesn't require any additional configuration properties.
;   - `\acdhOeaw\oai\metadata\ResMetadata` - fetches metadata from a binary 
;     content of another repository resource:
;       [oaiRes]  --format.metaResProp--> metaResId
;       [metaRes] --format.idProp-->      metaResId
;       [metaRes] binary contains XML with the metadata
;     Requires additional configuration properties: `idProp` and `metaResProp`.
;   - `\acdhOeaw\oai\metadata\CmdiMetadata` - fetches metadata from a binary
;     content of another repository resource checking if schema match requested
;     format schema:
;       [oaiRes]  --format.cmdiResProp-->    metaResId
;       [metaRes] --format.idProp-->         metaResId
;       [metaRes] --format.cmdiSchemaProp--> format.schema
;       [metaRes] binary contains XML with the metadata
;     Requires additional configuration properties: `idProp`, `cmdiResProp` and `cmdiSchemaProp`.
;   - `\acdhOeaw\oai\metadata\LiveCmdiMetadata` - generates metadata by filling up
;     CMDI XML templates with resource's metadata values
;     Requires additional configuration properties: `idProp`, `uriProp`, `labelProp`, 
;     `schemaProp`, `templateDir` and `defaultLang`.
;     The `propNmsp[]`, `schemaEnforce` and `schenaDefault` configuration properties are optional.
; - ... other properties required by class handling given metadata format
;   (see classes documentation)

[oai_dc]
metadataPrefix="oai_dc"
schema="http://www.openarchives.org/OAI/2.0/oai_dc.xsd"
metadataNamespace="http://www.openarchives.org/OAI/2.0/oai_dc/"
class="\acdhOeaw\oai\metadata\DcMetadata"

[cmdi_collection]
metadataPrefix="cmdi_collection"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1345561703620/xsd"
metadataNamespace="http://www.clarin.eu/cmd/1"
class="\acdhOeaw\oai\metadata\CmdiMetadata"
; RDF property denoting repository resource storing actual CMDI XML
cmdiResProp="https://vocabs.acdh.oeaw.ac.at/schema#hasMetadata"
; as in ACDH repository resource are denoted indirectly using their internal repository ID, also the RDF property storing ID must be provided
idProp="https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier"
; CMDI resource RDF property storing its schema
cmdiSchemaProp="https://vocabs.acdh.oeaw.ac.at/schema#hasSchema"

[cmdi_lexRes]
metadataPrefix="cmdi_lexRes"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1290431694579/xsd"
metadataNamespace="http://www.clarin.eu/cmd/1"
rdfProperty="https://vocabs.acdh.oeaw.ac.at/schema#hasMetadata"
class="\acdhOeaw\oai\metadata\CmdiMetadata"
; RDF property denoting repository resource storing actual CMDI XML
cmdiResProp="https://vocabs.acdh.oeaw.ac.at/schema#hasMetadata"
; as in ACDH repository resource are denoted indirectly using their internal repository ID, also the RDF property storing ID must be provided
idProp="https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier"
; CMDI resource RDF property storing its schema
cmdiSchemaProp="https://vocabs.acdh.oeaw.ac.at/schema#hasSchema"

[cmdi_dcmiTerms]
metadataPrefix="cmdi_dcmiTerms"
metadataNamespace="http://www.clarin.eu/cmd/1"
schema="http://catalog.clarin.eu/ds/ComponentRegistry/rest/registry/profiles/clarin.eu:cr1:p_1288172614023/xsd"
class="\acdhOeaw\oai\metadata\LiveCmdiMetadata"
; RDF property used as `cmd:ResourceRef` CMDI value
uriProp="https://vocabs.acdh.oeaw.ac.at/schema#hasPid"
; idProp and labelProp are used to fetch URI labels (e.g. fetch name of a resource creator)
; They are tightly coupled with the metadata repository structure used by the ACDH:
;   resIdUri <--idProp-- res --labelProp--> label
idProp="https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier"
labelProp="https://vocabs.acdh.oeaw.ac.at/schema#hasTitle"
; Path to the XML storing CMDI template
template="src/acdhOeaw/oai/metadata/liveCmdi/DcmiTerms.xml"
; Default language used to map multilingual metadata properties to CMDI elements of cardinality 1
defaultLang="en"
; Namespace prefixes used in template file's metadata property placeholders
propNmsp[acdh]="https://vocabs.acdh.oeaw.ac.at/schema#"
