import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { DeleteServiceRequest } from '@/components/service-requests/delete-service-request';
import { PriorityBadge } from '@/components/service-requests/priority-badge';
import { StatusBadge } from '@/components/service-requests/status-badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/service-requests';
import type { ServiceRequest } from '@/types/service-request';

type Props = {
    serviceRequest: ServiceRequest;
};

const dateTimeFormat = new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function ServiceRequestsShow({ serviceRequest }: Props) {
    return (
        <>
            <Head title={serviceRequest.title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={serviceRequest.title}
                        description={`Request #${serviceRequest.id}`}
                    />

                    <div className="flex items-center gap-2">
                        <Link
                            href={index()}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            Back to requests
                        </Link>
                        <Button variant="outline" asChild>
                            <Link
                                href={edit(serviceRequest)}
                                data-test="edit-service-request-link"
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteServiceRequest serviceRequest={serviceRequest} />
                    </div>
                </div>

                <div className="grid gap-6 rounded-xl border border-sidebar-border/70 p-6 md:grid-cols-3 dark:border-sidebar-border">
                    <section className="space-y-2 md:col-span-2">
                        <h3 className="text-sm font-medium text-muted-foreground">
                            Description
                        </h3>
                        <p className="text-sm whitespace-pre-wrap">
                            {serviceRequest.description}
                        </p>
                    </section>

                    <dl className="space-y-4 text-sm">
                        <div className="space-y-1">
                            <dt className="text-muted-foreground">Status</dt>
                            <dd>
                                <StatusBadge status={serviceRequest.status} />
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-muted-foreground">Priority</dt>
                            <dd>
                                <PriorityBadge
                                    priority={serviceRequest.priority}
                                />
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-muted-foreground">
                                Created by
                            </dt>
                            <dd>
                                {serviceRequest.creator?.name ?? 'Unknown user'}
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-muted-foreground">Created</dt>
                            <dd>
                                {dateTimeFormat.format(
                                    new Date(serviceRequest.created_at),
                                )}
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-muted-foreground">
                                Last updated
                            </dt>
                            <dd>
                                {dateTimeFormat.format(
                                    new Date(serviceRequest.updated_at),
                                )}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </>
    );
}

ServiceRequestsShow.layout = {
    breadcrumbs: [
        {
            title: 'Requests',
            href: index(),
        },
    ],
};
