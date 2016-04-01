<?php

/*
 * CKFinder
 * ========
 * http://cksource.com/ckfinder
 * Copyright (C) 2007-2016, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the MIT License.
 * Please read the LICENSE.md file before using, installing, copying,
 * modifying or distribute this file or part of its contents.
 */

namespace CKSource\CKFinder\Plugin\DatabaseAdapter;

// This line may not be needed if the autoloader can load from the CKFinder plugins directory.
require_once __DIR__ . '/PDOAdapter.php';

use CKSource\CKFinder\CKFinder;
use CKSource\CKFinder\Plugin\PluginInterface;
use \PDO;

class DatabaseAdapter implements PluginInterface
{
    /**
     * Injects the DI container to the plugin.
     *
     * @param CKFinder $app
     */
    public function setContainer(CKFinder $app)
    {
        $backendFactory = $app->getBackendFactory();

        // Register a backend adapter named "database".
        $backendFactory->registerAdapter('database', function($backendConfig) use ($backendFactory) {
            // Create an instance of PDOAdapter using backend options defined in the CKFinder configuration.
            $pdo = new PDO($backendConfig['dsn'], $backendConfig['username'], $backendConfig['password']);
            $adapter = new PDOAdapter($pdo, $backendConfig['tableName']);

            // Create and return a CKFinder backend instance.
            return $backendFactory->createBackend($backendConfig, $adapter);
        });
    }

    /**
     * This plugin is a bit specific, as it only uses backend configuration options.
     * This method can be ignored, and simply return an empty array.
     */
    public function getDefaultConfig()
    {
        return [];
    }
}