<?php

declare(strict_types=1);

namespace Airwire\Testing;

class ResponseMetadata
{
    /** Results of method calls. */
    public array $calls;

    /**
     * Validation errors.
     *
     * @var array<string, string[]>
     */
    public array $errors;

    /**
     * Exceptions occured during the execution of individual methods..
     *
     * @var array<string, array>
     */
    public array $exceptions;

    public function __construct(array $calls, array $errors, array $exceptions)
    {
        $this->calls = $calls;
        $this->errors = $errors;
        $this->exceptions = $exceptions;
    }
}
