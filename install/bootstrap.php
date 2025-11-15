<?php
/**
 * Evolution CMS Installer Bootstrap
 *
 * This file initializes the new installer architecture.
 * Currently, it coexists with the existing installer and will
 * gradually replace it during the refactoring phases.
 *
 * @package EvolutionCMS\Install
 * @version Phase 1 - Foundation
 */

declare(strict_types=1);

// Error reporting for development
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

// Include PSR-4 autoloader
require_once __DIR__ . '/autoload.php';

// Define paths (temporary - will be moved to Core\Application)
if (!defined('MODX_SETUP_PATH')) {
    define('MODX_SETUP_PATH', __DIR__ . '/');
}

if (!defined('MODX_BASE_PATH')) {
    include __DIR__ . '/../define-path.php';
    define('MODX_BASE_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
}

// For now, include the existing installer
// This will be replaced with new Application class in future phases
require_once __DIR__ . '/index.php';
