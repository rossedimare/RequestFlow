import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/service-requests';
import {
    PRIORITY_LABELS,
    REQUEST_PRIORITIES,
    REQUEST_STATUSES,
    STATUS_LABELS,
} from '@/types/service-request';
import type { ServiceRequestFilters as Filters } from '@/types/service-request';

// Radix Select does not allow an empty string as an item value, so
// "no filter" is this sentinel and it is never sent to the server.
const ALL = 'all';
const SEARCH_DEBOUNCE_MS = 300;

type Params = {
    search: string;
    status: string | null;
    priority: string | null;
};

// Only non-empty values are sent, so the URL stays clean (no ?status=&priority=).
function visit({ search, status, priority }: Params) {
    const query: Record<string, string> = {};

    if (search.trim() !== '') {
        query.search = search.trim();
    }

    if (status) {
        query.status = status;
    }

    if (priority) {
        query.priority = priority;
    }

    router.get(index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

export function ServiceRequestFilters({ filters }: { filters: Filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    // Debounced automatic search: wait until the user stops typing.
    // Re-running when status/priority change cancels a stale pending search.
    useEffect(() => {
        if (search.trim() === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                visit({
                    search,
                    status: filters.status,
                    priority: filters.priority,
                }),
            SEARCH_DEBOUNCE_MS,
        );

        return () => clearTimeout(timeout);
    }, [search, filters.search, filters.status, filters.priority]);

    const hasActiveFilters =
        search !== '' || filters.status !== null || filters.priority !== null;

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by title or description"
                aria-label="Search requests"
                className="sm:max-w-xs"
            />

            <Select
                value={filters.status ?? ALL}
                onValueChange={(value) =>
                    visit({
                        search,
                        status: value === ALL ? null : value,
                        priority: filters.priority,
                    })
                }
            >
                <SelectTrigger
                    aria-label="Filter by status"
                    className="sm:w-44"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All statuses</SelectItem>
                    {REQUEST_STATUSES.map((status) => (
                        <SelectItem key={status} value={status}>
                            {STATUS_LABELS[status]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.priority ?? ALL}
                onValueChange={(value) =>
                    visit({
                        search,
                        status: filters.status,
                        priority: value === ALL ? null : value,
                    })
                }
            >
                <SelectTrigger
                    aria-label="Filter by priority"
                    className="sm:w-44"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All priorities</SelectItem>
                    {REQUEST_PRIORITIES.map((priority) => (
                        <SelectItem key={priority} value={priority}>
                            {PRIORITY_LABELS[priority]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {hasActiveFilters && (
                <Button
                    variant="ghost"
                    onClick={() => {
                        setSearch('');
                        visit({ search: '', status: null, priority: null });
                    }}
                >
                    Clear filters
                </Button>
            )}
        </div>
    );
}
