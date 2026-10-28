<?php

namespace App\Exports;

class MonthlyLeaveBalanceSummaryExport extends AbstractLeaveReportExport
{
    public function headings(): array
    {
        return [
            'Employee Name',
            'Date of Joining',
            'Leave Type',
            'Leave Allocated',
            'Carry Forward Received',
            'Carry Forward Transferred',
            'Leave Deducted',
            'Leave Reversed',
            'Leave Expired',
            'Net Balance Change',
        ];
    }
}
