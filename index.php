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

    return filter_var($value, FILTER_SANITIZE_STRING);
}

function check_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP);
}

openlog("DDNS-Provider", LOG_PID | LOG_PERROR, LOG_LOCAL0);

$ip = check_ip(get_value('REMOTE_ADDRE', $_SERVER));
if($ip === false) {
    syslog(LOG_WARN, "No IP received.");
    exit(1);
}

$user = get_value('PHP_AUTH_USER', $_SERVER);
if($user === false) {
    syslog(LOG_WARN, "No user given by connection from $ip");
    exit(2);
}

$pwd = get_value('PHP_AUTH_PW', $_SERVER);
if($pwd === false) {
    syslog(LOG_WARN, "No password given by connection from $ip with user $user");
    exit(3);
}

$domain = get_value('DOMAIN');
if($domain === false) {
    syslog(LOG_WARN, "User $user from $ip didn't provide any domain");
    exit(4);
}

$parts = explode('.', $domain);

$domain  = implode('.', array_slice($parts, -2, 2));
$subdomain = implode('.', array_slice($parts, 0, -2));

$config = file_get_contents('template.config.py');

$secret = file_get_contents('secret');
if(empty($secret)) {
    syslog(LOG_WARN, "No secret found.");
    exit(5);
}

file_put_contents('config.py', sprintf($config,
    $secret,
    $domain,
    $subdomain,
    $ip
));
exec('./gandi-live-dns.py', $output, $status);
syslog(LOG_INFO, implode("\n", $output));

closelog();

exit($status);
