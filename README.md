An OAI-PMH service for the ACDH repository.

* Uses metadata from the Fedora repository triplestore
  (it means **you must have Fedora coupled with a triplestore**, e.g. using the [fcrepo-indexing-triplestore](https://github.com/fcrepo4-exts/fcrepo-camel-toolbox/tree/master/fcrepo-indexing-triplestore) Fedora plugin)
* Fetches OAI-PMH header data (identifier and modyfication date) from given RDF properties in the triplestore
  (id property can be set in the configuration file using the `fedoraIdProp` configuration setting)
* Is able to fetch OAI-PMH metadata in three ways:
    * by creating a Dublin Core metadata from corresponding Dublin Core and Dublin Core Terms triples found in Fedora resource's RDF metadata
    * by taking content of other Fedora resource denoted by a chosen resource's RDF property (e.g. https://vocabs.acdh.ac.at/#hasCMDIcollection)
        * also checking if the target resource's schema as denoted in its metadata matches metadata schema requested by the user  
          (the RDF property storing schema is denoted by the `cmdiSchemaProp` configuration property)
* Can be easily extended to read metadata from other sources.  
  Just write your own class implementing the `acdhOeaw\oai\metadata\MetadataInterface` interface.
* Depends only on the Fedora REST API and the SPARQL endpoint, therefore is not affected by internal changes in Fedora.

# Installation

* clone the repo
* run `composer update`
    * of course make sure you have `composer` first
* rename `config.ini.inc` to `config.ini`
* update `config.ini` content
    * read the comments - information on describing metadata formats is provided there (as well as examples)
* deploy as a normal PHP website

# Unsupported features

* *resumptionTokens*  
  Implementing *resumptionTokens* in a right way is difficult and in my personal opinion it is better to avoid resumption queries at all (the main problem is Fedora does not provide transaction consistency and isolation - see [ACID](https://en.wikipedia.org/wiki/ACID) - and if I can not guarantee immutable repository state between following resumption queries I do not see a way to support such queries in a right way).  
  Also the service is written in a way it can handle responses of virtually any size (it doesn't buffer the response so memory usage shouldn't be a problem).
* *deleted records*  
  As in our software stack information about deleted resources is removed from the triplestore we did not bother about this feature.  
  Anyway if such data are available in the triplestore it will be easy to add this feature.
* *sets*  
  That's because we are not using sets in our repository.  
  Anyway it won't be difficult to add support for them. Contact us if you need this feature.
