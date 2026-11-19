<?php

/*
 | Notification events (C1 foundation). In-app and email only for now.
 | Placeholders in title/body use {name} and are filled from the values passed to notify().
 | Push and WhatsApp come later.
 */
return [
    'events' => [
        'sr.contract_choice_needed' => [
            'title' => 'Choose a contract for {sr_number}',
            'body'  => '{client}, {site}. {count} contracts match. Choose the one that applies.',
            'email' => true,
        ],
        'contract.cap_reached' => [
            'title' => 'Contract {contract_number} has reached its call cap',
            'body'  => '{client}. {used} of {max} calls used. {sr_number} needs your decision.',
            'email' => true,
        ],
        'contract.expiring' => [
            'title' => 'Contract {contract_number} ends in {days} days',
            'body'  => '{client}. It ends on {date}. Renew it or let it end.',
            'email' => true,
        ],
        // LED-05 (Currency and payments spec, section 7): the Finance email for each overdue reminder.
        'invoice.overdue' => [
            'title' => 'Reminder {n} sent for invoice {invoice_number}',
            'body'  => '{client}. {balance} unpaid, {days} days overdue. Reminder {n} of {max} was sent to {recipients}.',
            'email' => true,
        ],
        'qr.scan_request' => [
            'title' => '{technician} raised {sr_number} from a QR scan',
            'body'  => 'Asset {asset_code}: {request_title}',
            'email' => true,
        ],
        // Forms-Approvals-Batch spec, section 7.
        'sr.auto_approved' => [
            'title' => '{sr_number} was approved automatically',
            'body'  => 'Approved automatically ({mode}). {client}.',
            'email' => false,
        ],
        'sr.approval_needed' => [
            'title' => '{sr_number} needs your approval',
            'body'  => '{client}. {waiting_for} must approve it.',
            'email' => true,
        ],
        'sr.approval_rejected' => [
            'title' => '{sr_number} was not approved',
            'body'  => 'Reason: {reason}',
            'email' => true,
        ],
        // Preventive-Maintenance spec, section 7.
        'pm.generated' => [
            'title' => 'Maintenance request {sr_number} was created',
            'body'  => '{what} at {site}. Due {date}. {note}',
            'email' => true,
        ],
        'pm.overdue' => [
            'title' => 'Maintenance request {sr_number} is overdue',
            'body'  => '{site}. It was due on {date} and is not done.',
            'email' => true,
        ],
        // Multi-Trade-Vendors-Depot spec, section 7.
        'master.child_completed' => [
            'title' => '{sr_number}: a trade is complete',
            'body'  => '{trade} is done on the multi-trade job {master_number}.',
            'email' => false,
        ],
        'master.ready_to_invoice' => [
            'title' => 'Request {sr_number} is complete. Create the invoice.',
            'body'  => 'Every trade is done.',
            'email' => true,
        ],
        'vendor.assigned' => [
            'title' => 'You have a new job {sr_number} at {site} on {date}',
            'body'  => 'Open the job in the app.',
            'email' => true,
        ],
        'depot.received' => [
            'title' => '{sr_number}: we received {asset} at the workshop',
            'body'  => 'The equipment is at the workshop.',
            'email' => true,
        ],
        'depot.ready_for_return' => [
            'title' => '{sr_number}: {asset} is ready to collect',
            'body'  => 'The work is done and the equipment can go back.',
            'email' => true,
        ],
        // Reports-Summaries-Preferences spec D9, D13. sr.assigned and sla.breached are the two events a user cannot turn off.
        'summary.daily' => [
            'title' => 'Your daily summary for {date}',
            'body'  => '{summary}',
            'email' => true,
        ],
        'sr.assigned' => [
            'title' => 'You were assigned {sr_number}',
            'body'  => '{site}. {when}',
            'email' => true,
        ],
        // defined for preferences and the catalogue; nothing in the code detects an SLA breach yet (a follow-up)
        'sla.breached' => [
            'title' => '{sr_number} missed its SLA',
            'body'  => '{client}. The {kind} time was missed.',
            'email' => true,
        ],
        'batch.unsafe_asset' => [
            'title' => '{sr_number}: {count} asset(s) failed as unsafe',
            'body'  => 'The technician marked assets as failed (unsafe) in this batch.',
            'email' => true,
        ],
    ],
];
