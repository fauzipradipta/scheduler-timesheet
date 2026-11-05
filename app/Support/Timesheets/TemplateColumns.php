<?php

namespace App\Support\Timesheets;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Finds the columns a template author added to the right of the fixed layout,
 * by the headings they wrote for them. Each one becomes a field the user fills
 * in per day, and the export writes it back under the same heading.
 *
 * @phpstan-type Column array{column: string, label: string}
 */
final class TemplateColumns
{
    /**
     * @return list<Column> ordered left to right
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $strings = self::sharedStrings($zip);
        $zip->close();

        if ($sheet === false) {
            return [];
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($sheet)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', TimesheetLayout::SPREADSHEET_NAMESPACE);

        $lastFixed = TimesheetLayout::columnIndex(TimesheetLayout::LAST_FIXED_COLUMN);

        /** @var array<string, string> $labels column letter => heading */
        $labels = [];

        /** The upper heading row wins, so a heading merged over both rows is read once. */
        foreach (TimesheetLayout::HEADER_ROWS as $row) {
            $cells = $xpath->query('//x:sheetData/x:row[@r="'.$row.'"]/x:c');

            foreach ($cells === false ? [] : $cells as $cell) {
                if (! $cell instanceof DOMElement || preg_match('/^([A-Z]+)\d+$/', $cell->getAttribute('r'), $matches) !== 1) {
                    continue;
                }

                $column = $matches[1];

                if (TimesheetLayout::columnIndex($column) <= $lastFixed || isset($labels[$column])) {
                    continue;
                }

                $label = self::textOf($cell, $strings);

                if ($label !== '') {
                    $labels[$column] = $label;
                }
            }
        }

        uksort($labels, fn (string $first, string $second): int => TimesheetLayout::columnIndex($first) <=> TimesheetLayout::columnIndex($second));

        $columns = [];
        $seen = [];

        foreach ($labels as $column => $label) {
            /** Values are stored by heading, so two columns under one heading would share a value. */
            if (isset($seen[mb_strtolower($label)])) {
                continue;
            }

            $seen[mb_strtolower($label)] = true;
            $columns[] = ['column' => $column, 'label' => $label];
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($xml)) {
            return [];
        }

        $strings = [];

        foreach ($document->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'si') as $item) {
            $strings[] = $item->textContent;
        }

        return $strings;
    }

    /**
     * A heading may be a shared string, an inline string, or a plain value.
     *
     * @param  array<int, string>  $strings
     */
    private static function textOf(DOMElement $cell, array $strings): string
    {
        if ($cell->getAttribute('t') === 's') {
            $index = $cell->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'v')->item(0);

            $text = $index === null ? '' : ($strings[(int) $index->textContent] ?? '');
        } else {
            /** A formula's own text is not a heading; only its cached value is. */
            $value = $cell->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, $cell->getAttribute('t') === 'inlineStr' ? 'is' : 'v')->item(0);

            $text = $value === null ? '' : $value->textContent;
        }

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
