import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

const PATIENT_SOURCES = [
    { value: 'passaparola', label: 'Passaparola' },
    { value: 'campagna_social', label: 'Campagna social' },
    { value: 'google', label: 'Google' },
    { value: 'sito', label: 'Sito web' },
    { value: 'invio_medico', label: 'Invio da medico' },
    { value: 'altro', label: 'Altro' },
];

function Field({ id, label, errors, children }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            {children}
            <InputError message={errors[id]} className="mt-2" />
        </div>
    );
}

function Text({ id, label, data, setData, errors, type = 'text', ...props }) {
    return (
        <Field id={id} label={label} errors={errors}>
            <TextInput
                id={id}
                type={type}
                className="mt-1 block w-full"
                value={data[id] ?? ''}
                onChange={(e) => setData(id, e.target.value)}
                {...props}
            />
        </Field>
    );
}

export default function PatientFormFields({ data, setData, errors }) {
    return (
        <div className="space-y-8">
            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Dati personali
                </h3>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Text id="last_name" label="Cognome" data={data} setData={setData} errors={errors} required />
                    <Text id="first_name" label="Nome" data={data} setData={setData} errors={errors} required />
                    <Text id="date_of_birth" label="Data di nascita" type="date" data={data} setData={setData} errors={errors} />
                    <Text id="birth_place" label="Luogo di nascita" data={data} setData={setData} errors={errors} />

                    <Field id="gender" label="Sesso" errors={errors}>
                        <select
                            id="gender"
                            className="mt-1 block w-full rounded-control border-ink/15 shadow-sm focus:border-brand focus:ring-brand"
                            value={data.gender ?? ''}
                            onChange={(e) => setData('gender', e.target.value)}
                        >
                            <option value="">—</option>
                            <option value="M">M</option>
                            <option value="F">F</option>
                        </select>
                    </Field>

                    <Text id="fiscal_code" label="Codice fiscale" data={data} setData={setData} errors={errors} maxLength={16} />
                </div>
            </section>

            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Contatti
                </h3>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Text id="mobile_phone" label="Cellulare" data={data} setData={setData} errors={errors} />
                    <Text id="landline_phone" label="Telefono fisso" data={data} setData={setData} errors={errors} />
                    <Text id="email" label="Email" type="email" data={data} setData={setData} errors={errors} />
                </div>
            </section>

            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Domicilio
                </h3>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <Text id="address_street" label="Via" data={data} setData={setData} errors={errors} />
                    </div>
                    <Text id="address_postal_code" label="CAP" data={data} setData={setData} errors={errors} maxLength={5} />
                    <Text id="address_city" label="Città" data={data} setData={setData} errors={errors} />
                    <Text id="address_province" label="Provincia" data={data} setData={setData} errors={errors} maxLength={2} />
                </div>
            </section>

            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Residenza (solo se diversa dal domicilio)
                </h3>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <Text id="residence_street" label="Via" data={data} setData={setData} errors={errors} />
                    </div>
                    <Text id="residence_postal_code" label="CAP" data={data} setData={setData} errors={errors} maxLength={5} />
                    <Text id="residence_city" label="Città" data={data} setData={setData} errors={errors} />
                    <Text id="residence_province" label="Provincia" data={data} setData={setData} errors={errors} maxLength={2} />
                </div>
            </section>

            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Dati amministrativi
                </h3>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Text id="vat_number" label="Partita IVA" data={data} setData={setData} errors={errors} maxLength={11} />

                    <Field id="source" label="Fonte di provenienza" errors={errors}>
                        <select
                            id="source"
                            className="mt-1 block w-full rounded-control border-ink/15 shadow-sm focus:border-brand focus:ring-brand"
                            value={data.source ?? ''}
                            onChange={(e) => setData('source', e.target.value)}
                        >
                            <option value="">—</option>
                            {PATIENT_SOURCES.map(({ value, label }) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
            </section>

            <section>
                <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Note
                </h3>
                <Field id="notes" label="Note amministrative" errors={errors}>
                    <textarea
                        id="notes"
                        className="mt-1 block w-full rounded-control border-ink/15 shadow-sm focus:border-brand focus:ring-brand"
                        rows={4}
                        value={data.notes ?? ''}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </Field>
            </section>
        </div>
    );
}
