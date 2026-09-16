import AnimatedNumber from '@/Components/AnimatedNumber';
import ChartCard from '@/Components/ChartCard';
import PageHeading from '@/Components/PageHeading';
import SegmentedToggle from '@/Components/SegmentedToggle';
import StatCard from '@/Components/StatCard';
import { CATEGORICAL, CHROME, SEQUENTIAL_GREEN, STATUS, colorForCategory } from '@/chartTheme';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const CURRENCY = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
const MONTH_LABEL = new Intl.DateTimeFormat('it-IT', { month: 'short', year: 'numeric' });
const DAY_LABEL = new Intl.DateTimeFormat('it-IT', { day: 'numeric', month: 'short' });

function monthLabel(month) {
    return MONTH_LABEL.format(new Date(`${month}-01T00:00:00`)).replace('.', '');
}

function dayLabel(date) {
    return DAY_LABEL.format(new Date(`${date}T00:00:00`)).replace('.', '');
}

/**
 * Chrome del tooltip condiviso da tutti i grafici — stessa "pelle" delle
 * card (bianco, shadow-soft, ring), coerente con lo skill dataviz:
 * valore in evidenza, etichetta secondaria, mai il solo colore a
 * portare il significato.
 */
function ChartTooltip({ active, payload, formatter, labelFormatter }) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-control bg-white px-3 py-2 text-sm shadow-soft ring-1 ring-ink/10">
            {payload.map((entry) => (
                <div key={entry.dataKey ?? entry.name} className="flex items-center gap-2">
                    <span
                        className="inline-block h-2 w-2 shrink-0 rounded-full"
                        style={{ backgroundColor: entry.color }}
                    />
                    <span className="text-ink-secondary">
                        {labelFormatter ? labelFormatter(entry) : entry.name}
                    </span>
                    <span className="font-semibold text-ink">
                        {formatter ? formatter(entry.value, entry) : entry.value}
                    </span>
                </div>
            ))}
        </div>
    );
}

