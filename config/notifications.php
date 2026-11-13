<?php

/*
 | Notification events (C1 foundation). In-app and email only for now.
 | Placeholders in title/body use {name} and are filled from the values passed to notify().
 | Push and WhatsApp come later.
 */
return [
    'events' => [
        'sr.contract_choice_needed' => [
            'title' => 'Contract choice needed for {sr_number}',
            'body'  => '{count} contracts match this request. Choose the one that applies before you approve.',
            'email' => true,
        ],
        'contract.cap_reached' => [
            'title' => 'Call cap reached on {contract_number}',
            'body'  => '{contract_number} has used {used} of {max} calls. Decide how to handle {sr_number}.',
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
