<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiRequestKeys
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/stripe/*')) {
            return $next($request);
        }

        if ($request->query->count() > 0) {
            $request->query->replace($this->snakeKeys($request->query->all()));
        }

        if ($request->isJson()) {
            $request->json()->replace($this->snakeKeys($request->json()->all()));
        } elseif ($request->request->count() > 0) {
            $request->request->replace($this->snakeKeys($request->request->all()));
        }

        return $next($request);
    }

    private function snakeKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $snakeKey = is_string($key) ? Str::snake($key) : $key;
            $result[$snakeKey] = is_array($value) ? $this->snakeKeys($value) : $value;
        }

        return $result;
    }
}
