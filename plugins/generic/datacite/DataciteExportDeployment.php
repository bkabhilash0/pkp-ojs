<?php

/**
 * @file plugins/generic/datacite/DataciteExportDeployment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataciteExportDeployment
 *
 * @brief Base class configuring the datacite export process to an
 * application's specifics.
 */

namespace APP\plugins\generic\datacite;

// XML attributes
define('DATACITE_XMLNS', 'http://datacite.org/schema/kernel-4');
define('DATACITE_XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('DATACITE_XSI_SCHEMAVERSION', '4');
define('DATACITE_XSI_SCHEMALOCATION', 'http://schema.datacite.org/meta/kernel-4/metadata.xsd');

class DataciteExportDeployment
{
    /** @var \PKP\context\Context The current import/export context */
    public $_context;

    /** @var \PKP\plugin\Plugin The current import/export plugin */
    public $_plugin;

    /**
     * Get the plugin cache
     *
     * @return \APP\plugins\PubObjectCache
     */
    public function getCache()
    {
        return $this->_plugin->getCache();
    }

    /**
     * Constructor
     *
     * @param \PKP\context\Context $context
     * @param \APP\plugins\DOIPubIdExportPlugin $plugin
     */
    public function __construct($context, $plugin)
    {
        $this->setContext($context);
        $this->setPlugin($plugin);
    }

    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the root element name
     *
     * @return string
     */
    public function getRootElementName()
    {
        return 'resource';
    }

    /**
     * Get the namespace URN
     *
     * @return string
     */
    public function getNamespace()
    {
        return DATACITE_XMLNS;
    }

    /**
     * Get the schema instance URN
     *
     * @return string
     */
    public function getXmlSchemaInstance()
    {
        return DATACITE_XMLNS_XSI;
    }

    /**
     * Get the schema version
     *
     * @return string
     */
    public function getXmlSchemaVersion()
    {
        return DATACITE_XSI_SCHEMAVERSION;
    }

    /**
     * Get the schema location URL
     *
     * @return string
     */
    public function getXmlSchemaLocation()
    {
        return DATACITE_XSI_SCHEMALOCATION;
    }

    /**
     * Get the schema filename.
     *
     * @return string
     */
    public function getSchemaFilename()
    {
        return $this->getXmlSchemaLocation();
    }

    //
    // Getter/setters
    //
    /**
     * Set the import/export context.
     *
     * @param \PKP\context\Context $context
     */
    public function setContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Get the import/export context.
     *
     * @return \PKP\context\Context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Set the import/export plugin.
     *
     * @param \PKP\plugins\Plugin $plugin
     */
    public function setPlugin($plugin)
    {
        $this->_plugin = $plugin;
    }

    /**
     * Get the import/export plugin.
     *
     * @return \PKP\plugins\ImportExportPlugin
     */
    public function getPlugin()
    {
        return $this->_plugin;
    }
}
