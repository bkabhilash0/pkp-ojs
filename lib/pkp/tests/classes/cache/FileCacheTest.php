<?php

/**
 * @file tests/classes/cache/FileCacheTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileCacheTest
 *
 * @ingroup tests_classes_cache
 *
 * @see Config
 *
 * @brief Tests for the FileCache class.
 */

namespace PKP\tests\classes\cache;

use PKP\cache\CacheManager;
use PKP\tests\PKPTestCase;

class FileCacheTest extends PKPTestCase
{
    /** @var CacheManager $cacheManager */
    public $cacheManager;

    /** @var int $cacheMisses */
    public $cacheMisses;

    public $testCacheContents = [
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
    ];

    /**
     * @covers \PKP\cache\FileCache::get
     */
    public function testGetCache()
    {
        // Get the file cache.
        $fileCache = $this->getCache();

        // No cache misses should be registered.
        self::assertTrue($this->cacheMisses == 0);

        // The cache has just been flushed by setUp. Try a get.
        $val1 = $fileCache->get(1);

        // Make sure the returned value was correct
        self::assertTrue($val1 == 'one');

        // Make sure we registered one cache miss
        self::assertTrue($this->cacheMisses == 1);

        // Try another get
        $val2 = $fileCache->get(2);

        // Make sure the value was correct
        self::assertTrue($val2 == 'two');

        // Make sure we didn't have to register another cache miss
        self::assertTrue($this->cacheMisses == 1);
    }

    /**
     * @covers \PKP\cache\FileCache::get
     */
    public function testCacheMiss()
    {
        // Get the file cache.
        $fileCache = $this->getCache();

        // Try to get an item that's not in the cache
        $val1 = $fileCache->get(-1);

        // Make sure we registered one cache miss
        self::assertTrue($val1 == null);
        self::assertTrue($this->cacheMisses == 1);

        // Try another get of the same item
        $val2 = $fileCache->get(-1);

        // Check to see that we got it without a second miss
        self::assertTrue($val2 == null);

        // When an item isn't found, the cache is reset
        self::assertTrue($this->cacheMisses == 2);
    }

    //
    // Helper functions
    //
    public function _cacheMiss($cache, $id)
    {
        $this->cacheMisses++;
        $cache->setEntireCache($this->testCacheContents);
        if (!isset($this->testCacheContents[$id])) {
            $cache->setCache($id, null);
            return null;
        }
        return $this->testCacheContents[$id];
    }

    //
    // Protected methods.
    //
    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = CacheManager::getManager();
        $this->cacheMisses = 0;

        if (!is_writable($this->cacheManager->getFileCachePath())) {
            $this->markTestSkipped('File cache path not writable.');
        }
        $this->cacheManager->flush();
    }

    /**
     * Return a test cache.
     */
    protected function getCache()
    {
        return $this->cacheManager->getFileCache('testCache', 0, [$this, '_cacheMiss']);
    }
}
