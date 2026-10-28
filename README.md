Capstone : Leave Management

Whenever you want to allocate leave balances, run this command from your project directory:
php artisan leave:allocate-balances

Useful commands

1. Preview allocations without saving anything
   php artisan leave:allocate-balances --dry-run

2. Allocate balances for a specific date
   php artisan leave:allocate-balances --date=2026-10-28

3. Verify the scheduled tasks
   php artisan schedule:list

=============================================================================================================

| Functionality                                  | Existing implementation                          |
| ---------------------------------------------- | ------------------------------------------------ |
| Employee cancels their own pending application | `cancelPendingApplication()`                     |
| Admin cancels an approved application          | `cancelApprovedApplication()`                    |
| Refund paid leave to the original allocation   | `refundPaidLeave()`                              |
| Prevent duplicate refunds                      | Checks `reverses_transaction_id`                 |
| Record cancellation reason and timestamp       | Updates `cancellation_reason` and `cancelled_at` |
| Skip paid-leave refunds for LWP                | Checks the leave type code                       |

=============================================================================================================

1. Monthly Leave Transaction Report
   | Excel column | What it tells the user |
   |---|---|
   | Employee Name | Who the transaction belongs to |
   | Date of Joining | When the employee joined |
   | Leave Type | Casual Leave, Sick Leave, etc. |
   | Transaction Date | When the balance change was recorded |
   | Transaction Type | Allocation, Deduction, Reversal, Carry Forward or Expiry |
   | Days Added | Days credited to the balance |
   | Days Deducted | Days removed from the balance |
   | Balance After Transaction | Balance recorded after the transaction |
   | Leave Start Date | Start of the related leave, if applicable |
   | Leave End Date | End of the related leave, if applicable |
   | Description | Explanation of the balance change |

    SELECT
    u.name AS `Employee Name`,
    e.joining_date AS `Date of Joining`,
    lt.name AS `Leave Type`,

    lbt.created_at AS `Transaction Date`,
    lbt.transaction_type AS `Transaction Type`,

    CASE
    WHEN lbt.transaction_type IN (
    'allocation',
    'carry_forward',
    'reversal'
    )
    THEN lbt.days
    ELSE 0
    END AS `Days Added`,

    CASE
    WHEN lbt.transaction_type IN (
    'deduction',
    'carry_forward_out',
    'expiry'
    )
    THEN lbt.days
    ELSE 0
    END AS `Days Deducted`,

    lbt.balance_after AS `Balance After Transaction`,

    la.leave_from_date AS `Leave Start Date`,
    la.leave_to_date AS `Leave End Date`,

    lbt.description AS `Description`

FROM leave_balance_transactions lbt

INNER JOIN leavebalances lb
ON lbt.leave_balance_id = lb.id

INNER JOIN employees e
ON lb.employee_id = e.id

LEFT JOIN users u
ON e.user_id = u.id

INNER JOIN leavetypes lt
ON lb.leave_type_id = lt.id

LEFT JOIN leaveapplications la
ON lbt.leave_application_id = la.id

WHERE lbt.created_at >= '2026-10-01'
AND lbt.created_at < '2026-11-01'

ORDER BY
u.name,
lbt.created_at;

2. Employee Leave Balance Report

| Excel column    | What it tells the user        |
| --------------- | ----------------------------- |
| Employee Name   | Employee's name               |
| Date of Joining | Employee's joining date       |
| Leave Type      | Type of leave                 |
| Allocation Type | Annual Leave or Carry Forward |
| Allocated Days  | Days originally granted       |
| Used Days       | Net balance consumed          |
| Remaining Days  | Days currently available      |
| Valid From      | Start of the balance period   |
| Valid Until     | End of the balance period     |
| Expiry Date     | Date the balance expires      |

SELECT
u.name AS `Employee Name`,
e.joining_date AS `Date of Joining`,

    lt.name AS `Leave Type`,
    lb.allocation_type AS `Allocation Type`,

    lb.allocated_days AS `Allocated Days`,

    lb.allocated_days - lb.remaining_days
        AS `Used Days`,

    lb.remaining_days AS `Remaining Days`,

    lb.period_start AS `Valid From`,
    lb.period_end AS `Valid Until`,
    lb.expires_at AS `Expiry Date`

