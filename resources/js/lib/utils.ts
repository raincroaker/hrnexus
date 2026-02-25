import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    const target = normalizeUrlPath(toUrl(urlToCheck));
    const current = normalizeUrlPath(currentUrl);

    return target.length > 0 && target === current;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    if (typeof href === 'string') {
        return href;
    }

    return typeof href?.url === 'string' ? href.url : '';
}

export function normalizeUrlPath(url: string) {
    return String(url ?? '').split('?')[0].split('#')[0];
}
