<?php

namespace App\Services\Export;

use App\Services\Audit\AuditLogger;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes a simple one-sheet Excel file and records the export through the AuditLogger (CLAUDE.md: audit is append-only
 * and goes through the one service). Text cells are always written as text, so a value that starts with = + - or @
 * can never run as a formula when someone opens the file.
 */
class XlsxExporter
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * @param string[]  $headings
     * @param iterable<array<int, scalar|null>> $rows  each row is a list of cell values; numbers stay numbers
     * @param array     $audit  ['action' => 'ledger.exported', 'tenant_id' => 1, 'new' => [...filters, row count]]
     */
    public function download(string $filename, string $sheetTitle, array $headings, iterable $rows, array $audit): BinaryFileResponse
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetTitle), 0, 31));

        $r = 1;
        foreach ([$headings, ...$rows] as $row) {
            $c = 1;
            foreach ($row as $value) {
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue([$c, $r], $value);
                } else {
                    $sheet->setCellValueExplicit([$c, $r], (string) $value, DataType::TYPE_STRING);
                }
                $c++;
            }
            $r++;
        }
        $sheet->getStyle([1, 1, max(1, count($headings)), 1])->getFont()->setBold(true);
        foreach (range(1, max(1, count($headings))) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        $this->audit->log($audit['action'], [
            'tenant_id' => $audit['tenant_id'],
            'item_type' => 'export',
            'new'       => $audit['new'] ?? null,
        ]);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
