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
        'qr.scan_request' => [
            'title' => '{technician} raised {sr_number} from a QR scan',
            'body'  => 'Asset {asset_code}: {request_title}',
            'email' => true,
        ],
    ],
];
