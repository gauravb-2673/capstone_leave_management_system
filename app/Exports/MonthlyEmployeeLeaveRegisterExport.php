<?php

namespace App\Exports;

class MonthlyEmployeeLeaveRegisterExport extends AbstractLeaveReportExport
{
    public function headings(): array
    {
        return [
            'Employee Name',
            'Date of Joining',
            'Leave Type',
            'Leave Start Date',
            'Leave End Date',
            'Duration',
            'Leave Days',
            'Reason for Leave',
        ];
    }
}
