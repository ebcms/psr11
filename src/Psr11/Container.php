<?php declare (strict_types = 1);

namespace Ebcms\Psr11;

use Ebcms\Psr11\Exception\NotFoundException;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class Container implements ContainerInterface
{
    private $items = [];
    private $caches = [];
    private $shares = [];

    public function has($id): bool
    {
        if (array_key_exists($id, $this->items)) {
            return true;
        }
        if (class_exists($id)) {
            $reflector = new ReflectionClass($id);
            if ($reflector->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

    public function get($id)
    {
        if (in_array($id, $this->shares)) {
            if (array_key_exists($id, $this->caches)) {
                return $this->caches[$id];
            }
        }
        if (array_key_exists($id, $this->items)) {
            $result = call_user_func($this->items[$id]);
            if (in_array($id, $this->shares)) {
                $this->caches[$id] = $result;
            }
            return $result;
        }
        if (class_exists($id)) {
            $reflector = new ReflectionClass($id);
            if ($reflector->isInstantiable()) {
                $construct = $reflector->getConstructor();
                $result = $reflector->newInstanceArgs($construct === null ? [] : $this->reflectArguments($construct));
                if (in_array($id, $this->shares)) {
                    $this->caches[$id] = $result;
                }
                return $result;
            }
        }
        throw new NotFoundException(
            sprintf('Alias (%s) is not an existing class and therefore cannot be resolved', $id)
        );
    }

    public function set(string $id, callable $callback, bool $share = true): self
    {
        $this->items[$id] = $callback;
        if ($share) {
            $this->share($id);
        }
        return $this;
    }

    public function share($id): self
    {
        if (!in_array($id, $this->shares)) {
            $this->shares[] = $id;
        }
        return $this;
    }

    private function reflectArguments(ReflectionMethod $method): array
    {
        return array_map(function (ReflectionParameter $param) use ($method) {

            $class = $param->getClass();
            if ($class !== null) {
                if ($this->has($class->getName())) {
                    $result = $this->get($class->getName());
                    $class_name = $class->getName();
                    if ($result instanceof $class_name) {
                        return $result;
                    }
                }
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new Exception(sprintf(
                'Unable to resolve a value for parameter (%s) in the %s::%s',
                $param->getName(),
                $method->getDeclaringClass()->getName(),
                $method->getName()
            ));
        }, $method->getParameters());
    }
}
