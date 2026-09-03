import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export default function PatientFormFields({ data, setData, errors }) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <InputLabel htmlFor="last_name" value="Cognome" />
                <TextInput
                    id="last_name"
                    className="mt-1 block w-full"
                    value={data.last_name}
                    onChange={(e) => setData('last_name', e.target.value)}
                    required
                />
                <InputError message={errors.last_name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="first_name" value="Nome" />
                <TextInput
                    id="first_name"
                    className="mt-1 block w-full"
                    value={data.first_name}
                    onChange={(e) => setData('first_name', e.target.value)}
                    required
                />
                <InputError message={errors.first_name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="date_of_birth" value="Data di nascita" />
                <TextInput
                    id="date_of_birth"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.date_of_birth ?? ''}
                    onChange={(e) =>
                        setData('date_of_birth', e.target.value)
                    }
                />
                <InputError message={errors.date_of_birth} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="gender" value="Sesso" />
                <TextInput
                    id="gender"
                    className="mt-1 block w-full"
                    value={data.gender ?? ''}
                    onChange={(e) => setData('gender', e.target.value)}
                />
                <InputError message={errors.gender} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="fiscal_code" value="Codice fiscale" />
                <TextInput
                    id="fiscal_code"
                    className="mt-1 block w-full"
                    value={data.fiscal_code ?? ''}
                    onChange={(e) => setData('fiscal_code', e.target.value)}
                />
                <InputError message={errors.fiscal_code} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.email ?? ''}
                    onChange={(e) => setData('email', e.target.value)}
                />
                <InputError message={errors.email} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="phone" value="Telefono" />
                <TextInput
                    id="phone"
                    className="mt-1 block w-full"
                    value={data.phone ?? ''}
                    onChange={(e) => setData('phone', e.target.value)}
                />
                <InputError message={errors.phone} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="address" value="Indirizzo" />
                <TextInput
                    id="address"
                    className="mt-1 block w-full"
                    value={data.address ?? ''}
                    onChange={(e) => setData('address', e.target.value)}
                />
                <InputError message={errors.address} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="notes" value="Note" />
                <textarea
                    id="notes"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={4}
                    value={data.notes ?? ''}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </div>
    );
}
