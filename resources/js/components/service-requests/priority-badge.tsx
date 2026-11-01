import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { PRIORITY_LABELS } from '@/types/service-request';
import type { RequestPriority } from '@/types/service-request';

const styles: Record<RequestPriority, string> = {
    low: 'text-muted-foreground',
    medium: '',
    high: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-400/20 dark:bg-orange-400/10 dark:text-orange-300',
    urgent: 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/20 dark:bg-red-400/10 dark:text-red-300',
};

export function PriorityBadge({ priority }: { priority: RequestPriority }) {
    return (
        <Badge variant="outline" className={cn(styles[priority])}>
            {PRIORITY_LABELS[priority]}
        </Badge>
    );
}
