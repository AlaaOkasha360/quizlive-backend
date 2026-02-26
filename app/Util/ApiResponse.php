<?php

namespace App\Util;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function send($code = Response::HTTP_OK, $message = 'Success response', $data = [], $resource = null, $errors = []): JsonResponse
    {
        $data = self::prepareData($data, $resource);

        $response = [
            'status' => $code,
            'message' => $message,
            'meta' => $data['meta'] ?? null,
            'data' => $data['items'],
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    public static function success(string $message = 'Success response', $data = [], $resource = null): JsonResponse
    {
        return self::send(Response::HTTP_OK, $message, $data, $resource);
    }

    public static function created(string $message = 'Resource created successfully', $data = [], $resource = null): JsonResponse
    {
        return self::send(Response::HTTP_CREATED, $message, $data, $resource);
    }

    public static function badRequest(string $message, array $errors = []): JsonResponse
    {
        return self::send(code: Response::HTTP_BAD_REQUEST, message: $message, errors: $errors);
    }

    public static function notFound(string $message): JsonResponse
    {
        return self::send(Response::HTTP_NOT_FOUND, $message);
    }

    public static function invalidArgument(string $message): JsonResponse
    {
        return self::send(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function forbidden(string $message): JsonResponse
    {
        return self::send(Response::HTTP_FORBIDDEN, $message);
    }

    public static function validationError(string $message, $errors, $resource): JsonResponse
    {
        return self::send(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $errors, $resource);
    }

    public static function unauthorized(string $message): JsonResponse
    {
        return self::send(Response::HTTP_UNAUTHORIZED, $message);
    }

    public static function error(string $message): JsonResponse
    {
        return self::send(Response::HTTP_INTERNAL_SERVER_ERROR, $message);
    }

    public static function getCode(int|string $code): int
    {
        if ($code == '0') {
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return is_int($code) ? $code : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    protected static function getPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    protected static function prepareData($data, $resource)
    {
        if ($data instanceof LengthAwarePaginator && $resource != null) {
            $data = [
                'meta' => self::getPaginationMeta($data),
                'items' => $resource::collection($data->items()),
            ];

            return $data;
        }

        return ['items' => $data];
    }
}
