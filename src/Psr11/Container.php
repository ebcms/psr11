<?php declare (strict_types = 1);

namespace Ebcms\Psr11;

use Ebcms\Psr11\Exception\ContainerException;
use Ebcms\Psr11\Exception\NotFoundException;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

class Container implements ContainerInterface
{
    protected $items = [];

    public function has($id): bool
    {
        if (array_key_exists($id, $this->items)) {
            return true;
        }
        return class_exists($id);
    }

    public function get($id, array $args = [])
    {
        if (array_key_exists($id, $this->items)) {
            try {
                return call_user_func($this->items[$id], $args);
            } catch (Throwable $th) {
                throw new ContainerException($th->getMessage());
            }
        }
        if (class_exists($id)) {
            try {
                $reflector = new ReflectionClass($id);
                $construct = $reflector->getConstructor();
                return $reflector->newInstanceArgs($construct === null ? [] : $this->reflectArguments($construct, $args));
            } catch (Throwable $th) {
                throw new ContainerException($th->getMessage());
            }
        }
        throw new NotFoundException(
            sprintf('Alias (%s) is not an existing class and therefore cannot be resolved', $id)
        );
    }

    public function set(string $id, callable $callback): self
    {
        $this->items[$id] = $callback;
        return $this;
    }

    private function reflectArguments(ReflectionMethod $method, array $args = []): array
    {
        return array_map(function (ReflectionParameter $param) use ($method, $args) {
            $name = $param->getName();
            if (array_key_exists($name, $args)) {
                return $args[$name];
            }

            $class = $param->getClass();
            if ($class !== null) {
                if ($class->isInstantiable()) {
                    return $this->get($class->getName());
                }
                if ($class->isInterface() || $class->isAbstract()) {
                    if (array_key_exists($class->getName(), $this->items)) {
                        $result = $this->get($class->getName());
                        $class_name = $class->getName();
                        if ($result instanceof $class_name) {
                            return $result;
                        }
                    }
                }
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new Exception(sprintf(
                'Unable to resolve a value for parameter (%s) in the %s::%s',
                $name,
                $method->getDeclaringClass()->getName(),
                $method->getName()
            ));
        }, $method->getParameters());
    }
}
