<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Services\Calls\CallReportingService;
use App\Support\Filters\CallFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CallsController extends Controller
{
    public function __construct(
        private readonly CallReportingService $calls,
        private readonly CallFilters $filters
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters->normalise($request->all());
        $perPage = max(1, min((int) $request->integer('per_page', 100), 500));
        $paginator = $this->calls->paginate($filters, $perPage);
        $mask = $this->calls->piiMaskingEnabled();

        $collection = $paginator->getCollection()->map(function (Call $call) use ($mask) {
            return $this->calls->transform($call, $mask);
        });

        $paginator->setCollection($collection);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'filters' => array_filter($filters, static fn ($value) => ! in_array($value, [null, ''], true)),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'previous' => $paginator->previousPageUrl(),
            ],
        ]);
    }
}
