<?php

namespace ChurchCRM\Reports;

class PdfAbsenteeReport extends ChurchInfoReport
{
    private const COL_NAME = 90;
    private const COL_CELL = 45;
    private const COL_HOME = 45;
    private const ROW_HEIGHT = 8;

    public function __construct(string $churchName, string $dateYmd)
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetMargins(12, 12);
        $this->SetAutoPageBreak(true, 15);
        $this->addPage();

        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 10, self::convertToLatin1($churchName), 0, 1, 'L');

        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 8, self::convertToLatin1(sprintf(gettext('Members who did not attend on %s'), $dateYmd)), 0, 1, 'L');
        $this->Ln(4);

        $this->drawHeaderRow();
    }

    private function drawHeaderRow(): void
    {
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(self::COL_NAME, self::ROW_HEIGHT, self::convertToLatin1(gettext('Name')), 1, 0, 'L', true);
        $this->Cell(self::COL_CELL, self::ROW_HEIGHT, self::convertToLatin1(gettext('Cell Phone')), 1, 0, 'L', true);
        $this->Cell(self::COL_HOME, self::ROW_HEIGHT, self::convertToLatin1(gettext('Home Phone')), 1, 1, 'L', true);
        $this->SetFont('Helvetica', '', 10);
    }

    /** @param list<array{firstName: string, lastName: string, cellPhone: string, homePhone: string}> $rows */
    public function addRows(array $rows): void
    {
        foreach ($rows as $row) {
            if ($this->GetY() > $this->PageBreakTrigger) {
                $this->addPage();
                $this->drawHeaderRow();
            }
            $name = trim($row['lastName'] . ', ' . $row['firstName']);
            $this->Cell(self::COL_NAME, self::ROW_HEIGHT, self::convertToLatin1($name), 1, 0, 'L');
            $this->Cell(self::COL_CELL, self::ROW_HEIGHT, self::convertToLatin1($row['cellPhone']), 1, 0, 'L');
            $this->Cell(self::COL_HOME, self::ROW_HEIGHT, self::convertToLatin1($row['homePhone']), 1, 1, 'L');
        }

        if ($rows === []) {
            $this->Cell(self::COL_NAME + self::COL_CELL + self::COL_HOME, self::ROW_HEIGHT, self::convertToLatin1(gettext('Everyone attended — no absentees.')), 1, 1, 'C');
        }
    }
}
