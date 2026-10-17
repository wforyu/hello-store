<?php

namespace App\Exceptions;

/**
 * Raised for malformed POS input: empty cart, unknown payment method,
 * or cash that does not cover the total. Always a client error (422),
 * never a conflict.
 */
class PosValidationException extends PosException {}