FROM leavebalances lb

INNER JOIN employees e
ON lb.employee_id = e.id

LEFT JOIN users u
ON e.user_id = u.id

INNER JOIN leavetypes lt
ON lb.leave_type_id = lt.id

ORDER BY
u.name,
lt.name,
lb.period_start;

3. Monthly Leave Balance Summary

| Excel column              | What it tells the user               |
| ------------------------- | ------------------------------------ |
| Employee Name             | Employee's name                      |
| Date of Joining           | Employee's joining date              |
| Leave Type                | Type of leave                        |
| Leave Allocated           | Days allocated during the month      |
| Carry Forward Received    | Days received from previous balances |
| Carry Forward Transferred | Days moved out of previous balances  |
| Leave Deducted            | Days deducted for leave              |
| Leave Reversed            | Days restored                        |
| Leave Expired             | Days that expired                    |
| Net Balance Change        | Overall increase or decrease         |

SELECT
u.name AS `Employee Name`,
e.joining_date AS `Date of Joining`,

    lt.name AS `Leave Type`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'allocation'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Leave Allocated`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'carry_forward'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Carry Forward Received`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'carry_forward_out'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Carry Forward Transferred`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'deduction'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Leave Deducted`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'reversal'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Leave Reversed`,

    SUM(
        CASE
            WHEN lbt.transaction_type = 'expiry'
                THEN lbt.days
            ELSE 0
        END
    ) AS `Leave Expired`,

    SUM(
        CASE
            WHEN lbt.transaction_type IN (
                'allocation',
                'carry_forward',
                'reversal'
            )
                THEN lbt.days

            WHEN lbt.transaction_type IN (
                'deduction',
                'carry_forward_out',
                'expiry'
            )
                THEN -lbt.days

            ELSE 0
        END
    ) AS `Net Balance Change`

FROM leave_balance_transactions lbt

INNER JOIN leavebalances lb
ON lbt.leave_balance_id = lb.id

INNER JOIN employees e
ON lb.employee_id = e.id

LEFT JOIN users u
ON e.user_id = u.id

INNER JOIN leavetypes lt
ON lb.leave_type_id = lt.id

WHERE lbt.created_at >= '2026-10-01'
AND lbt.created_at < '2026-11-01'

GROUP BY
e.id,
u.name,
e.joining_date,
lt.id,
lt.name

ORDER BY
u.name,
lt.name;

4. Monthly Employee Leave Register
   | Excel column | What it tells the user |
   |---|---|
   | Employee Name | Employee's name |
   | Date of Joining | Employee's joining date |
   | Leave Type | Type of leave |
   | Leave Start Date | First date of the leave |
   | Leave End Date | Last date of the leave |
   | Duration | Half Day, Full Day or Multiple Days |
   | Leave Days | Recorded leave deduction count |
   | Reason for Leave | Employee's stated reason |

    SELECT
    u.name AS `Employee Name`,
    e.joining_date AS `Date of Joining`,

    lt.name AS `Leave Type`,

    la.leave_from_date AS `Leave Start Date`,
    la.leave_to_date AS `Leave End Date`,

    d.name AS `Duration`,

    la.leavedeductcount AS `Leave Days`,
    la.leavereason AS `Reason for Leave`

FROM leaveapplications la

INNER JOIN employees e
ON la.employee_id = e.id

LEFT JOIN users u
ON e.user_id = u.id

INNER JOIN leavetypes lt
ON la.leave_type_id = lt.id

LEFT JOIN durations d
ON la.duration_id = d.id

WHERE la.leave_from_date < '2026-11-01'
AND la.leave_to_date >= '2026-10-01'

AND EXISTS (
SELECT 1
FROM leaveapplicationapprovals laa
WHERE laa.leave_application_id = la.id
AND laa.status = 'approved'
)

ORDER BY
u.name,
la.leave_from_date;
