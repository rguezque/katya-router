<?php

declare(strict_types=1);

namespace rguezque\Database;

use rguezque\Contract\ModelInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;

final class ModelRepository
{
    /**
     * The shared DB connection object.
     *
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * Flag for share a unique model instance for each.
     *
     * @var bool
     */
    private bool $share_instances;

    /**
     * ReflectionClass of registered models.
     *
     * @var array<class-string<ModelInterface>, ReflectionClass<ModelInterface>>
     */
    private array $models = [];

    /**
     * Optional shared instances.
     *
     * @var array<class-string<ModelInterface>, ModelInterface>
     */
    private array $shared_instances = [];

    /**
     * Initialize the class.
     *
     * @param ConnectionInterface $connection A DB connection object.
     * @param bool $share_instances Defines whether the repository will only return one shared instance of each model it generates.
     */
    public function __construct(ConnectionInterface $connection, bool $share_instances = false)
    {
        $this->connection = $connection;
        $this->share_instances = $share_instances;
    }

    /**
     * Register a model using its FQCN (Fully Qualified Class Name).
     *
     * @param class-string $model_class
     * @return ModelRepository
     * @throws InvalidArgumentException
     */
    public function register(string $model_class): ModelRepository
    {
        $model_class = $this->normalizeClassName($model_class);

        if (!class_exists($model_class)) {
            throw new InvalidArgumentException(
                sprintf('The class "%s" does not exist or cannot be loaded.', $model_class)
            );
        }

        $reflection = new ReflectionClass($model_class);

        if ($reflection->isInterface() || $reflection->isAbstract()) {
            throw new InvalidArgumentException(
                sprintf('"%s" cannot be instantiated because it is abstract or an interface.', $model_class)
            );
        }

        if (!$reflection->implementsInterface(ModelInterface::class)) {
            throw new InvalidArgumentException(
                sprintf('"%s" must implement "%s".', $model_class, ModelInterface::class)
            );
        }

        if (!$this->constructorAcceptsConnectionInterface($reflection)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The constructor of "%s" must be public, receive "%s" as the first parameter, '
                        . 'and any additional parameters must have default values.',
                    $model_class,
                    ConnectionInterface::class
                )
            );
        }

        $this->models[$model_class] = $reflection;

        return $this;
    }

    /**
     * Register several models.
     *
     * @param iterable<class-string> $model_classes An array with FQCN (Fully Qualified Class Name) to register.
     * @return ModelRepository
     */
    public function registerMany(iterable $model_classes): ModelRepository
    {
        foreach ($model_classes as $model_class) {
            $this->register($model_class);
        }

        return $this;
    }

    /**
     * Unregister a previously registered model.
     *
     * Also removes any shared instance associated with the model.
     *
     * @param class-string $model_class The FQCN to unregister.
     * @return ModelRepository
     */
    public function unregister(string $model_class): ModelRepository
    {
        $model_class = $this->normalizeClassName($model_class);
        unset($this->models[$model_class], $this->shared_instances[$model_class]);

        return $this;
    }

    /**
     * Return `true` if a model is registered, `false` otherwise.
     *
     * @param class-string $model_class The FQCN (Fully Qualified Class Name) to search.
     * @return bool
     */
    public function has(string $model_class): bool
    {
        $model_class = $this->normalizeClassName($model_class);

        return isset($this->models[$model_class]);
    }

    /**
     * Retrieves an instance of the model with the injected connection.
     *
     * @template T of ModelInterface
     * @param class-string<T> $model_class The FQCN (Fully Qualified Class Name).
     * @return T
     * @throws RuntimeException If the model is not registered.
     */
    public function get(string $model_class): ModelInterface
    {
        $model_class = $this->normalizeClassName($model_class);

        if (!$this->has($model_class)) {
            throw new RuntimeException(
                sprintf('Model "%s" is not registered in the ModelRepository.', $model_class)
            );
        }

        if ($this->share_instances && isset($this->shared_instances[$model_class])) {
            /** @var T */
            return $this->shared_instances[$model_class];
        }

        /** @var ReflectionClass<T> $reflection */
        $reflection = $this->models[$model_class];
        /** @var T $model */
        $model = $reflection->newInstance($this->connection);

        if ($this->share_instances) {
            $this->shared_instances[$model_class] = $model;
        }

        return $model;
    }

    /**
     * Get the list of registered model class names.
     *
     * @return array<class-string<ModelInterface>>
     */
    public function getRegisteredModels(): array
    {
        return array_keys($this->models);
    }

    /**
     * Clear all shared instances, forcing the repository to create new instances
     * on subsequent `get()` calls.
     *
     * @return ModelRepository
     */
    public function clearSharedInstances(): ModelRepository
    {
        $this->shared_instances = [];

        return $this;
    }

    /**
     * Normalize a class name.
     *
     * @param string $class_name The FQCN (Fully Qualified Class Name) to normalize.
     */
    private function normalizeClassName(string $class_name): string
    {
        return ltrim($class_name, '\\');
    }

    /**
     * Return `true` if a class constructor accepts a `ConnectionInterface` object
     * as its first parameter and any additional parameters have default values.
     *
     * @param ReflectionClass $reflection The reflection of the class.
     * @return bool
     */
    private function constructorAcceptsConnectionInterface(ReflectionClass $reflection): bool
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null || !$constructor->isPublic()) {
            return false;
        }

        $parameters = $constructor->getParameters();
        if ($parameters === []) {
            return false;
        }

        $type = $parameters[0]->getType();
        if (
            !$type instanceof ReflectionNamedType
            || $type->isBuiltin()
            || $type->getName() !== ConnectionInterface::class
        ) {
            return false;
        }

        // Verify that any additional parameters are optional (have default values)
        $count = count($parameters);
        for ($i = 1; $i < $count; $i++) {
            if (!$parameters[$i]->isOptional()) {
                return false;
            }
        }

        return true;
    }
}
