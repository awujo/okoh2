<?php
header('Content-Type: text/plain');

// Dump all environment variables PHP knows about
echo "=== \$_ENV ===\n";
var_dump($_ENV);

echo "\n=== getenv('SMTP_HOST') ===\n";
var_dump(getenv('SMTP_HOST'));

echo "\n=== getenv('SMTP_USER') ===\n";
var_dump(getenv('SMTP_USER'));

echo "\n=== getenv('SMTP_PASS') ===\n";
var_dump(getenv('SMTP_PASS'));

echo "\n=== getenv('SMTP_PORT') ===\n";
var_dump(getenv('SMTP_PORT'));
