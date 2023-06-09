<?php
/**
 * @defgroup tests Tests
 * Tests and test framework for unit and integration tests.
 */

/**
 * @file tests/PKPTestCase.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTestCase
 *
 * @ingroup tests
 *
 * @brief Class that implements functionality common to all PKP unit test cases.
 */

namespace PKP\tests;

use APP\core\Application;
use APP\core\PageRouter;
use Mockery;
use PHPUnit\Framework\TestCase;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\db\DAORegistry;

abstract class PKPTestCase extends TestCase
{
    private array $daoBackup = [];
    private array $registryBackup = [];
    private array $containerBackup = [];
    private array $mockedRegistryKeys = [];

    /**
     * Override this method if you want to backup/restore
     * DAOs before/after the test.
     *
     * @return array A list of DAO names to backup and restore.
     */
    protected function getMockedDAOs(): array
    {
        return [];
    }

    /**
     * Override this method if you want to backup/restore
     * registry entries before/after the test.
     *
     * @return array A list of registry keys to backup and restore.
     */
    protected function getMockedRegistryKeys(): array
    {
        return $this->mockedRegistryKeys;
    }

    /**
     * Override this method if you want to backup/restore
     * singleton entries from the container before/after the test.
     *
     * @return string[] A list of container classes/identifiers to backup and restore.
     */
    protected function getMockedContainerKeys(): array
    {
        return [];
    }

    /**
     * @copydoc TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setBackupGlobals(true);

        // Rather than using "include_once()", ADOdb uses
        // a global variable to maintain the information
        // whether its library has been included before (wtf!).
        // This causes problems with PHPUnit as PHPUnit will
        // delete all global state between two consecutive
        // tests to isolate tests from each other.
        if (function_exists('_array_change_key_case')) {
            global $ADODB_INCLUDED_LIB;
            $ADODB_INCLUDED_LIB = 1;
        }
        Config::setConfigFileName(Core::getBaseDir() . '/config.inc.php');

        // Backup DAOs.
        foreach ($this->getMockedDAOs() as $mockedDao) {
            $this->daoBackup[$mockedDao] = DAORegistry::getDAO($mockedDao);
        }

        // Backup registry keys.
        foreach ($this->getMockedRegistryKeys() as $mockedRegistryKey) {
            $this->registryBackup[$mockedRegistryKey] = Registry::get($mockedRegistryKey);
        }

        // Backup container keys.
        foreach ($this->getMockedContainerKeys() as $mockedContainer) {
            $this->containerBackup[$mockedContainer] = app($mockedContainer);
        }
    }

    /**
     * @copydoc TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        // Restore container keys.
        foreach ($this->getMockedContainerKeys() as $mockedContainer) {
            app()->instance($mockedContainer, $this->containerBackup[$mockedContainer]);
        }

        // Restore registry keys.
        foreach ($this->getMockedRegistryKeys() as $mockedRegistryKey) {
            Registry::set($mockedRegistryKey, $this->registryBackup[$mockedRegistryKey]);
        }

        // Restore DAOs.
        foreach ($this->getMockedDAOs() as $mockedDao) {
            DAORegistry::registerDAO($mockedDao, $this->daoBackup[$mockedDao]);
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * @copydoc TestCase::getActualOutput()
     */
    public function getActualOutput(): string
    {
        // We do not want to see output.
        return '';
    }


    //
    // Protected helper methods
    //
    /**
     * Set a non-default test configuration
     *
     * @param string $config the id of the configuration to use
     * @param string $configPath (optional) where to find the config file, default: 'config'
     */
    protected function setTestConfiguration($config, $configPath = 'config')
    {
        // Get the configuration file belonging to
        // this test configuration.
        $configFile = $this->getConfigFile($config, $configPath);

        // Avoid unnecessary configuration switches.
        if (Config::getConfigFileName() != $configFile) {
            // Switch the configuration file
            Config::setConfigFileName($configFile);
        }
    }

    /**
     * Mock a web request.
     *
     * For correct timing you have to call this method
     * in the setUp() method of a test after calling
     * parent::setUp() or in a test method. You can also
     * call this method as many times as necessary from
     * within your test and you're guaranteed to receive
     * a fresh request whenever you call it.
     *
     * And make sure that you merge any additional mocked
     * registry keys with the ones returned from this class.
     *
     * @param string $path
     * @param int $userId
     *
     * @return Request
     */
    protected function mockRequest($path = 'index/test-page/test-op', $userId = null)
    {
        // Back up the default request.
        if (!isset($this->registryBackup['request'])) {
            $this->mockedRegistryKeys[] = 'request';
            $this->registryBackup['request'] = Registry::get('request');
        }

        // Create a test request.
        Registry::delete('request');
        $application = Application::get();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = $path;
        $request = $application->getRequest();

        // Test router.
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);

        // Test user.
        $session = $request->getSession();
        $session->setUserId($userId);

        return $request;
    }


    //
    // Private helper methods
    //
    /**
     * Resolves the configuration id to a configuration
     * file
     *
     * @param string $config
     *
     * @return string the resolved configuration file name
     */
    private function getConfigFile($config, $configPath = 'config')
    {
        // Build the config file name.
        return './lib/pkp/tests/' . $configPath . '/config.' . $config . '.inc.php';
    }

    /**
     * Creates a regular expression to match the translation, and replaces params by a generic matcher
     * e.g. The following translation "start {$param} end" would end up as "/^start .*? end$/
     */
    protected function localeToRegExp(string $translation): string
    {
        $pieces = preg_split('/\{\$[^}]+\}/', $translation);
        $escapedPieces = array_map(fn ($piece) => preg_quote($piece, '/'), $pieces);
        return '/^' . implode('.*?', $escapedPieces) . '$/u';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias(PKPTestCase::class, 'PKPTestCase');
}
