<?php

function get_value($name, $data = null) {
    $value = false;
    if(is_null($data)) {
        $data = [$_POST, $_GET];
    }

    foreach([$name, strtolower($name)] as $key) {
        if($value === false) {
            foreach($data as $d) {
                $value = isset($d[$key]) ? $d[$key] : false;
            }
        }
    }

    return $value === false ? $value : filter_var($value, FILTER_SANITIZE_STRING);
}

function check_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP);
}

function error($code, $message) {
    http_response_code($code);
    header($message);
    die($message);
}

openlog("DDNS-Provider", LOG_PID | LOG_PERROR, LOG_LOCAL0);

$ip = check_ip(get_value('myip'));
if($ip === false) {
    $ip = check_ip(get_value('ip'));
}
if($ip === false) {
    $ip = check_ip(get_value('dnsto'));
}
if($ip === false) {
    $ip = check_ip(get_value('REMOTE_ADDR', [$_SERVER]));
}
if($ip === false) {
    syslog(LOG_WARNING, "No IP received.");
    exit(1);
}

$user = get_value('PHP_AUTH_USER', [$_SERVER]);
$pwd = get_value('PHP_AUTH_PW', [$_SERVER]);
if($user === false || $pwd === false) {
    $user = get_value('domain');
    $pwd = get_value('password');
}
if($user === false || $pwd === false) {
    $user = get_value('token');
    $pwd = '';
}
if($user === false || $pwd === false) {
    syslog(LOG_WARNING, " No token provided from $ip");
    error(403, 'HTTP/1.0 403 Forbidden');
}
$token = $user.$pwd;
$check = trim(file_get_contents('token'));
if($token !== $check) {
    syslog(LOG_WARNING, "Token ($token $check) did not match for $user from $ip");
    error(401, 'HTTP/1.0 401 Unauthorized');
}

$domain = get_value('hostname');
if($domain === false || $domain === 'YES') {
    $domain = get_value('host_id');
}
if($domain === false) {
    $domain = get_value('host');
}
if($domain === false) {
    syslog(LOG_WARNING, "User $user from $ip didn't provide any domain");
    error(400, 'HTTP/1.0 400 Bad request');
}

$parts = explode('.', $domain);
$domain  = implode('.', array_slice($parts, -2, 2));
$subdomain = implode('.', array_slice($parts, 0, -2));

$secret = trim(file_get_contents('secret'));
if(empty($secret)) {
    syslog(LOG_WARNING, "No secret found.");
    error(501, 'HTTP/1.0 501 Not implemented');
}

$config = file_get_contents('template.config.py');
file_put_contents('config.py', sprintf($config,
    $secret,
    $domain,
    $subdomain,
    $ip
));
$out = exec('python gandi-live-dns.py', $output, $status);

if($status !== 0) {
    syslog(LOG_WARNING, "status: $status");
    syslog(LOG_WARNING, "out: $out");
    syslog(LOG_WARNING, "output: ".print_r($output, true));
    error(500, 'HTTP/1.0 500 Internal server error');
}

closelog();

http_response_code(200);
echo "good\n";
