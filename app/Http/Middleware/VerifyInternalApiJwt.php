<?php

namespace App\Http\Middleware;

use App\Services\Api\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class VerifyInternalApiJwt
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorised('Missing bearer token.');
        }

        try {
            $claims = $this->jwt->verify($token);
        } catch (Throwable $exception) {
            return $this->unauthorised($exception->getMessage());
        }

        $request->attributes->set('internal_api_claims', $claims);

        return $next($request);
    }

    private function unauthorised(string $message): JsonResponse
    {
        return response()->json([
            'error' => 'unauthorised',
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
