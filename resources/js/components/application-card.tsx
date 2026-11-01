import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Application } from '@/config/applications';

export function ApplicationCard({ application }: { application: Application }) {
    const Icon = application.icon;

    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardHeader>
                <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-muted">
                    <Icon className="size-5" />
                </div>
                <CardTitle>{application.name}</CardTitle>
                <CardDescription>{application.description}</CardDescription>
            </CardHeader>
            <CardFooter className="mt-auto">
                <Button asChild>
                    <Link
                        href={application.href}
                        data-test={`open-application-${application.name.toLowerCase()}`}
                    >
                        Open application
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    );
}
