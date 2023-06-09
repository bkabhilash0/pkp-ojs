<?php

/**
 * @file tests/plugins/importexport/PubObjectCacheTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubObjectCacheTest
 *
 * @ingroup tests_plugins_importexport
 *
 * @see PubObjectCacheTest
 *
 * @brief Test class for PubObjectCache.
 *
 * NB: This test is not in the medra or datacite package as the class
 * is used symlinked in both plug-ins.
 */

namespace APP\tests\plugins\importexport;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\PubObjectCache;
use APP\submission\Submission;
use PKP\tests\PKPTestCase;

class PubObjectCacheTest extends PKPTestCase
{
    /**
     * @covers PubObjectCache
     */
    public function testAddIssue()
    {
        $nullVar = null;
        $cache = new PubObjectCache();

        $issue = new Issue();
        $issue->setId('1');

        self::assertFalse($cache->isCached('issues', $issue->getId()));
        $cache->add($issue, $nullVar);
        self::assertTrue($cache->isCached('issues', $issue->getId()));

        $retrievedIssue = $cache->get('issues', $issue->getId());
        self::assertEquals($issue, $retrievedIssue);
    }

    /**
     * @covers PubObjectCache
     */
    public function testAddArticle()
    {
        $nullVar = null;
        $cache = new PubObjectCache();

        $article = new Submission();
        $article->setId('2');
        $article->setIssueId('1');

        self::assertFalse($cache->isCached('articles', $article->getId()));
        self::assertFalse($cache->isCached('articlesByIssue', $article->getCurrentPublication()->getData('issueId')));
        self::assertFalse($cache->isCached('articlesByIssue', $article->getCurrentPublication()->getData('issueId'), $article->getId()));
        $cache->add($article, $nullVar);
        self::assertTrue($cache->isCached('articles', $article->getId()));
        self::assertFalse($cache->isCached('articlesByIssue', $article->getCurrentPublication()->getData('issueId')));
        self::assertTrue($cache->isCached('articlesByIssue', $article->getCurrentPublication()->getData('issueId'), $article->getId()));

        $retrievedArticle = $cache->get('articles', $article->getId());
        self::assertEquals($article, $retrievedArticle);
    }


    /**
     * @covers PubObjectCache
     */
    public function testAddGalley()
    {
        $nullVar = null;
        $cache = new PubObjectCache();

        $article = new Submission();
        $article->setId('2');
        $article->setIssueId('1');

        $articleGalley = Repo::galley()->newDataObject();
        $articleGalley->setId('3');
        $articleGalley->setSubmissionId($article->getId());

        self::assertFalse($cache->isCached('galleys', $articleGalley->getId()));
        self::assertFalse($cache->isCached('galleysByArticle', $article->getId()));
        self::assertFalse($cache->isCached('galleysByArticle', $article->getId(), $articleGalley->getId()));
        self::assertFalse($cache->isCached('galleysByIssue', $article->getCurrentPublication()->getData('issueId')));
        self::assertFalse($cache->isCached('galleysByIssue', $article->getCurrentPublication()->getData('issueId'), $articleGalley->getId()));
        $cache->add($articleGalley, $article);
        self::assertTrue($cache->isCached('galleys', $articleGalley->getId()));
        self::assertFalse($cache->isCached('galleysByArticle', $article->getId()));
        self::assertTrue($cache->isCached('galleysByArticle', $article->getId(), $articleGalley->getId()));
        self::assertFalse($cache->isCached('galleysByIssue', $article->getCurrentPublication()->getData('issueId')));
        self::assertTrue($cache->isCached('galleysByIssue', $article->getCurrentPublication()->getData('issueId'), $articleGalley->getId()));

        $retrievedArticleGalley1 = $cache->get('galleys', $articleGalley->getId());
        self::assertEquals($articleGalley, $retrievedArticleGalley1);

        $retrievedArticleGalley2 = $cache->get('galleysByIssue', $article->getCurrentPublication()->getData('issueId'), $articleGalley->getId());
        self::assertEquals($retrievedArticleGalley1, $retrievedArticleGalley2);

        $cache->markComplete('galleysByArticle', $article->getId());
        self::assertTrue($cache->isCached('galleysByArticle', $article->getId()));
        self::assertFalse($cache->isCached('galleysByIssue', $article->getCurrentPublication()->getData('issueId')));
    }

    /**
     * @covers PubObjectCache
     */
    public function testAddSeveralGalleys()
    {
        $nullVar = null;
        $cache = new PubObjectCache();

        $article = new Submission();
        $article->setId('2');
        $article->setIssueId('1');

        $articleGalley1 = Repo::galley()->newDataObject();
        $articleGalley1->setId('3');
        $articleGalley1->setSubmissionId($article->getId());

        $articleGalley2 = Repo::galley()->newDataObject();
        $articleGalley2->setId('4');
        $articleGalley2->setSubmissionId($article->getId());

        // Add galleys in the wrong order.
        $cache->add($articleGalley2, $article);
        $cache->add($articleGalley1, $article);

        $cache->markComplete('galleysByArticle', $article->getId());

        // Retrieve them in the right order.
        $retrievedGalleys = $cache->get('galleysByArticle', $article->getId());
        $expectedGalleys = [
            3 => $articleGalley1,
            4 => $articleGalley2
        ];
        self::assertEquals($expectedGalleys, $retrievedGalleys);

        // And they should still be cached.
        self::assertTrue($cache->isCached('galleysByArticle', $article->getId()));
    }
}
