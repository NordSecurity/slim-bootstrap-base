# Slim bootstrap

This package allows quick and easy project bootstrap using slim micro-framework.
## Dependencies

- Composer
- PHP >= 7.2

## Example usage

### Partial auto wiring
```PHP
$container[SomeClass::class] = function (Container $container) {
    $configuration = $container['config']['some_configuration'];
    $dependentClass = new DependentClass();

    return $container->autowire(
        SomeClass::class,
        [
            '$configuration' => $configuration,
            DependentClass::class => $dependentClass,
        ]
    );
};
```

### Append dependencies after service is created

```PHP
$container->append(
    SomeClass::class,
    function (SomeClass $someClass, Container $container) {
        $someClass->setOtherClass($container[OtherClass::class]);
    }
);
```
