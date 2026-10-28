<?php

namespace App\Exports;

class EmployeeLeaveBalancesExport extends AbstractLeaveReportExport
{
    public function headings(): array
    {
        return [
            'Employee Name',
            'Date of Joining',
            'Leave Type',
            'Allocation Type',
            'Allocated Days',
            'Used Days',
            'Remaining Days',
            'Valid From',
            'Valid Until',
            'Expiry Date',
        ];
    }
}
