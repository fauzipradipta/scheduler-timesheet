export type Holiday = {
    name: string;
    /** A cuti bersama, which bridges a holiday to the weekend around it. */
    joint: boolean;
    /** A date the government has announced but not yet confirmed. */
    tentative: boolean;
};

/** The days off of a year, keyed by ISO date. */
export type Holidays = Record<string, Holiday>;
