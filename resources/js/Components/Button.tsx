import { Link } from '@inertiajs/react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'md' | 'sm';

const base =
    'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';

const variants: Record<Variant, string> = {
    primary: 'bg-primary text-primary-text shadow-card hover:bg-primary-hover',
    secondary: 'border border-border-strong bg-surface text-text shadow-card hover:bg-surface-muted',
    ghost: 'text-muted hover:bg-surface-muted hover:text-text',
    danger: 'text-danger hover:bg-danger-soft',
};

const sizes: Record<Size, string> = { md: 'h-10 px-4 text-sm', sm: 'h-8 px-3 text-sm' };

export const buttonClass = (variant: Variant = 'primary', size: Size = 'md') =>
    `${base} ${variants[variant]} ${sizes[size]}`;

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
}

export function Button({ variant, size, className = '', ...rest }: Props) {
    return <button type="button" {...rest} className={`${buttonClass(variant, size)} ${className}`} />;
}

export function ButtonLink({
    href,
    variant,
    size,
    children,
}: {
    href: string;
    variant?: Variant;
    size?: Size;
    children: ReactNode;
}) {
    return (
        <Link href={href} className={buttonClass(variant, size)}>
            {children}
        </Link>
    );
}
