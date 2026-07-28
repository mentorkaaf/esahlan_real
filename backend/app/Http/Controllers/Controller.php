<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
