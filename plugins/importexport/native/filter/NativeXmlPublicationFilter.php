<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPublicationFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPublicationFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Native XML document to a set of articles.
 */

namespace APP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlPublicationFilter extends \PKP\plugins\importexport\native\filter\NativeXmlPKPPublicationFilter
{
    /**
     * Handle an Article import.
     * The Article must have a valid section in order to be imported
     *
     * @param DOMElement $node
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $sectionAbbrev = $node->getAttribute('section_ref');
        if ($sectionAbbrev !== '') {
            $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->filterByAbbrevs([$sectionAbbrev])->getMany()->first();
            if (!$section) {
                $deployment->addError(Application::ASSOC_TYPE_SUBMISSION, null, __('plugins.importexport.native.error.unknownSection', ['param' => $sectionAbbrev]));
            } else {
                return parent::handleElement($node);
            }
        }
    }

    /**
     * Populate the submission object from the node, checking first for a valid section and published_date/issue relationship
     *
     * @param Publication $publication
     * @param DOMElement $node
     *
     * @return Publication
     */
    public function populateObject($publication, $node)
    {
        /** @var NativeImportExportDeployment */
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $sectionAbbrev = $node->getAttribute('section_ref');
        if ($sectionAbbrev !== '') {
            $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->filterByAbbrevs([$sectionAbbrev])->getMany()->first();
            if (!$section) {
                $deployment->addError(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.error.unknownSection', ['param' => $sectionAbbrev]));
            } else {
                $publication->setData('sectionId', $section->getId());
            }
        }
        // check if publication is related to an issue, but has no published date
        $datePublished = $node->getAttribute('date_published');
        $issue = $deployment->getIssue();
        $issue_identification = $node->getElementsByTagName('issue_identification');
        if (!$datePublished && ($issue || $issue_identification->length)) {
            $titleNodes = $node->getElementsByTagName('title');
            $deployment->addWarning(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.import.error.publishedDateMissing', ['articleTitle' => $titleNodes->item(0)->textContent]));
        }

        $this->populatePublishedPublication($publication, $node);

        return parent::populateObject($publication, $node);
    }

    /**
     * Handle an element whose parent is the submission element.
     *
     * @param DOMElement $n
     * @param Publication $publication
     */
    public function handleChildElement($n, $publication)
    {
        switch ($n->tagName) {
            case 'article_galley':
                $this->parseArticleGalley($n, $publication);
                break;
            case 'issue_identification':
                // do nothing, because this is done in populatePublishedSubmission
                break;
            case 'pages':
                $publication->setData('pages', $n->textContent);
                break;
            case 'covers':
                $nativeFilterHelper = new NativeFilterHelper();
                $nativeFilterHelper->parsePublicationCovers($this, $n, $publication);
                break;
            default:
                parent::handleChildElement($n, $publication);
        }
    }

    /**
     * Get the import filter for a given element.
     *
     * @param string $elementName Name of XML element
     *
     * @return Filter
     */
    public function getImportFilter($elementName)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();
        switch ($elementName) {
            case 'article_galley':
                $importClass = 'ArticleGalley';
                break;
            default:
                $importClass = null; // Suppress scrutinizer warn
                $deployment->addWarning(Application::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $elementName]));
        }
        // Caps on class name for consistency with imports, whose filter
        // group names are generated implicitly.
        $currentFilter = PKPImportExportFilter::getFilter('native-xml=>' . $importClass, $deployment);
        return $currentFilter;
    }

    /**
     * Parse an article galley and add it to the publication.
     *
     * @param DOMElement $n
     * @param Publication $publication
     */
    public function parseArticleGalley($n, $publication)
    {
        return $this->importWithXMLNode($n);
    }

    /**
     * Class-specific methods for published publication.
     *
     * @param Publication $publication
     * @param DOMElement $node
     *
     * @return Publication
     */
    public function populatePublishedPublication($publication, $node)
    {
        /** @var NativeImportExportDeployment */
        $deployment = $this->getDeployment();

        $context = $deployment->getContext();
        $issue = $deployment->getIssue();

        if (empty($issue)) {
            $issueIdentificationNodes = $node->getElementsByTagName('issue_identification');

            if ($issueIdentificationNodes->length != 1) {
                $titleNodes = $node->getElementsByTagName('title');
                $deployment->addError(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.import.error.issueIdentificationMissing', ['articleTitle' => $titleNodes->item(0)->textContent]));
            } else {
                $issueIdentificationNode = $issueIdentificationNodes->item(0);
                $issue = $this->parseIssueIdentification($publication, $issueIdentificationNode);
            }
        }

        if ($issue) {
            $publication->setData('issueId', $issue->getId());
        }

        return $publication;
    }

    /**
     * Get the issue from the given identification.
     *
     * @param DOMElement $node
     *
     * @return Issue
     */
    public function parseIssueIdentification($publication, $node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $vol = $num = $year = null;
        $titles = [];
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                switch ($n->tagName) {
                    case 'volume':
                        $vol = $n->textContent;
                        break;
                    case 'number':
                        $num = $n->textContent;
                        break;
                    case 'year':
                        $year = $n->textContent;
                        break;
                    case 'title':
                        [$locale, $value] = $this->parseLocalizedContent($n);
                        if (empty($locale)) {
                            $locale = $context->getPrimaryLocale();
                        }
                        $titles[$locale] = $value;
                        break;
                    default:
                        $deployment->addWarning(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $n->tagName]));
                }
            }
        }
        $issue = null;

        $collector = Repo::issue()->getCollector()
            ->filterByContextIds([$context->getId()]);
        if ($vol !== null) {
            $collector->filterByVolumes([$vol]);
        }
        if ($num !== null) {
            $collector->filterByNumbers([$num]);
        }
        if ($year !== null) {
            $collector->filterByYears([$year]);
        }
        if (!empty($titles)) {
            $collector->filterByTitles($titles);
        }
        $issuesIdsByIdentification = $collector->getIds();

        if ($issuesIdsByIdentification->count() != 1) {
            $deployment->addError(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.import.error.issueIdentificationMatch', ['issueIdentification' => $node->ownerDocument->saveXML($node)]));
        } else {
            $issue = Repo::issue()->get($issuesIdsByIdentification->first());
        }

        if (!isset($issue)) {
            $issue = Repo::issue()->newDataObject();

            $issue->setVolume($vol);
            $issue->setNumber($num);
            $issue->setYear($year);
            $issue->setShowVolume(1);
            $issue->setShowNumber(1);
            $issue->setShowYear(1);
            $issue->setShowTitle(0);
            $issue->setPublished(0);
            $issue->setAccessStatus(0);
            $issue->setJournalId($context->getId());
            $issue->setTitle($titles, null);

            $issueId = Repo::issue()->add($issue);

            $issue->setId($issueId);
        }

        return $issue;
    }
}
