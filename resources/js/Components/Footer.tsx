import { usePage } from '@inertiajs/react';
import { Code2 } from 'lucide-react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';

/** Who made the application, with a link to the source. Set in config/credits.php. */
export function Footer({ className = '' }: { className?: string }) {
    const t = useT();
    const { app, credits } = usePage<SharedProps>().props;

    return (
        <footer
            className={`flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted ${className}`}
        >
            <span>
                © {credits.years} {credits.author}
            </span>
            <span aria-hidden>·</span>
            <span>{t('Open source, :license licence', { license: credits.license })}</span>
            <span aria-hidden>·</span>
            <a
                href={credits.url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 hover:text-text hover:underline"
            >
                <Code2 className="size-3.5" aria-hidden />
                {app.name} {t('on GitHub')}
            </a>
        </footer>
    );
}
