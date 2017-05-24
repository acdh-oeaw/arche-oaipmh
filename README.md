An OAI-PMH service for the ACDH repository.

* Uses metadata from the Fedora repository triplestore
* Fetches OAI-PMH header data (identifier and modyfication date) from given RDF properties in the triplestore
  (e.g. dct:identifier and http://fedora.info/definitions/v4/repository#lastModified)
    * It is hardcoded at the moment but can be easily moved to the configuration file.
* Is able to fetch OAI-PMH metadata in two ways:
    * by creating a Dublin Core them from corresponding Dublin Core and Dublin Core Terms found in Fedora resource's RDF metadata
    * by taking content of other Fedora resource denoted by a chosen resource's RDF property (e.g. https://vocabs.acdh.ac.at/#hasCMDIcollection)
* Can be quite easily extended
* Depends only on the Fedora REST API and the triplestore, therefore is not affected by internal changes in Fedora.

# Installation

* clone the repo
* run `composer update`
    * of course make sure you have `composer` first
* rename `config.ini.inc` to `config.ini`
* update `config.ini` content
    * read the comments - information on describing metadata formats is provided there (as well as examples)
* deploy as a normal PHP website

