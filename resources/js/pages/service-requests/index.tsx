import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/service-requests/pagination';
import { PriorityBadge } from '@/components/service-requests/priority-badge';
import { ServiceRequestFilters } from '@/components/service-requests/service-request-filters';
import { StatusBadge } from '@/components/service-requests/status-badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/service-requests';
import type {
    Paginated,
    ServiceRequest,
    ServiceRequestFilters as Filters,
} from '@/types/service-request';

type Props = {
    serviceRequests: Paginated<ServiceRequest>;
    filters: Filters;
};

const dateFormat = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

export default function ServiceRequestsIndex({
    serviceRequests,
    filters,
}: Props) {
    return (
        <>
            <Head title="Requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Requests"
                        description="Internal requests shared by everyone on the team"
                    />

                    <Button asChild>
                        <Link
                            href={create()}
                            data-test="new-service-request-link"
                        >
                            New request
                        </Link>
                    </Button>
                </div>

                <ServiceRequestFilters filters={filters} />

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Priority
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created by
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {serviceRequests.data.map((serviceRequest) => (
                                <tr key={serviceRequest.id}>
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={show(serviceRequest.id)}
                                            className="hover:underline"
                                        >
                                            {serviceRequest.title}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={serviceRequest.status}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <PriorityBadge
                                            priority={serviceRequest.priority}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {serviceRequest.creator?.name ??
                                            'Unknown user'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {dateFormat.format(
                                            new Date(serviceRequest.created_at),
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {serviceRequests.data.length === 0 && (
                        <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                            No requests match your search or filters.
                        </p>
                    )}
                </div>

                <Pagination paginator={serviceRequests} />
            </div>
        </>
    );
}

ServiceRequestsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Requests',
            href: index(),
        },
    ],
};
