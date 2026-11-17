<?php
// Copy privately to config/invoice-reader.php on the server, outside public.
// Never commit the copy with credentials. Enable only after setting endpoint/key.
return [
    'enabled' => false,
    'endpoint' => 'https://YOUR-RESOURCE.cognitiveservices.azure.com',
    'key' => getenv('MOBA_INVOICE_READER_KEY') ?: '',
];
