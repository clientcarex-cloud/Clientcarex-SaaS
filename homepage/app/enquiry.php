<?php
/**
 * Growth-audit enquiry handling for /contact.
 *
 * Stateless CSRF (an HMAC-signed timestamp) so no session cookie is set and
 * the rest of the site stays fully cacheable. Every enquiry is appended to
 * storage/enquiries.log before mail is attempted, so nothing is ever lost if
 * the host's mail transport is down or unconfigured.
 */
declare(strict_types=1);

const TOKEN_LIFETIME = 7200; // 2 hours
const TOKEN_MIN_AGE  = 2;    // a bot fills the form faster than this

/** Signing key, created on first use. */
function secret(): string
{
    $file = ROOT . '/storage/secret.key';
    if (is_file($file)) {
        return (string) file_get_contents($file);
    }

    $key = bin2hex(random_bytes(32));
    @mkdir(dirname($file), 0775, true);
    file_put_contents($file, $key, LOCK_EX);

    return $key;
}

function csrf_token(): string
{
    $ts = (string) time();

    return $ts . '.' . hash_hmac('sha256', $ts, secret());
}

function csrf_valid(string $token): bool
{
    [$ts, $mac] = array_pad(explode('.', $token, 2), 2, '');
    if ($ts === '' || !ctype_digit($ts)) {
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', $ts, secret()), $mac)) {
        return false;
    }

    $age = time() - (int) $ts;

    return $age >= TOKEN_MIN_AGE && $age <= TOKEN_LIFETIME;
}

/** One-line-per-enquiry JSON log, so no request is dropped. */
function log_enquiry(array $data, bool $mailed): void
{
    $file = ROOT . '/storage/enquiries.log';
    @mkdir(dirname($file), 0775, true);
    file_put_contents(
        $file,
        json_encode($data + ['mailed' => $mailed, 'at' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/** Strip anything that could forge a mail header. */
function header_safe(string $v): string
{
    return trim(str_replace(["\r", "\n"], ' ', $v));
}

/**
 * Validate and deliver a submission.
 *
 * @return array{errors: array<string,string>, values: array<string,string>}
 */
function handle_enquiry(): array
{
    $field = static fn (string $k): string => header_safe((string) ($_POST[$k] ?? ''));

    $values = [
        'name'    => $field('name'),
        'company' => $field('company'),
        'email'   => $field('email'),
        'phone'   => $field('phone'),
        'interest' => $field('interest'),
        'revenue'  => $field('revenue'),
        'message'  => trim((string) ($_POST['message'] ?? '')),
    ];

    // Honeypot: a real browser never fills a hidden field.
    if (($_POST['website'] ?? '') !== '') {
        redirect(url('contact') . '?sent=1');
    }

    $errors = [];
    if (!csrf_valid((string) ($_POST['token'] ?? ''))) {
        $errors['form'] = 'Your session expired. Please send the form again.';
    }
    if ($values['name'] === '') {
        $errors['name'] = 'Please tell us your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid work email.';
    }
    if (mb_strlen($values['message']) > 5000) {
        $errors['message'] = 'Please keep the message under 5,000 characters.';
    }

    if ($errors) {
        return ['errors' => $errors, 'values' => $values];
    }

    $body = '';
    foreach ($values as $label => $value) {
        $body .= ucfirst($label) . ": " . ($value === '' ? '—' : $value) . "\n";
    }

    $domain = (string) parse_url(SITE_URL, PHP_URL_HOST);
    $mailed = @mail(
        MAIL_TO,
        'Growth audit request — ' . $values['name'] . ($values['company'] !== '' ? ' (' . $values['company'] . ')' : ''),
        $body,
        [
            'From'         => SITE_NAME . ' <no-reply@' . $domain . '>',
            'Reply-To'     => $values['email'],
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]
    );

    log_enquiry($values, $mailed);
    redirect(url('contact') . '?sent=1');
}

function redirect(string $to, int $status = 303): never
{
    header('Location: ' . $to, true, $status);
    exit;
}
