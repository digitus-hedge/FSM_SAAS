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
        'batch.unsafe_asset' => [
            'title' => '{sr_number}: {count} asset(s) failed as unsafe',
            'body'  => 'The technician marked assets as failed (unsafe) in this batch.',
            'email' => true,
        ],
    ],
];
