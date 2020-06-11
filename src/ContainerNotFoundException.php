<?php declare (strict_types = 1);

namespace Ebcms;

use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends ContainerException implements NotFoundExceptionInterface
{

}
