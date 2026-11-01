import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ServiceRequestForm } from '@/components/service-requests/service-request-form';
import { index, show, update } from '@/routes/service-requests';
import type { ServiceRequest } from '@/types/service-request';

type Props = {
    serviceRequest: ServiceRequest;
};

export default function ServiceRequestsEdit({ serviceRequest }: Props) {
    return (
        <>
            <Head title={`Edit ${serviceRequest.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit request"
                    description={`Request #${serviceRequest.id}`}
                />

                <ServiceRequestForm
                    action={update.form(serviceRequest)}
                    serviceRequest={serviceRequest}
                    submitLabel="Save changes"
                    cancelHref={show(serviceRequest).url}
                />
            </div>
        </>
    );
}

ServiceRequestsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Requests',
            href: index(),
        },
    ],
};