function RevenueChart({ data }) {
    const [months, setMonths] = useState(6);
    const sliced = useMemo(() => data.slice(data.length - months), [data, months]);
    const hasRevenue = sliced.some((point) => point.total > 0);

    return (
        <ChartCard
            title="Andamento del fatturato"
            subtitle="Documenti emessi, per mese"
            action={
                <SegmentedToggle
                    value={months}
                    onChange={setMonths}
                    options={[
                        { value: 3, label: '3 mesi' },
                        { value: 6, label: '6 mesi' },
                        { value: 12, label: '12 mesi' },
                    ]}
                />
            }
        >
            {hasRevenue ? (
                <div className="h-64 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={sliced} margin={{ top: 8, right: 28, left: 0, bottom: 0 }}>
                            <defs>
                                <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={SEQUENTIAL_GREEN[4]} stopOpacity={0.28} />
                                    <stop offset="100%" stopColor={SEQUENTIAL_GREEN[4]} stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} stroke={CHROME.grid} />
                            <XAxis
                                dataKey="month"
                                tickFormatter={monthLabel}
                                tick={{ fill: CHROME.axis, fontSize: 12 }}
                                axisLine={{ stroke: CHROME.grid }}
                                tickLine={false}
                                interval={months > 6 ? 1 : 0}
                            />
                            <YAxis
                                tickFormatter={(value) => CURRENCY.format(value)}
                                tick={{ fill: CHROME.axis, fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                                width={72}
                            />
                            <Tooltip
                                content={
                                    <ChartTooltip
                                        formatter={(value) => CURRENCY.format(value)}
                                        labelFormatter={(entry) => monthLabel(entry.payload.month)}
                                    />
                                }
                            />
                            <Area
                                type="monotone"
                                dataKey="total"
                                stroke={SEQUENTIAL_GREEN[4]}
                                strokeWidth={2}
                                fill="url(#revenueFill)"
                                activeDot={{ r: 5, stroke: '#FFFFFF', strokeWidth: 2 }}
                                isAnimationActive
                                animationDuration={500}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <EmptyState message="Nessun documento emesso in questo periodo." />
            )}
        </ChartCard>
    );
}

function bucketByWeek(daily) {
    const weeks = [];

    for (let i = 0; i < daily.length; i += 7) {
        const slice = daily.slice(i, i + 7);
        weeks.push({
            date: slice[0].date,
            count: slice.reduce((sum, day) => sum + day.count, 0),
        });
    }

    return weeks;
}

function AppointmentsChart({ data }) {
    const [granularity, setGranularity] = useState('day');
    const chartData = useMemo(() => {
        if (granularity === 'week') {
            return bucketByWeek(data);
        }

        return data.slice(data.length - 14);
    }, [data, granularity]);
    const hasAppointments = chartData.some((point) => point.count > 0);

    return (
        <ChartCard
            title="Appuntamenti"
            subtitle={granularity === 'day' ? 'Ultimi 14 giorni' : 'Ultime settimane'}
            action={
                <SegmentedToggle
                    value={granularity}
                    onChange={setGranularity}
                    options={[
                        { value: 'day', label: 'Giorno' },
                        { value: 'week', label: 'Settimana' },
                    ]}
                />
            }
        >
            {hasAppointments ? (
                <div className="h-64 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barCategoryGap="30%">
                            <CartesianGrid vertical={false} stroke={CHROME.grid} />
                            <XAxis
                                dataKey="date"
                                tickFormatter={dayLabel}
                                tick={{ fill: CHROME.axis, fontSize: 12 }}
                                axisLine={{ stroke: CHROME.grid }}
                                tickLine={false}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={{ fill: CHROME.axis, fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                                width={32}
                            />
                            <Tooltip
                                cursor={{ fill: CHROME.grid }}
                                content={
                                    <ChartTooltip
                                        formatter={(value) => `${value} appuntament${value === 1 ? 'o' : 'i'}`}
                                        labelFormatter={(entry) => dayLabel(entry.payload.date)}
                                    />
                                }
                            />
                            <Bar
                                dataKey="count"
                                fill={CATEGORICAL[1]}
                                radius={[4, 4, 0, 0]}
                                maxBarSize={24}
                                isAnimationActive
                                animationDuration={500}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <EmptyState message="Nessun appuntamento in questo periodo." />
            )}
        </ChartCard>
    );
}

function QuoteAcceptanceChart({ acceptance }) {
    const segments = [
        { key: 'accepted', name: 'Accettati', value: acceptance.accepted, color: STATUS.good },
        { key: 'pending', name: 'In attesa', value: acceptance.pending, color: STATUS.neutral },
        { key: 'rejected', name: 'Rifiutati', value: acceptance.rejected, color: STATUS.critical },
    ].filter((segment) => segment.value > 0);

    return (
        <ChartCard title="Tasso di accettazione preventivi" subtitle="Sui preventivi emessi (esclude le bozze)">
            {acceptance.issued > 0 ? (
                <div className="flex flex-col items-center gap-6 sm:flex-row">
                    <div className="relative h-48 w-48 shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={segments}
                                    dataKey="value"
                                    nameKey="name"
                                    innerRadius="70%"
                                    outerRadius="100%"
                                    paddingAngle={segments.length > 1 ? 2 : 0}
                                    stroke="#FFFFFF"
                                    strokeWidth={2}
                                    isAnimationActive
                                    animationDuration={500}
                                >
                                    {segments.map((segment) => (
                                        <Cell key={segment.key} fill={segment.color} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    content={
                                        <ChartTooltip
                                            formatter={(value, entry) =>
                                                `${value} (${Math.round((value / acceptance.issued) * 100)}%)`
                                            }
                                        />
                                    }
                                />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-3xl font-semibold text-ink">{acceptance.rate}%</span>
                            <span className="text-xs text-ink-secondary">accettazione</span>
                        </div>
                    </div>

                    <ul className="space-y-2 text-sm">
                        {segments.map((segment) => (
                            <li key={segment.key} className="flex items-center gap-2">
                                <span
                                    className="inline-block h-2.5 w-2.5 shrink-0 rounded-sm"
                                    style={{ backgroundColor: segment.color }}
                                />
                                <span className="text-ink-secondary">{segment.name}</span>
                                <span className="font-semibold text-ink">{segment.value}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <EmptyState message="Nessun preventivo emesso ancora." />
            )}
        </ChartCard>
    );
}

function AppointmentsByTypeChart({ data }) {
    const sorted = [...data].sort((a, b) => b.total - a.total);
    const height = Math.max(160, sorted.length * 40);

    return (
        <ChartCard title="Appuntamenti per tipo" subtitle="Esclusi gli annullati">
            {sorted.length > 0 ? (
                <div className="w-full" style={{ height }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={sorted}
                            layout="vertical"
                            margin={{ top: 0, right: 24, left: 0, bottom: 0 }}
                            barCategoryGap="35%"
                        >
                            <CartesianGrid horizontal={false} stroke={CHROME.grid} />
                            <XAxis
                                type="number"
                                allowDecimals={false}
                                hide
                                domain={[0, (max) => Math.ceil(max * 1.18)]}
                            />
                            <YAxis
                                type="category"
                                dataKey="name"
                                tick={{ fill: '#2B241C', fontSize: 13 }}
                                axisLine={false}
                                tickLine={false}
                                width={110}
                            />
                            <Tooltip
                                cursor={{ fill: CHROME.grid }}
                                content={<ChartTooltip formatter={(value) => `${value} appuntamenti`} />}
                            />
                            <Bar
                                dataKey="total"
                                radius={[0, 4, 4, 0]}
                                maxBarSize={24}
                                isAnimationActive
                                animationDuration={500}
                                label={{ position: 'right', fill: '#2B241C', fontSize: 13 }}
                            >
                                {sorted.map((entry) => (
                                    <Cell key={entry.name} fill={colorForCategory(entry.name)} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <EmptyState message="Nessun appuntamento con un tipo assegnato." />
            )}
        </ChartCard>
    );
}

function EmptyState({ message }) {
    return (
        <div className="flex h-40 items-center justify-center text-sm text-ink-secondary">
            {message}
        </div>
    );
}

export default function Admin({ summary, monthlyRevenue, appointmentsDaily, quoteAcceptance, appointmentsByType }) {
    return (
        <AuthenticatedLayout header={<PageHeading>Dashboard</PageHeading>}>
            <Head title="Dashboard" />

            <div className="py-16">
                <div className="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Appuntamenti di oggi"
                            value={<AnimatedNumber value={summary.todayAppointments} />}
                            href={route('agenda.index')}
                        />
                        <StatCard
                            title="Tasso di accettazione preventivi"
                            value={<AnimatedNumber value={summary.quoteAcceptanceRate} format={(v) => `${v.toFixed(1)}%`} />}
                            caption={
                                quoteAcceptance.issued > 0
                                    ? `${quoteAcceptance.accepted} accettati su ${quoteAcceptance.issued} emessi`
                                    : 'Nessun preventivo emesso ancora'
                            }
                            href={route('quotes.index')}
                        />
                        <StatCard
                            title="Fatturato del mese"
                            value={<AnimatedNumber value={summary.monthlyRevenue} format={(v) => CURRENCY.format(Math.round(v))} />}
                            href={route('billing.index')}
                        />
                        <StatCard
                            title="Pazienti attivi"
                            value={<AnimatedNumber value={summary.activePatients} />}
                            caption="Inattivi: in arrivo con il modulo recall"
                            href={route('patients.index')}
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <RevenueChart data={monthlyRevenue} />
                        <AppointmentsChart data={appointmentsDaily} />
                        <QuoteAcceptanceChart acceptance={quoteAcceptance} />
                        <AppointmentsByTypeChart data={appointmentsByType} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
