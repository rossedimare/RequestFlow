import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/service-requests';
import type { ServiceRequest } from '@/types/service-request';

export function DeleteServiceRequest({
    serviceRequest,
}: {
    serviceRequest: Pick<ServiceRequest, 'id' | 'title'>;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="destructive"
                    data-test="delete-service-request-button"
                >
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete this request?</DialogTitle>
                <DialogDescription>
                    "{serviceRequest.title}" will be permanently deleted. This
                    action cannot be undone.
                </DialogDescription>

                <Form {...destroy.form(serviceRequest)}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>

                            <Button
                                variant="destructive"
                                disabled={processing}
                                asChild
                            >
                                <button
                                    type="submit"
                                    data-test="confirm-delete-service-request-button"
                                >
                                    Delete request
                                </button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
