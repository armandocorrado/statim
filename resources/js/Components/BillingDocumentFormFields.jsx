import InputError from '@/Components/InputError';
import PatientPicker from '@/Components/PatientPicker';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import VatExemptionReasonField from '@/Components/VatExemptionReasonField';

const EMPTY_LINE = {
    description: '',
    quantity: 1,
    unit_price: '',
    vat_rate: '',
    vat_exemption_reason: 'art. 10 n. 18 DPR 633/72',
};

function computeTotals(lines) {
    let taxable = 0;
    let vat = 0;

    for (const line of lines) {
        const lineTotal =
            (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);
        taxable += lineTotal;
        const rate = parseFloat(line.vat_rate) || 0;
        if (rate > 0) {
            vat += lineTotal * (rate / 100);
        }
    }

    return { taxable, vat, total: taxable + vat };
}

export default function BillingDocumentFormFields({
    data,
    setData,
    errors,
    patientLabel,
    setPatientLabel,
    recipientLabel,
    setRecipientLabel,
    vatExemptionReasons,
}) {
    const updateLine = (index, field, value) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], [field]: value };
        setData('lines', lines);
    };

    const addLine = () => setData('lines', [...data.lines, { ...EMPTY_LINE }]);

    const removeLine = (index) =>
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index),
        );

    const totals = computeTotals(data.lines);

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <PatientPicker
                    id="patient_search"
                    label="Paziente"
                    initialLabel={patientLabel}
                    error={errors.patient_id}
                    onSelect={(id, label) => {
                        setData('patient_id', id);
                        setPatientLabel(label);
                    }}
                />
                <div>
                    <PatientPicker
                        id="recipient_search"
                        label="Intestatario (se diverso dal paziente)"
                        initialLabel={recipientLabel}
                        error={errors.recipient_patient_id}
                        onSelect={(id, label) => {
                            setData('recipient_patient_id', id);
                            setRecipientLabel(label);
                        }}
                    />
                    {data.recipient_patient_id && (
                        <button
                            type="button"
                            className="mt-1 text-xs text-gray-500 hover:underline"
                            onClick={() => {
                                setData('recipient_patient_id', null);
                                setRecipientLabel('');
                            }}
                        >
                            Fattura al paziente stesso
                        </button>
                    )}
                </div>
            </div>

            <div>
                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Righe
                </h3>
                <InputError message={errors.lines} className="mb-2" />

                <div className="overflow-x-auto rounded-md border border-gray-200">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                            <tr>
                                <th className="px-3 py-2">Descrizione</th>
                                <th className="w-20 px-3 py-2">Qtà</th>
                                <th className="w-28 px-3 py-2">
                                    Prezzo unit.
                                </th>
                                <th className="w-24 px-3 py-2">IVA %</th>
                                <th className="px-3 py-2">
                                    Motivo esenzione
                                </th>
                                <th className="w-10 px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {data.lines.map((line, index) => (
                                <tr
                                    key={index}
                                    className="border-b border-gray-100"
                                >
                                    <td className="px-3 py-2">
                                        <TextInput
                                            className="w-full"
                                            value={line.description}
                                            onChange={(e) =>
                                                updateLine(
                                                    index,
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `lines.${index}.description`
                                                ]
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="w-full"
                                            value={line.quantity}
                                            onChange={(e) =>
                                                updateLine(
                                                    index,
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="w-full"
                                            value={line.unit_price}
                                            onChange={(e) =>
                                                updateLine(
                                                    index,
                                                    'unit_price',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `lines.${index}.unit_price`
                                                ]
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="w-full"
                                            placeholder="esente"
                                            value={line.vat_rate ?? ''}
                                            onChange={(e) =>
                                                updateLine(
                                                    index,
                                                    'vat_rate',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <VatExemptionReasonField
                                            reasons={vatExemptionReasons}
                                            value={line.vat_exemption_reason ?? ''}
                                            onChange={(value) =>
                                                updateLine(
                                                    index,
                                                    'vat_exemption_reason',
                                                    value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {data.lines.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                                className="text-red-600 hover:underline"
                                            >
                                                ✕
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <SecondaryButton type="button" className="mt-2" onClick={addLine}>
                    Aggiungi riga
                </SecondaryButton>
            </div>

            <div className="flex justify-end">
                <dl className="w-64 space-y-1 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-gray-500">Imponibile</dt>
                        <dd>{totals.taxable.toFixed(2)} €</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-gray-500">IVA</dt>
                        <dd>{totals.vat.toFixed(2)} €</dd>
                    </div>
                    <div className="flex justify-between font-semibold">
                        <dt>Totale</dt>
                        <dd>{totals.total.toFixed(2)} €</dd>
                    </div>
                </dl>
            </div>
        </div>
    );
}
