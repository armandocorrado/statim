export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center justify-center rounded-control border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink shadow-soft transition duration-150 ease-in-out hover:bg-cream focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
