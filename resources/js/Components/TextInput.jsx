import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'block rounded-control border-ink/15 px-3.5 py-2.5 text-sm text-ink shadow-sm transition placeholder:text-ink-secondary/60 focus:border-brand focus:ring-2 focus:ring-brand/20 ' +
                className
            }
            ref={localRef}
        />
    );
});
