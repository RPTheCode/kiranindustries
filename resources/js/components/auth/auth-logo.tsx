import { useMemo, useState } from 'react';

import { useBrand } from '@/contexts/BrandContext';
import { cn } from '@/lib/utils';
import { getImagePath } from '@/utils/helpers';

type AuthLogoVariant = 'on-dark' | 'on-light';

interface AuthLogoProps {
    variant: AuthLogoVariant;
    className?: string;
}

const FALLBACK: Record<AuthLogoVariant, string> = {
    'on-dark': '/images/logos/logo-light.png',
    'on-light': '/images/logos/logo-dark.png',
};

function buildLogoCandidates(path: string | undefined, variant: AuthLogoVariant): string[] {
    const candidates: string[] = [];

    if (path) {
        const clean = path.replace(/^\//, '');

        if (path.startsWith('http')) {
            candidates.push(path);
        } else {
            const resolved = getImagePath(path);
            if (resolved) {
                candidates.push(resolved);
            }
            candidates.push(`/storage/media/${clean}`);
            candidates.push(`/storage/${clean}`);

            const filename = clean.split('/').pop();
            if (filename) {
                candidates.push(`/images/logos/${filename}`);
            }
        }
    }

    candidates.push(FALLBACK[variant]);

    return [...new Set(candidates.filter(Boolean))];
}

export default function AuthLogo({ variant, className }: AuthLogoProps) {
    const { logoLight, logoDark, titleText } = useBrand();
    const brandPath = variant === 'on-dark' ? logoLight : logoDark;
    const candidates = useMemo(() => buildLogoCandidates(brandPath, variant), [brandPath, variant]);
    const [candidateIndex, setCandidateIndex] = useState(0);

    const src = candidates[Math.min(candidateIndex, candidates.length - 1)] ?? FALLBACK[variant];

    return (
        <img
            key={`${variant}-${src}`}
            src={src}
            alt={titleText || 'Logo'}
            className={cn('max-h-full w-auto max-w-full object-contain object-center', className)}
            onError={() => {
                setCandidateIndex((current) => {
                    if (current < candidates.length - 1) {
                        return current + 1;
                    }
                    return current;
                });
            }}
        />
    );
}
