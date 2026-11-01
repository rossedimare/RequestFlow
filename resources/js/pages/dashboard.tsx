import { Head } from '@inertiajs/react';
import { ApplicationCard } from '@/components/application-card';
import Heading from '@/components/heading';
import { applications } from '@/config/applications';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="My Applications" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="My Applications"
                    description="Choose an application to get started"
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {applications.map((application) => (
                        <ApplicationCard
                            key={application.name}
                            application={application}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'My Applications',
            href: dashboard(),
        },
    ],
};
