<?php
/**
 * Config Exception
 *
 * Thrown when there's an error with configuration operations such as
 * missing required keys, invalid configuration files, or I/O errors.
 *
 * @package EvolutionCMS\Install\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

use Exception;

/**
 * Class ConfigException
 *
 * Exception thrown when configuration operations fail.
 *
 * @package EvolutionCMS\Install\Core
 */
class ConfigException extends Exception
{
}
