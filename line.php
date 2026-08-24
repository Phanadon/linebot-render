<?php

$raw = file_get_contents("php://input");

file_put_contents(
    __DIR__ . "/log.txt",
    date("Y-m-d H:i:s") .
    " | METHOD=" . ($_SERVER["REQUEST_METHOD"] ?? "") .
    " | LENGTH=" . strlen($raw) .
    " | TYPE=" . ($_SERVER["CONTENT_TYPE"] ?? "") .
    " | RAW=" . $raw . "\n",
    FILE_APPEND
);

http_response_code(200);
header("Content-Type: text/plain");
echo "OK";