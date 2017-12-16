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

openlog("DDNS-Provider", LOG_PID | LOG_PERROR, LOG_LOCAL0);

$ip = check_ip(get_value('myip'));
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
    syslog(LOG_WARNING, " No token provided from $ip");
    exit(2);
}
$token = $user.$pwd;
$check = trim(file_get_contents('token'));
if($token !== $check) {
    syslog(LOG_WARNING, "Token ($token $check) did not match for $user from $ip");
    exit(2);
}

$domain = get_value('hostname');
if($domain === false) {
    syslog(LOG_WARNING, "User $user from $ip didn't provide any domain");
    exit(4);
}

$parts = explode('.', $domain);

$domain  = implode('.', array_slice($parts, -2, 2));
$subdomain = implode('.', array_slice($parts, 0, -2));

$config = file_get_contents('template.config.py');

$secret = trim(file_get_contents('secret'));
if(empty($secret)) {
    syslog(LOG_WARNING, "No secret found.");
    exit(5);
}

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
}

closelog();

exit($status);

