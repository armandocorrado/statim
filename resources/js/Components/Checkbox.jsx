export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-ink/15 text-brand shadow-sm focus:ring-2 focus:ring-brand/30 ' +
                className
            }
        />
    );
}
