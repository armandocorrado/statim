export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `mb-1.5 block text-sm font-medium text-ink ` +
                className
            }
        >
            {value ? value : children}
        </label>
    );
}
