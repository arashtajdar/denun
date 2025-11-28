<?php
// Simple health check - just return OK without database
header('Content-Type: text/plain');
echo 'OK';
http_response_code(200);
