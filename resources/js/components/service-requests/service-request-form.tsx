import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    PRIORITY_LABELS,
    REQUEST_PRIORITIES,
    REQUEST_STATUSES,
    STATUS_LABELS,
} from '@/types/service-request';
import type { ServiceRequest } from '@/types/service-request';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    // Wayfinder form definition (store.form() or update.form(request)),
    // so this component does not need to know which URL it submits to.
    action: RouteFormDefinition<'post'>;
    // Present when editing: its values prefill the fields.
    serviceRequest?: ServiceRequest;
    submitLabel: string;
    cancelHref: string;
};

export function ServiceRequestForm({
    action,
    serviceRequest,
    submitLabel,
    cancelHref,
}: Props) {
    const isEditing = serviceRequest !== undefined;

    return (
        <Form {...action} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            defaultValue={serviceRequest?.title}
                            maxLength={255}
                            required
                            aria-invalid={errors.title ? true : undefined}
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            defaultValue={serviceRequest?.description}
                            rows={6}
                            maxLength={5000}
                            required
                            aria-invalid={errors.description ? true : undefined}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        {/* New requests always start as "open" on the server,
                            so status can only be chosen when editing. */}
                        {isEditing && (
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    name="status"
                                    defaultValue={serviceRequest.status}
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {REQUEST_STATUSES.map((status) => (
                                            <SelectItem
                                                key={status}
                                                value={status}
                                            >
                                                {STATUS_LABELS[status]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="priority">Priority</Label>
                            <Select
                                name="priority"
                                defaultValue={
                                    serviceRequest?.priority ?? 'medium'
                                }
                            >
                                <SelectTrigger id="priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {REQUEST_PRIORITIES.map((priority) => (
                                        <SelectItem
                                            key={priority}
                                            value={priority}
                                        >
                                            {PRIORITY_LABELS[priority]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.priority} />
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="submit-service-request-button"
                        >
                            {submitLabel}
                        </Button>
                        <Link
                            href={cancelHref}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            Cancel
                        </Link>
                    </div>
                </>
            )}
        </Form>
    );
}
