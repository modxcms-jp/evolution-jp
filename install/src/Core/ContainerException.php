<?php
/**
 * Container Exception
 *
 * Thrown when there's an error during service resolution or container operations.
 * Implements PSR-11 ContainerExceptionInterface.
 *
 * @package EvolutionCMS\Install\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

use Exception;

/**
 * Class ContainerException
 *
 * Exception thrown when the container encounters an error during service resolution.
 *
 * @package EvolutionCMS\Install\Core
 */
class ContainerException extends Exception
{
}
