export interface Party {
    name: string;
    nip?: string | null;
    tax_id?: string | null;
    address: string;
    email: string | null;
    country?: string;
}

/** One party of an invoice (seller or buyer): the name first, then what identifies and locates it. */
export function PartyBlock({ title, party }: { title: string; party: Party }) {
    const taxId = party.tax_id ?? party.nip;

    return (
        <div className="p-6">
            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">{title}</div>
            <div className="font-medium">{party.name}</div>
            {taxId && <div className="text-sm text-muted">NIP {taxId}</div>}
            <div className="text-sm text-muted">
                {party.address}
                {party.country ? ` · ${party.country}` : ''}
            </div>
            {party.email && <div className="text-sm text-muted">{party.email}</div>}
        </div>
    );
}
