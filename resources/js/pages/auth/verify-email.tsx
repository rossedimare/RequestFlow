import { Form, Head, router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

// Fortify flashes this status after a verification email has been (re)sent.
const LINK_SENT_STATUS = 'verification-link-sent';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            {status === LINK_SENT_STATUS && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <div className="space-y-6 text-center">
                <Form {...send.form()}>
                    {({ processing }) => (
                        <Button
                            disabled={processing}
                            variant="secondary"
                            data-test="resend-verification-button"
                        >
                            {processing && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            Resend verification email
                        </Button>
                    )}
                </Form>

                {/* Outside the Form above: this is a separate POST request. */}
                <TextLink
                    href={logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                    className="mx-auto block text-sm"
                    data-test="verify-email-logout-button"
                >
                    Log out
                </TextLink>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verify email',
    description:
        'Please verify your email address by clicking the link we just emailed to you.',
};
