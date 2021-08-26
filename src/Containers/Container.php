<?php

declare(strict_types=1);

namespace Nordsec\SlimBootstrapBase\Containers;

use InvalidArgumentException;
use Nordsec\SlimBootstrapBase\Containers\Entities\LazyValuePlaceholder;
use ReflectionClass;
use Slim\Container as BaseContainer;

class Container extends BaseContainer
{
    protected $lazyExtendCallables = [];

    protected $autowiringEnabled = false;

    protected $append = [];

    public function isAutowiringEnabled(): bool
    {
        return $this->autowiringEnabled;
    }

    public function setAutowiringEnabled(bool $autowiringEnabled): self
    {
        $this->autowiringEnabled = $autowiringEnabled;

        return $this;
    }

    public function offsetExists($id)
    {
        return parent::offsetExists($id) || $this->canBeAutowired($id);
    }

    public function offsetGet($id)
    {
        if ($this->isAutowiringEnabled() && $this->canBeAutowired($id)) {
            $this[$id] = function () use ($id) {
                return $this->autowire($id);
            };

            return $this[$id];
        }

        $val = parent::offsetGet($id);

        $this->applyAppends($id, $val);

        return $val;
    }

    public function offsetSet($id, $value)
    {
        if ($value instanceof LazyValuePlaceholder) {
            return;
        }

        $serviceWithAppliedCallables = $this->applyLazyExtendedCallables($id, $value);

        parent::offsetSet($id, $serviceWithAppliedCallables);
    }

    public function extend($id, $callable)
    {
        if (!parent::offsetExists($id)) {
            $this->lazyExtendCallables[$id][] = $callable;

            return new LazyValuePlaceholder();
        }

        return parent::extend($id, $callable);
    }

    public function append($id, $callable)
    {
        $this->append[$id][] = static function ($service, $container) use ($callable) {
            $callable($service, $container);
        };
    }

    public function autowire(string $className, array $defaultArguments = [])
    {
        $reflectionInstance = new ReflectionClass($className);
        $reflectionConstructor = $reflectionInstance->getConstructor();
        $parameters = $reflectionConstructor ? $reflectionConstructor->getParameters() : [];

        $constructorArguments = [];

        foreach ($parameters as $key => $parameter) {
            $parameterName = sprintf('$%s', $parameter->getName());
            if (array_key_exists($parameterName, $defaultArguments)) {
                $constructorArguments[] = $defaultArguments[$parameterName];

                continue;
            }

            $reflectionClass = $parameter->getClass();
            $reflectionClassName = $reflectionClass !== null ? $reflectionClass->name : null;

            if ($reflectionClassName !== null) {
                $constructorArgument = array_key_exists($reflectionClassName, $defaultArguments)
                    ? $defaultArguments[$reflectionClassName]
                    : $this->offsetGet($reflectionClassName);

                $constructorArguments[] = $constructorArgument;

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $constructorArguments[] = $parameter->getDefaultValue();

                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Could not autowire argument %s(%d) of type "%s" for class "%s"',
                    $parameterName,
                    $key,
                    $parameter->getType(),
                    $className
                )
            );
        }

        return $reflectionInstance->newInstanceArgs($constructorArguments);
    }

    protected function canBeAutowired(string $className): bool
    {
        $isOffsetMissing = !parent::offsetExists($className);

        return $isOffsetMissing && class_exists($className);
    }

    protected function applyLazyExtendedCallables($id, $value)
    {
        if (empty($this->lazyExtendCallables[$id])) {
            return $value;
        }

        parent::offsetSet($id, $value);

        $lazyExtendedCallables = $this->lazyExtendCallables[$id];
        $this->lazyExtendCallables[$id] = [];

        $resultingValue = $value;
        foreach ($lazyExtendedCallables as $lazyCallable) {
            $resultingValue = parent::extend($id, $lazyCallable);
        }

        return $resultingValue;
    }

    protected function applyAppends($id, $service)
    {
        if (empty($this->append[$id])) {
            return;
        }

        $appends = $this->append[$id];
        unset($this->append[$id]);

        foreach ($appends as $append) {
            $append($service, $this);
        }
    }
}
