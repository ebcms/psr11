<?php declare (strict_types = 1);

namespace Ebcms\Psr11\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{

}
