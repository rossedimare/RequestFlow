<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexServiceRequestRequest;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    /**
     * Show the shared list of service requests, newest first, optionally
     * searched and filtered by status and priority.
     */
    public function index(IndexServiceRequestRequest $request): Response
    {
        $filters = [
            'search' => $request->validated('search'),
            'status' => $request->validated('status'),
            'priority' => $request->validated('priority'),
        ];

        return Inertia::render('service-requests/index', [
            'serviceRequests' => ServiceRequest::query()
                ->with('creator:id,name')
                ->search($filters['search'])
                ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['priority'], fn (Builder $query, string $priority) => $query->where('priority', $priority))
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form to create a service request.
     */
    public function create(): Response
    {
        Gate::authorize('create', ServiceRequest::class);

        return Inertia::render('service-requests/create');
    }

    /**
     * Show a single service request.
     */
    public function show(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('view', $serviceRequest);

        return Inertia::render('service-requests/show', [
            'serviceRequest' => $serviceRequest->load('creator:id,name'),
            // Computed by the policy so the UI never re-implements the rule.
            'can' => [
                'update' => Gate::allows('update', $serviceRequest),
                'delete' => Gate::allows('delete', $serviceRequest),
            ],
        ]);
    }

    /**
     * Show the form to edit a service request.
     */
    public function edit(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('update', $serviceRequest);

        return Inertia::render('service-requests/edit', [
            'serviceRequest' => $serviceRequest,
        ]);
    }

    /**
     * Store a new service request created by the authenticated user.
     */
    public function store(StoreServiceRequestRequest $request): RedirectResponse
    {
        $serviceRequest = $request->user()->serviceRequests()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request created.')]);

        return to_route('service-requests.show', $serviceRequest);
    }

    /**
     * Update an existing service request. The creator is never changed.
     */
    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $serviceRequest->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request updated.')]);

        return to_route('service-requests.show', $serviceRequest);
    }

    /**
     * Delete a service request.
     */
    public function destroy(ServiceRequest $serviceRequest): RedirectResponse
    {
        Gate::authorize('delete', $serviceRequest);

        $serviceRequest->delete();

        return to_route('service-requests.index');
    }
}
