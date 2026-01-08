<?php
return [
    // true kalau production
    'server_key' => getenv('MIDTRANS_SERVER_KEY') ?: '',
  'client_key' => getenv('MIDTRANS_CLIENT_KEY') ?: '',
  'is_production' => (getenv('MIDTRANS_IS_PROD') === 'true'),

    // harga PRO (contoh)
    'pro_price' => 25000,

    // snap redirect endpoint
    'snap_url_sandbox' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
    'snap_url_prod'    => 'https://app.midtrans.com/snap/v1/transactions',

    'status_url_sandbox' => 'https://api.sandbox.midtrans.com/v2/%s/status',
    'status_url_prod'    => 'https://api.midtrans.com/v2/%s/status',

];
