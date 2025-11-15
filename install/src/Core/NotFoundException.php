<?php
/**
 * Not Found Exception
 *
 * Thrown when a requested service or resource is not found in the container.
 * Implements PSR-11 NotFoundExceptionInterface.
 *
 * @package EvolutionCMS\Install\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

use Exception;

/**
 * Class NotFoundException
 *
 * Exception thrown when a service identifier is not found in the container.
 *
 * @package EvolutionCMS\Install\Core
 */
class NotFoundException extends Exception
{
}
