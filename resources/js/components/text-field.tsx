import type { ComponentProps } from 'react';

type TextFieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
};

/**
 * A labelled input that shows the validation error the server sent back.
 */
export default function TextField({
    label,
    error,
    id,
    ...props
}: TextFieldProps) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <input
                id={id}
                aria-invalid={error ? true : undefined}
                className="w-full rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none placeholder:text-[#a3a29e] focus:border-[#1b1b18] aria-invalid:border-[#f53003] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC] dark:aria-invalid:border-[#FF4433]"
                {...props}
            />
            {error && (
                <p className="text-sm text-[#f53003] dark:text-[#FF4433]">
                    {error}
                </p>
            )}
        </div>
    );
}
