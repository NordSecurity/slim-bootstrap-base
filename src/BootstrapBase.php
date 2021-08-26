<?php

declare(strict_types=1);

namespace Nordsec\SlimBootstrapBase;

use Nordsec\SlimBootstrapBase\Services\ClassNameLoader;
use Nordsec\SlimBootstrapBase\Containers\Container;
use Pimple\ServiceProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Collection;

abstract class BootstrapBase
{
    /**
     * @var App
     */
    protected static $application;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var App
     */
    protected $app;

    /**
     * @var ClassNameLoader
     */
    protected $classNameLoader;

    /**
     * @var string
     */
    protected $applicationPath;

    /**
     * @var string
     */
    protected $srcPath;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @var string
     */
    protected $modulesPath;

    /**
     * @param string $applicationPath
     */
    public function __construct(string $applicationPath)
    {
        $this->container = $this->buildContainerInstance();
        static::$application = $this->app = $this->buildApplicationInstance($this->container);

        $this->classNameLoader = new ClassNameLoader();

        $this->applicationPath = rtrim($applicationPath, '/');
        $this->srcPath = $this->applicationPath . '/src';
        $this->configPath = $this->applicationPath . '/config';
        $this->modulesPath = $this->applicationPath . '/modules';
    }

    protected function buildContainerInstance(): Container
    {
        return new Container();
    }

    protected function buildApplicationInstance(Container $container): App
    {
        return new App($container);
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    public function enableContainerAutowiring(): void
    {
        $this->getContainer()->setAutowiringEnabled(true);
    }

    protected function bootstrap(): void
    {
        $this->loadConfigs();
        $this->loadSettings();
        $this->loadDependencies();
    }

    protected function loadConfigs(): void
    {
        $configFiles = $this->loadDirectoryFiles($this->configPath . '/*.php');

        $config = [];

        foreach ($configFiles as $configFile) {
            $configuration = require $configFile;
            if (!is_array($configuration)) {
                continue;
            }

            $fileNameWithoutExtension = substr($configFile, 0, -strlen('.php'));
            $relativePath = substr($fileNameWithoutExtension, strlen($this->configPath));
            $nestedKeys = explode('/', ltrim($relativePath, '/'));

            $nestedConfiguration = $this->buildNestedConfig($nestedKeys, $configuration);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $config = array_merge_recursive($config, $nestedConfiguration);
        }


        $this->container['config'] = $config;
    }

    protected function buildNestedConfig(array $keys, array $value): array
    {
        if (empty($keys)) {
            return $value;
        }

        $currentKey = array_shift($keys);
        $accumulatedValue[$currentKey] = $this->buildNestedConfig($keys, $value);
        return $accumulatedValue;
    }

    protected function loadSettings(): void
    {
        $defaultSettings = $this->container['settings'];

        if ($defaultSettings instanceof Collection) {
            $defaultSettings = $defaultSettings->all();
        }

        unset($this->container['settings']);

        if (isset($this->container['config']['settings'])) {
            $this->container['settings'] = array_merge($defaultSettings, $this->container['config']['settings']);
        }
    }

    protected function loadDependencies(): void
    {
        if (file_exists($this->srcPath)) {
            $this->loadDependenciesFromPath($this->srcPath . '/Dependencies/*.php');
            $this->loadDependenciesFromPath($this->srcPath . '/Core/*/*/Dependencies/*.php');
        }

        if (file_exists($this->modulesPath)) {
            $this->loadDependenciesFromPath($this->modulesPath . '/*/*/Dependencies/*.php');
            $this->loadDependenciesFromPath($this->modulesPath . '/*/Dependencies/*.php');
        }
    }

    protected function loadDependenciesFromPath(string $path): void
    {
        $dependencyFiles = $this->loadDirectoryFiles($path);

        /** @var ServiceProviderInterface $dependencyProvider */
        foreach ($dependencyFiles as $dependencyFile) {
            $serviceProviderName = $this->classNameLoader->loadFromFile($dependencyFile);
            $dependencyProvider = new $serviceProviderName();
            $dependencyProvider->register($this->container);
        }
    }

    protected function loadDirectoryFiles(string $pattern): ?array
    {
        $files = glob($pattern);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $files = array_merge($files, $this->loadDirectoryFiles($dir . '/' . basename($pattern)));
        }

        return $files;
    }

    public function getApplicationPath(): ?string
    {
        return $this->applicationPath;
    }

    public function getSrcPath(): ?string
    {
        return $this->srcPath;
    }

    public function getConfigPath(): ?string
    {
        return $this->configPath;
    }

    public function getModulesPath(): ?string
    {
        return $this->modulesPath;
    }

    public static function getApplication(): App
    {
        return static::$application;
    }
}

