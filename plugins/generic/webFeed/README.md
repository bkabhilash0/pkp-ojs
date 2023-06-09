# Web Feeds Plugin

> Author: MJ Suhonos

> Contributions: Juan Alperin, Alf Eaton, Alec Smecher

## About

This plugin for OJS/OMP/OPS provides a set of syndication feeds for the latest publications in RSS 1.0, RDF and Atom formats.

## License

This plugin is licensed under the GNU General Public License v3. See the file COPYING for the complete terms of this license.

## System Requirements

OJS/OMP/OPS +3.4

## Installation

The plugin is included by default with releases of OJS/OMP/OPS.

## Configuration

The plugin have some settings to define the maximum amount of items and where the feed should be displayed.
It also provides a "block plugin", which allows to display the feeds on the sidebar.

## Known Issues

- Improperly-formatted (non-RFC2822) email addresses within OJS articles or contact addresses may cause invalid feeds to be generated.
- Articles with no abstract (eg. editorials) will create Atom warnings due to lack of entry:content or entry:summary elements
- Multiple publications with the same publish date/time (e.g. in an issue) may create an Atom warning

## Contact/Support

See the PKP support forum: https://forum.pkp.sfu.ca
Bugs: https://github.com/pkp/pkp-lib/issues
