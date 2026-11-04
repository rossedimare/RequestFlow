export const REQUEST_STATUSES = [
    'open',
    'in_progress',
    'resolved',
    'closed',
] as const;

export const REQUEST_PRIORITIES = ['low', 'medium', 'high', 'urgent'] as const;

export type RequestStatus = (typeof REQUEST_STATUSES)[number];
export type RequestPriority = (typeof REQUEST_PRIORITIES)[number];

export const STATUS_LABELS: Record<RequestStatus, string> = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    closed: 'Closed',
};

export const PRIORITY_LABELS: Record<RequestPriority, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

export type ServiceRequest = {
    id: number;
    // null when the creator deleted their account (nullOnDelete)
    user_id: number | null;
    title: string;
    description: string;
    status: RequestStatus;
    priority: RequestPriority;
    created_at: string;
    updated_at: string;
    creator: { id: number; name: string } | null;
};

// What the current user may do with a request, computed by the server policy.
export type ServiceRequestPermissions = {
    update: boolean;
    delete: boolean;
};

// Subset of the Laravel LengthAwarePaginator JSON used by the UI.
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ServiceRequestFilters = {
    search: string | null;
    status: RequestStatus | null;
    priority: RequestPriority | null;
};
