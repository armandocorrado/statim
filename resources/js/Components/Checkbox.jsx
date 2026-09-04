export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-slate-300 text-blue-800 shadow-sm focus:ring-2 focus:ring-blue-700/30 ' +
                className
            }
        />
    );
}
