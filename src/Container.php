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
    private $item_list = [];
    private $item_cache_list = [];
    private $no_share_list = [];
    private $callback_list = [];

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->item_list)) {
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
        if (array_key_exists($id, $this->item_cache_list)) {
            return $this->item_cache_list[$id];
        }
        if (array_key_exists($id, $this->item_list)) {
            $result = call_user_func($this->item_list[$id]);
            if (isset($this->callback_list[$id])) {
                call_user_func($this->callback_list[$id], $result);
            }
            if (!in_array($id, $this->no_share_list)) {
                $this->item_cache_list[$id] = $result;
            }
            return $result;
        }
        if ($reflector = $this->getReflectionClass($id)) {
            if ($reflector->isInstantiable()) {
                $construct = $reflector->getConstructor();
                $result = $reflector->newInstanceArgs($construct === null ? [] : $this->reflectArguments($construct));
                if (isset($this->callback_list[$id])) {
                    call_user_func($this->callback_list[$id], $result);
                }
                if (!in_array($id, $this->no_share_list)) {
                    $this->item_cache_list[$id] = $result;
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
        $this->item_list[$id] = $callback;
        unset($this->item_cache_list[$id]);
        if ($no_share) {
            $this->noShare($id);
        }
        return $this;
    }

    public function noShare(string $id): self
    {
        unset($this->item_cache_list[$id]);
        if (!in_array($id, $this->no_share_list)) {
            $this->no_share_list[] = $id;
        }
        return $this;
    }

    public function callback(string $id, callable $callback)
    {
        $this->callback_list[$id] = $callback;
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

            if ($param->isOptional()) {
                return;
            }

            throw new OutOfBoundsException(sprintf(
                'Unable to resolve a value for parameter (%s $%s) in [%s] method:[%s]',
                $param->getType()->getName(),
                $param->getName(),
                $method->getFileName(),
                $method->getName(),
            ));
        }, $method->getParameters());
    }
}
