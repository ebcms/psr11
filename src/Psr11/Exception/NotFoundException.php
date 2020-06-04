<?php declare (strict_types = 1);

namespace Ebcms\Psr11\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{

}
