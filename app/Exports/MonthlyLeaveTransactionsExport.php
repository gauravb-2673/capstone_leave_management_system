<?php

namespace App\Exports;

class MonthlyLeaveTransactionsExport extends AbstractLeaveReportExport
{
    public function headings(): array
    {
        return [
            'Employee Name',
            'Date of Joining',
            'Leave Type',
            'Transaction Date',
            'Transaction Type',
            'Days Added',
            'Days Deducted',
            'Balance After Transaction',
            'Leave Start Date',
            'Leave End Date',
            'Description',
        ];
    }
}
