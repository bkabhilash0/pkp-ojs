<?php

/**
 * @file plugins/oaiMetadataFormats/rfc1807/OAIMetadataFormatPlugin_RFC1807.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormatPlugin_RFC1807
 *
 * @see OAI
 *
 * @brief rfc1807 metadata format plugin for OAI.
 */

namespace APP\plugins\oaiMetadataFormats\rfc1807;

use PKP\plugins\OAIMetadataFormatPlugin;

class OAIMetadataFormatPlugin_RFC1807 extends OAIMetadataFormatPlugin
{
    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     *
     * @return string name of plugin
     */
    public function getName()
    {
        return 'OAIFormatPlugin_RFC1807';
    }

    public function getDisplayName()
    {
        return __('plugins.OAIMetadata.rfc1807.displayName');
    }

    public function getDescription()
    {
        return __('plugins.OAIMetadata.rfc1807.description');
    }

    public function getFormatClass()
    {
        return '\APP\plugins\oaiMetadataFormats\rfc1807\OAIMetadataFormat_RFC1807';
    }

    public static function getMetadataPrefix()
    {
        return 'rfc1807';
    }

    public static function getSchema()
    {
        return 'http://www.openarchives.org/OAI/1.1/rfc1807.xsd';
    }

    public static function getNamespace()
    {
        return 'http://info.internet.isi.edu:80/in-notes/rfc/files/rfc1807.txt';
    }
}
