<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TransformApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload)) {
            return $response;
        }

        $payload = $this->reshapePagination($payload);

        return $response->setData($this->camelizeKeys($payload));
    }

    private function reshapePagination(array $payload): array
    {
        if (! isset($payload['links'], $payload['meta']['current_page'])) {
            return $payload;
        }

        $meta = $payload['meta'];
        unset($payload['links']);

        $payload['meta'] = [
            'total' => $meta['total'],
            'page' => $meta['current_page'],
            'perPage' => $meta['per_page'],
        ];

        return $payload;
    }

    private function camelizeKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $camelKey = is_string($key) ? Str::camel($key) : $key;
            $result[$camelKey] = is_array($value) ? $this->camelizeKeys($value) : $value;
        }

        return $result;
    }
}
