import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FlashPayload = {
    status?: string | null;
    error?: string | null;
};

type FlashToasterProps = {
    initialFlash?: FlashPayload;
};

export default function FlashToaster({ initialFlash }: FlashToasterProps) {
    const lastShownRef = useRef<string>('');

    const showFlash = (flash?: FlashPayload) => {
        const statusMessage = typeof flash?.status === 'string' ? flash.status.trim() : '';
        const errorMessage = typeof flash?.error === 'string' ? flash.error.trim() : '';

        if (statusMessage) {
            const key = `success:${statusMessage}`;

            if (lastShownRef.current !== key) {
                toast.success(statusMessage);
                lastShownRef.current = key;
            }

            return;
        }

        if (errorMessage) {
            const key = `error:${errorMessage}`;

            if (lastShownRef.current !== key) {
                toast.error(errorMessage);
                lastShownRef.current = key;
            }
        }
    };

    useEffect(() => {
        showFlash(initialFlash);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialFlash?.status, initialFlash?.error]);

    useEffect(() => {
        const removeListener = router.on('success', (event) => {
            const flash = event.detail.page.props.flash as FlashPayload | undefined;

            showFlash(flash);
        });

        return () => {
            removeListener();
        };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return null;
}
