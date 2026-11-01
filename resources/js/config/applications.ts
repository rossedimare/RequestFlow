import type { InertiaLinkProps } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { index as serviceRequestsIndex } from '@/routes/service-requests';

export type Application = {
    name: string;
    description: string;
    icon: LucideIcon;
    href: NonNullable<InertiaLinkProps['href']>;
};

// Add a new entry here to show another application on the dashboard.
export const applications: Application[] = [
    {
        name: 'RequestFlow',
        description: 'Internal Business Request Management',
        icon: ClipboardList,
        href: serviceRequestsIndex(),
    },
];
