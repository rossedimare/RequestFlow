import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ServiceRequestForm } from '@/components/service-requests/service-request-form';
import { index, store } from '@/routes/service-requests';

export default function ServiceRequestsCreate() {
    return (
        <>
            <Head title="New request" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New request"
                    description="Describe what you need. New requests start as open."
                />

                <ServiceRequestForm
                    action={store.form()}
                    submitLabel="Create request"
                    cancelHref={index().url}
                />
            </div>
        </>
    );
}

ServiceRequestsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Requests',
            href: index(),
        },
    ],
};
