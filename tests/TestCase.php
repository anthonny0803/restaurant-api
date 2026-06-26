<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function assertApiValidationError(TestResponse $response, array $fields): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        foreach ($fields as $field) {
            $response->assertJsonPath(
                "error.fields.{$field}",
                fn ($message) => is_string($message) && $message !== '',
            );
        }
    }
}
