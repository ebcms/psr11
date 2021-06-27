<?php

declare(strict_types=1);

namespace Ebcms;

use OutOfBoundsException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class Container implements ContainerInterface
{
    private $items = [];
    private $caches = [];
    private $no_shares = [];

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->items)) {
            return true;
        }
        if ($reflector = $this->getReflectionClass($id)) {
            if ($reflector->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->caches)) {
            return $this->caches[$id];
        }
        if (array_key_exists($id, $this->items)) {
            $result = call_user_func($this->items[$id]);
            if (!in_array($id, $this->no_shares)) {
                $this->caches[$id] = $result;
            }
            return $result;
        }
        if ($reflector = $this->getReflectionClass($id)) {
            if ($reflector->isInstantiable()) {
                $construct = $reflector->getConstructor();
                $result = $reflector->newInstanceArgs($construct === null ? [] : $this->reflectArguments($construct));
                if (!in_array($id, $this->no_shares)) {
                    $this->caches[$id] = $result;
                }
                return $result;
            }
        }
        throw new ContainerNotFoundException(
            sprintf('Alias (%s) is not an existing class and therefore cannot be resolved', $id)
        );
    }

    public function set(string $id, callable $callback, bool $no_share = false): self
    {
        $this->items[$id] = $callback;
        unset($this->caches[$id]);
        if ($no_share) {
            $this->noShare($id);
        }
        return $this;
    }

    public function noShare(string $id): self
    {
        unset($this->caches[$id]);
        if (!in_array($id, $this->no_shares)) {
            $this->no_shares[] = $id;
        }
        return $this;
    }

    private function getReflectionClass(string $id): ?ReflectionClass
    {
        static $reflectors = [];
        if (!isset($reflectors[$id])) {
            if (class_exists($id)) {
                $reflectors[$id] = new ReflectionClass($id);
            } else {
                return null;
            }
        }
        return $reflectors[$id];
    }

    private function reflectArguments(ReflectionMethod $method): array
    {
        return array_map(function (ReflectionParameter $param) use ($method) {

            $type = $param->getType();
            if ($type !== null && !$type->isBuiltin()) {
                if ($this->has($type->getName())) {
                    $result = $this->get($type->getName());
                    $type_name = $type->getName();
                    if ($result instanceof $type_name) {
                        return $result;
                    }
                }
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new OutOfBoundsException(sprintf(
                'Unable to resolve a value for parameter (%s $%s)',
                $param->getType(),
                $param->getName()
            ));
        }, $method->getParameters());
    }
}
