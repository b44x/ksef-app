import { router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2, Save, Star, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button, ButtonLink } from '../Components/Button';
import { Field, Input, Select } from '../Components/Form';
import { Alert, Badge, Card } from '../Components/Ui';
import { useT } from '../i18n';
import { usePdfPreview } from '../hooks/usePdfPreview';
import AppLayout from '../Layouts/AppLayout';
import type { SharedProps } from '../types';
import { LayoutThumb } from './Templates';

interface Settings {
    layout: string;
    accent: string;
    font: string;
    size: string;
    stripes: boolean;
    logo: { show: boolean; position: string; height: number };
    show: Record<string, boolean>;
    footer_text: string;
}

interface Props {
    template: { id: number; name: string; is_default: boolean; settings: Settings };
    hasLogo: boolean;
    options: { layouts: string[]; fonts: string[]; sizes: string[]; parts: string[] };
}

const swatches = ['#0a9a7b', '#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#111827'];

export default function TemplateEditor({ template, hasLogo, options }: Props) {
    const t = useT();
    const { locale, errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const form = useForm({ name: template.name, settings: template.settings });
    const [lang, setLang] = useState(locale === 'pl' ? 'pl' : 'en');
    const preview = usePdfPreview(form.data.settings, lang);
    const file = useRef<HTMLInputElement>(null);

    const settings = form.data.settings;
    const set = (patch: Partial<Settings>) => form.setData('settings', { ...settings, ...patch });
    const setLogo = (patch: Partial<Settings['logo']>) => set({ logo: { ...settings.logo, ...patch } });
    const setPart = (part: string, value: boolean) => set({ show: { ...settings.show, [part]: value } });

    const layoutNames: Record<string, string> = { classic: t('Classic'), modern: t('Modern'), minimal: t('Minimal') };
    const fontNames: Record<string, string> = { sans: t('Sans-serif'), serif: t('Serif'), mono: t('Monospace') };
    const sizeNames: Record<string, string> = { small: t('Small'), normal: t('Normal'), large: t('Large') };
    const partNames: Record<string, string> = {
        unit: t('Unit column'),
        vat_column: t('VAT rate column'),
        seller_email: t("Seller's e-mail"),
        buyer_email: t("Buyer's e-mail"),
        bank_account: t('Bank account'),
        payment: t('Payment block'),
        notes: t('Notes'),
        words: t('Total in words'),
        qr: t('KSeF QR codes'),
        signatures: t('Signature lines'),
        footer: t('Footer text'),
    };

    const check = (label: string, checked: boolean, onChange: (v: boolean) => void) => (
        <label key={label} className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                className="size-4 rounded border-border-strong accent-[var(--primary)]"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );

    return (
        <AppLayout>
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <ButtonLink href="/templates" variant="secondary" size="sm">
                        <ArrowLeft className="size-4" />
                        {t('Templates')}
                    </ButtonLink>
                    <h1 className="text-xl font-semibold">{t('Edit template')}</h1>
                    {template.is_default && <Badge tone="success">{t('Default')}</Badge>}
                </div>
                <div className="flex gap-2">
                    {!template.is_default && (
                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.post(`/templates/${template.id}/default`, {}, { preserveScroll: true })
                            }
                        >
                            <Star className="size-4" />
                            {t('Make default')}
                        </Button>
                    )}
                    <Button
                        onClick={() => form.put(`/templates/${template.id}`, { preserveScroll: true })}
                        disabled={form.processing}
                    >
                        <Save className="size-4" />
                        {t('Save')}
                    </Button>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <div className="space-y-5">
                    <Card title={t('Template')}>
                        <Field label={t('Name')} error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                    </Card>

                    <Card title={t('Layout')}>
                        <div className="grid grid-cols-3 gap-3">
                            {options.layouts.map((layout) => (
                                <button
                                    key={layout}
                                    type="button"
                                    onClick={() => set({ layout })}
                                    aria-pressed={settings.layout === layout}
                                    className={`rounded-lg border p-2 text-center text-xs transition-colors ${settings.layout === layout ? 'border-primary bg-primary-soft' : 'border-border hover:bg-surface-muted'}`}
                                >
                                    <LayoutThumb layout={layout} accent={settings.accent} />
                                    <span className="mt-1.5 block font-medium">{layoutNames[layout]}</span>
                                </button>
                            ))}
                        </div>
                    </Card>

                    <Card title={t('Colour and type')}>
                        <div className="space-y-4">
                            <Field label={t('Accent colour')}>
                                <div className="flex flex-wrap items-center gap-2">
                                    {swatches.map((c) => (
                                        <button
                                            key={c}
                                            type="button"
                                            aria-label={c}
                                            onClick={() => set({ accent: c })}
                                            className={`size-7 rounded-full border-2 ${settings.accent === c ? 'border-text' : 'border-transparent'}`}
                                            style={{ background: c }}
                                        />
                                    ))}
                                    <input
                                        type="color"
                                        aria-label={t('Accent colour')}
                                        value={settings.accent}
                                        onChange={(e) => set({ accent: e.target.value })}
                                        className="size-7 cursor-pointer rounded border border-border bg-transparent p-0"
                                    />
                                </div>
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Font')}>
                                    <Select value={settings.font} onChange={(e) => set({ font: e.target.value })}>
                                        {options.fonts.map((f) => (
                                            <option key={f} value={f}>
                                                {fontNames[f]}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('Text size')}>
                                    <Select value={settings.size} onChange={(e) => set({ size: e.target.value })}>
                                        {options.sizes.map((s) => (
                                            <option key={s} value={s}>
                                                {sizeNames[s]}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            {check(t('Striped rows'), settings.stripes, (v) => set({ stripes: v }))}
                        </div>
                    </Card>

                    <Card title={t('Logo')}>
                        <div className="space-y-4">
                            {errors.logo && <Alert tone="danger">{errors.logo}</Alert>}
                            <div className="flex flex-wrap gap-2">
                                <input
                                    ref={file}
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    className="hidden"
                                    onChange={(e) => {
                                        const logo = e.target.files?.[0];
                                        if (logo)
                                            router.post(
                                                '/company/logo',
                                                { logo },
                                                { forceFormData: true, preserveScroll: true },
                                            );
                                    }}
                                />
                                <Button variant="secondary" size="sm" onClick={() => file.current?.click()}>
                                    <Upload className="size-3.5" />
                                    {hasLogo ? t('Replace the logo') : t('Upload a logo')}
                                </Button>
                                {hasLogo && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.delete('/company/logo', { preserveScroll: true })}
                                    >
                                        <Trash2 className="size-3.5" />
                                        {t('Remove')}
                                    </Button>
                                )}
                            </div>
                            <p className="text-xs text-muted">
                                {t('PNG or JPEG, up to 512 KB. It is shared by all templates.')}
                            </p>
                            {hasLogo && (
                                <>
                                    {check(t('Show the logo'), settings.logo.show, (v) => setLogo({ show: v }))}
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Position')}>
                                            <Select
                                                value={settings.logo.position}
                                                onChange={(e) => setLogo({ position: e.target.value })}
                                            >
                                                <option value="left">{t('Left')}</option>
                                                <option value="right">{t('Right')}</option>
                                            </Select>
                                        </Field>
                                        <Field label={t('Height')}>
                                            <input
                                                type="range"
                                                min={20}
                                                max={120}
                                                value={settings.logo.height}
                                                onChange={(e) => setLogo({ height: Number(e.target.value) })}
                                                className="w-full accent-[var(--primary)]"
                                            />
                                        </Field>
                                    </div>
                                </>
                            )}
                        </div>
                    </Card>

                    <Card title={t('What is shown')}>
                        <div className="space-y-2">
                            {options.parts.map((part) =>
                                check(partNames[part], settings.show[part] ?? true, (v) => setPart(part, v)),
                            )}
                        </div>
                        <Field
                            label={t('Footer text')}
                            hint={t('Replaces the line about KSeF at the bottom of the page.')}
                            className="mt-4"
                        >
                            <Input
                                maxLength={300}
                                value={settings.footer_text}
                                onChange={(e) => set({ footer_text: e.target.value })}
                            />
                        </Field>
                    </Card>
                </div>

                <div className="lg:sticky lg:top-20 lg:self-start">
                    <div className="mb-3 flex items-center justify-between">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            {t('Live preview')}
                            {preview.loading && <Loader2 className="size-4 animate-spin text-muted" />}
                        </div>
                        <div className="inline-flex rounded-lg border border-border-strong p-0.5 text-xs">
                            {['pl', 'en'].map((l) => (
                                <button
                                    key={l}
                                    type="button"
                                    onClick={() => setLang(l)}
                                    className={`rounded-md px-2.5 py-1 font-medium uppercase ${lang === l ? 'bg-primary text-primary-text' : 'text-muted'}`}
                                >
                                    {l}
                                </button>
                            ))}
                        </div>
                    </div>
                    {preview.failed && (
                        <Alert tone="warning">{t('The preview could not be drawn. Try again in a moment.')}</Alert>
                    )}
                    <div className="overflow-hidden rounded-xl border border-border bg-surface-muted shadow-card">
                        {preview.url ? (
                            <iframe
                                title={t('Live preview')}
                                src={`${preview.url}#toolbar=0&navpanes=0&view=FitH`}
                                className="h-[75vh] min-h-[32rem] w-full"
                            />
                        ) : (
                            <div className="flex h-[32rem] items-center justify-center text-muted">
                                <Loader2 className="size-6 animate-spin" />
                            </div>
                        )}
                    </div>
                    {preview.url && (
                        <a
                            href={preview.url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-2 inline-block text-xs text-muted underline"
                        >
                            {t('Open the preview in a new tab')}
                        </a>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
