import { router, useForm } from '@inertiajs/react';
import { Check, Copy, Palette, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button, ButtonLink } from '../Components/Button';
import { Dialog } from '../Components/Dialog';
import { Field, Input } from '../Components/Form';
import { Badge, Card, EmptyState, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Template {
    id: number;
    name: string;
    is_default: boolean;
    layout: string;
    accent: string;
    used: number;
}

/** A little drawing of the layout in the template's colour: enough to tell the templates apart at a glance. */
export function LayoutThumb({ layout, accent }: { layout: string; accent: string }) {
    const bar = layout === 'minimal' ? 'transparent' : accent;
    return (
        <div className="aspect-[3/4] w-full overflow-hidden rounded-md border border-border bg-white p-2 shadow-card">
            {layout === 'modern' && <div className="-mx-2 -mt-2 mb-2 h-6" style={{ background: accent }} />}
            {layout !== 'modern' && <div className="mb-2 h-2 w-1/2 rounded-sm" style={{ background: accent }} />}
            <div className="mb-2 grid grid-cols-2 gap-1">
                <div
                    className="h-5 rounded-sm bg-gray-100"
                    style={layout === 'minimal' ? { borderTop: `1px solid ${accent}`, background: 'none' } : undefined}
                />
                <div
                    className="h-5 rounded-sm bg-gray-100"
                    style={layout === 'minimal' ? { borderTop: `1px solid ${accent}`, background: 'none' } : undefined}
                />
            </div>
            <div
                className="mb-1 h-2 rounded-sm"
                style={{ background: bar, borderBottom: layout === 'minimal' ? `1.5px solid ${accent}` : undefined }}
            />
            {[0, 1, 2, 3].map((i) => (
                <div key={i} className="mb-1 h-1.5 rounded-sm bg-gray-100" />
            ))}
            <div className="ml-auto mt-2 h-3 w-1/2 rounded-sm border-t-2" style={{ borderColor: accent }} />
        </div>
    );
}

export default function Templates({ templates }: { templates: Template[]; builtin: unknown }) {
    const t = useT();
    const [creating, setCreating] = useState<number | null | false>(false);
    const form = useForm({ name: '', copy: '' as number | '' });
    const names: Record<string, string> = { classic: t('Classic'), modern: t('Modern'), minimal: t('Minimal') };

    const open = (copy: number | null) => {
        form.setData({ name: '', copy: copy ?? '' });
        setCreating(copy);
    };
    const close = () => {
        setCreating(false);
        form.reset();
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('Invoice templates')}
                description={t('How your printed invoices look: layout, colours, type, logo and what is shown.')}
                actions={
                    <Button onClick={() => open(null)}>
                        <Plus className="size-4" />
                        {t('New template')}
                    </Button>
                }
            />

            {templates.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<Palette className="size-6" />}
                        title={t('No templates yet.')}
                        description={t(
                            'Invoices are printed in the built-in look. Make a template to set your colour, logo and layout.',
                        )}
                        action={<Button onClick={() => open(null)}>{t('New template')}</Button>}
                    />
                </Card>
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {templates.map((tpl) => (
                        <Card key={tpl.id}>
                            <LayoutThumb layout={tpl.layout} accent={tpl.accent} />
                            <div className="mt-4 flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <div className="truncate font-medium">{tpl.name}</div>
                                    <div className="text-xs text-muted">
                                        {names[tpl.layout]} · {t(':count invoices', { count: tpl.used })}
                                    </div>
                                </div>
                                {tpl.is_default && (
                                    <Badge tone="success">
                                        <Check className="mr-1 size-3" />
                                        {t('Default')}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-4 flex flex-wrap gap-2">
                                <ButtonLink href={`/templates/${tpl.id}/edit`} variant="secondary" size="sm">
                                    <Pencil className="size-3.5" />
                                    {t('Edit')}
                                </ButtonLink>
                                <Button variant="ghost" size="sm" onClick={() => open(tpl.id)}>
                                    <Copy className="size-3.5" />
                                    {t('Duplicate')}
                                </Button>
                                {!tpl.is_default && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.post(`/templates/${tpl.id}/default`, {}, { preserveScroll: true })
                                        }
                                    >
                                        <Star className="size-3.5" />
                                        {t('Make default')}
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        window.confirm(
                                            t(
                                                'Delete this template? Invoices that use it will be printed with the default one.',
                                            ),
                                        ) && router.delete(`/templates/${tpl.id}`)
                                    }
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">{t('Delete')}</span>
                                </Button>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Dialog open={creating !== false} onClose={close} title={t('New template')}>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/templates', { onSuccess: close });
                    }}
                >
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input
                            autoFocus
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={close}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing || form.data.name.trim() === ''}>
                            {t('Create and edit')}
                        </Button>
                    </div>
                </form>
            </Dialog>
        </AppLayout>
    );
}
