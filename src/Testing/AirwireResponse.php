<?php

declare(strict_types=1);

namespace Airwire\Testing;

class AirwireResponse
{
    public array $data;

    public ResponseMetadata $metadata;

    public function __construct(
        protected array $rawResponse
    ) {
        $rawResponse['metadata'] ??= [];

        $this->data = ($rawResponse['data'] ?? []);
        $this->metadata = new ResponseMetadata(
            $rawResponse['metadata']['calls'] ?? [],
            $rawResponse['metadata']['errors'] ?? [],
            $rawResponse['metadata']['exceptions'] ?? [],
        );
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $target = str_starts_with($key, 'metadata.')
            ? $this->metadata
            : $this->data;

        return data_get($target, $key, $default);
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function metadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function errors(string $property = null): mixed
    {
        if ($property) {
            return $this->metadata->errors[$property] ?? [];
        }

        return $this->metadata->errors;
    }

    public function exceptions(string $method = null): mixed
    {
        if ($method) {
            return $this->metadata->exceptions[$method] ?? [];
        }

        return $this->metadata->exceptions;
    }

    /** Get the return value of a call. */
    public function call(string $key): mixed
    {
        return $this->metadata->calls[$key] ?? null;
    }
}
