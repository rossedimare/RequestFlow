import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/service-request';

// The paginator URLs already include the active search and filters
// (the controller uses withQueryString()), so they survive page changes.
export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                Showing {paginator.from} to {paginator.to} of {paginator.total}{' '}
                requests
            </p>

            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">
                    Page {paginator.current_page} of {paginator.last_page}
                </span>

                {paginator.prev_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={paginator.prev_page_url} preserveScroll>
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                )}

                {paginator.next_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={paginator.next_page_url} preserveScroll>
                            Next
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}
