<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class for every business rule violation raised by PosService.
 *
 * Extends RuntimeException on purpose: PosController already catches
 * \RuntimeException and turns it into a 422 JSON response, so the web POS
 * keeps behaving exactly as before the service extraction.
 */
class PosException extends RuntimeException {}
