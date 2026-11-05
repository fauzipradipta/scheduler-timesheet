type ExtraColumnFieldsProps = {
    /** The headings the user's template adds after the remark column. */
    columns: string[];
    values: Record<string, string>;
    onChange: (values: Record<string, string>) => void;
    idPrefix: string;
};

/**
 * One input per column the user's timesheet template adds, so a day can
 * carry a value for each of them into the export.
 */
export default function ExtraColumnFields({
    columns,
    values,
    onChange,
    idPrefix,
}: ExtraColumnFieldsProps) {
    if (columns.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {columns.map((column, index) => (
                <label
                    key={column}
                    htmlFor={`${idPrefix}-${index}`}
                    className="flex flex-col gap-1 text-sm"
                >
                    {column}
                    <input
                        id={`${idPrefix}-${index}`}
                        type="text"
                        value={values[column] ?? ''}
                        onChange={(event) =>
                            onChange({
                                ...values,
                                [column]: event.target.value,
                            })
                        }
                        maxLength={255}
                        className="w-full rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none placeholder:text-[#a3a29e] focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC]"
                    />
                </label>
            ))}
        </div>
    );
}
