import { Pencil, Trash2 } from 'lucide-react';
import { useT } from '../i18n';

/** The edit and delete icons at the end of a table row. */
export function RowActions({ onEdit, onDelete }: { onEdit: () => void; onDelete: () => void }) {
    const t = useT();
    const cls = 'rounded-lg p-2 text-muted hover:bg-surface-muted';

    return (
        <div className="flex justify-end gap-1">
            <button onClick={onEdit} title={t('Edit')} aria-label={t('Edit')} className={`${cls} hover:text-text`}>
                <Pencil className="size-4" />
            </button>
            <button
                onClick={onDelete}
                title={t('Delete')}
                aria-label={t('Delete')}
                className={`${cls} hover:text-danger`}
            >
                <Trash2 className="size-4" />
            </button>
        </div>
    );
}
