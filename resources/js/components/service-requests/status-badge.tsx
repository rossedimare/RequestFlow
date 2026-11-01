import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { STATUS_LABELS } from '@/types/service-request';
import type { RequestStatus } from '@/types/service-request';

const styles: Record<RequestStatus, string> = {
    open: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-300',
    in_progress:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300',
    resolved:
        'border-green-200 bg-green-50 text-green-700 dark:border-green-400/20 dark:bg-green-400/10 dark:text-green-300',
    closed: 'text-muted-foreground',
};

export function StatusBadge({ status }: { status: RequestStatus }) {
    return (
        <Badge variant="outline" className={cn(styles[status])}>
            {STATUS_LABELS[status]}
        </Badge>
    );
}
