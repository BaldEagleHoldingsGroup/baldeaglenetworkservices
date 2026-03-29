<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('crm_mode')) {
    function crm_mode(): string
    {
        return strtolower((string) site_config()['crm_mode']);
    }

    function crm_target_label(): string
    {
        $config = site_config();

        if (crm_mode() === 'queue_api') {
            $parts = parse_url($config['intake_api_url']);
            if (!is_array($parts) || empty($parts['host'])) {
                return 'queue_api:unconfigured';
            }

            $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';
            return 'queue_api:' . $parts['host'] . $path;
        }

        if (crm_mode() === 'suitecrm_legacy') {
            $parts = parse_url($config['suitecrm_endpoint']);
            if (!is_array($parts) || empty($parts['host'])) {
                return 'suitecrm_legacy:unconfigured';
            }

            $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';
            return 'suitecrm_legacy:' . $parts['host'] . $path;
        }

        return crm_mode();
    }

    function crm_log(string $event, array $context = []): void
    {
        $file = site_config()['crm_log'];
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'event' => $event,
            'mode' => crm_mode(),
            'target' => crm_target_label(),
        ];

        foreach ($context as $key => $value) {
            $record[$key] = crm_log_sanitize($value);
        }

        file_put_contents($file, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    function crm_log_sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[$key] = crm_log_sanitize($item);
            }

            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        $text = trim((string) $value);
        if (strlen($text) > 400) {
            $text = substr($text, 0, 397) . '...';
        }

        return preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    }

    function crm_lead_ref(array $payload): string
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $requestId = (string) ($payload['request_id'] ?? '');

        return substr(hash('sha256', $email . '|' . $requestId), 0, 16);
    }

    function crm_response_excerpt(?string $body): string
    {
        return crm_log_sanitize($body ?? '');
    }

    function crm_failure(string $reason, array $context = []): array
    {
        return array_merge([
            'success' => false,
            'reason' => $reason,
            'mode' => crm_mode(),
            'target' => crm_target_label(),
        ], $context);
    }

    function crm_name_parts(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['first_name' => '', 'last_name' => 'Website Lead'];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) === 1) {
            return ['first_name' => '', 'last_name' => $parts[0]];
        }

        $firstName = array_shift($parts);

        return [
            'first_name' => $firstName === null ? '' : $firstName,
            'last_name' => implode(' ', $parts),
        ];
    }

    function crm_http_post_form(string $url, array $fields): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['success' => false, 'error' => 'Unable to initialize cURL transport.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($fields),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($body === false) {
                return ['success' => false, 'error' => $error !== '' ? $error : 'HTTP request failed.'];
            }

            return [
                'success' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => (string) $body,
            ];
        }

        $content = http_build_query($fields);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Content-Length: ' . strlen($content),
                ]),
                'content' => $content,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($body === false) {
            return ['success' => false, 'error' => 'HTTP request failed.'];
        }

        return [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $body,
        ];
    }

    function crm_http_post_json(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['success' => false, 'error' => 'JSON encoding failed.'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['success' => false, 'error' => 'Unable to initialize cURL transport.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($body),
                ],
            ]);

            $responseBody = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($responseBody === false) {
                return ['success' => false, 'error' => $error !== '' ? $error : 'HTTP request failed.'];
            }

            return [
                'success' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => (string) $responseBody,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Content-Length: ' . strlen($body),
                ]),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($responseBody === false) {
            return ['success' => false, 'error' => 'HTTP request failed.'];
        }

        return [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }

    function suitecrm_legacy_call(string $method, array $restData): array
    {
        $config = site_config();

        return crm_http_post_form($config['suitecrm_endpoint'], [
            'method' => $method,
            'input_type' => 'JSON',
            'response_type' => 'JSON',
            'rest_data' => json_encode($restData, JSON_UNESCAPED_SLASHES),
        ]);
    }

    function create_suitecrm_legacy_lead(array $payload): array
    {
        $config = site_config();
        if (
            $config['suitecrm_endpoint'] === '' ||
            $config['suitecrm_username'] === '' ||
            $config['suitecrm_password'] === ''
        ) {
            crm_log('crm_attempt', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => crm_lead_ref($payload),
                'result' => 'failed',
                'reason' => 'SuiteCRM credentials are not configured.',
            ]);

            return crm_failure('CRM integration is not configured.');
        }

        $leadRef = crm_lead_ref($payload);
        crm_log('crm_attempt', [
            'request_id' => $payload['request_id'] ?? '',
            'lead_ref' => $leadRef,
            'service_type' => $payload['service_type'] ?? '',
            'module' => 'Leads',
            'methods' => ['login', 'set_entry'],
        ]);

        $login = suitecrm_legacy_call('login', [
            'user_auth' => [
                'user_name' => $config['suitecrm_username'],
                'password' => md5($config['suitecrm_password']),
                'version' => '1',
            ],
            'application_name' => $config['site_name'],
            'name_value_list' => [],
        ]);

        if (!$login['success']) {
            crm_log('crm_result', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => $leadRef,
                'result' => 'failed',
                'method' => 'login',
                'module' => 'Leads',
                'http_status' => $login['status'] ?? 0,
                'reason' => $login['error'] ?? 'SuiteCRM login request failed.',
                'response_excerpt' => crm_response_excerpt($login['body'] ?? null),
            ]);

            return crm_failure('CRM login request failed.');
        }

        $loginData = json_decode((string) ($login['body'] ?? ''), true);
        $sessionId = is_array($loginData) ? (string) ($loginData['id'] ?? '') : '';
        if ($sessionId === '') {
            crm_log('crm_result', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => $leadRef,
                'result' => 'failed',
                'method' => 'login',
                'module' => 'Leads',
                'http_status' => $login['status'] ?? 0,
                'reason' => 'SuiteCRM login did not return a session id.',
                'response_excerpt' => crm_response_excerpt($login['body'] ?? null),
            ]);

            return crm_failure('CRM login failed.');
        }

        $names = crm_name_parts((string) ($payload['name'] ?? ''));
        $descriptionLines = [
            'Service: ' . (string) ($payload['service_type_label'] ?? ''),
            'Context: ' . (string) ($payload['form_context'] ?? ''),
            'Request ID: ' . (string) ($payload['request_id'] ?? ''),
            'IP: ' . (string) ($payload['ip'] ?? ''),
            '',
            (string) ($payload['message'] ?? ''),
        ];

        $fields = [
            ['name' => 'first_name', 'value' => $names['first_name']],
            ['name' => 'last_name', 'value' => $names['last_name']],
            ['name' => 'account_name', 'value' => (string) ($payload['company'] ?? '')],
            ['name' => 'email1', 'value' => (string) ($payload['email'] ?? '')],
            ['name' => 'phone_work', 'value' => (string) ($payload['phone'] ?? '')],
            ['name' => 'status', 'value' => $config['suitecrm_status']],
            ['name' => 'lead_source', 'value' => $config['suitecrm_source']],
            ['name' => 'description', 'value' => implode(PHP_EOL, $descriptionLines)],
        ];

        if ($config['suitecrm_assigned_user_id'] !== '') {
            $fields[] = ['name' => 'assigned_user_id', 'value' => $config['suitecrm_assigned_user_id']];
        }

        if ($config['suitecrm_team_id'] !== '') {
            $fields[] = ['name' => 'team_id', 'value' => $config['suitecrm_team_id']];
        }

        if ($config['suitecrm_team_set_id'] !== '') {
            $fields[] = ['name' => 'team_set_id', 'value' => $config['suitecrm_team_set_id']];
        }

        if ($config['suitecrm_campaign_id'] !== '') {
            $fields[] = ['name' => 'campaign_id', 'value' => $config['suitecrm_campaign_id']];
        }

        $create = suitecrm_legacy_call('set_entry', [
            'session' => $sessionId,
            'module_name' => 'Leads',
            'name_value_list' => $fields,
        ]);

        if (!$create['success']) {
            crm_log('crm_result', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => $leadRef,
                'result' => 'failed',
                'method' => 'set_entry',
                'module' => 'Leads',
                'http_status' => $create['status'] ?? 0,
                'reason' => $create['error'] ?? 'SuiteCRM lead create request failed.',
                'response_excerpt' => crm_response_excerpt($create['body'] ?? null),
            ]);

            return crm_failure('CRM lead create request failed.');
        }

        $createData = json_decode((string) ($create['body'] ?? ''), true);
        $leadId = is_array($createData) ? (string) ($createData['id'] ?? '') : '';
        if ($leadId === '') {
            crm_log('crm_result', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => $leadRef,
                'result' => 'failed',
                'method' => 'set_entry',
                'module' => 'Leads',
                'http_status' => $create['status'] ?? 0,
                'reason' => 'SuiteCRM lead create did not return a lead id.',
                'response_excerpt' => crm_response_excerpt($create['body'] ?? null),
            ]);

            return crm_failure('CRM lead creation failed.');
        }

        crm_log('crm_result', [
            'request_id' => $payload['request_id'] ?? '',
            'lead_ref' => $leadRef,
            'result' => 'success',
            'method' => 'set_entry',
            'module' => 'Leads',
            'http_status' => $create['status'] ?? 0,
            'lead_id' => $leadId,
        ]);

        return [
            'success' => true,
            'mode' => crm_mode(),
            'target' => crm_target_label(),
            'lead_id' => $leadId,
        ];
    }

    function create_queue_api_lead(array $payload): array
    {
        $config = site_config();
        if ($config['intake_api_url'] === '') {
            crm_log('crm_attempt', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => crm_lead_ref($payload),
                'result' => 'failed',
                'reason' => 'Queue API URL is not configured.',
            ]);

            return crm_failure('Queue API is not configured.');
        }

        $leadRef = crm_lead_ref($payload);
        $descriptionLines = [
            'Service: ' . (string) ($payload['service_type_label'] ?? ''),
            'Context: ' . (string) ($payload['form_context'] ?? ''),
            'Request ID: ' . (string) ($payload['request_id'] ?? ''),
            'IP: ' . (string) ($payload['ip'] ?? ''),
            '',
            (string) ($payload['message'] ?? ''),
        ];

        $queuePayload = [
            'name' => (string) ($payload['name'] ?? ''),
            'company' => (string) ($payload['company'] ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? ''),
            'industry' => (string) ($payload['service_type_label'] ?? ''),
            'employee_count' => '',
            'description' => implode(PHP_EOL, $descriptionLines),
        ];

        crm_log('crm_attempt', [
            'request_id' => $payload['request_id'] ?? '',
            'lead_ref' => $leadRef,
            'service_type' => $payload['service_type'] ?? '',
            'module' => 'Leads',
            'methods' => ['queue_api'],
        ]);

        $response = crm_http_post_json($config['intake_api_url'], $queuePayload);
        if (!$response['success']) {
            crm_log('crm_result', [
                'request_id' => $payload['request_id'] ?? '',
                'lead_ref' => $leadRef,
                'result' => 'failed',
                'method' => 'queue_api',
                'module' => 'Leads',
                'http_status' => $response['status'] ?? 0,
                'reason' => $response['error'] ?? 'Queue API request failed.',
                'response_excerpt' => crm_response_excerpt($response['body'] ?? null),
            ]);

            return crm_failure('Queue API request failed.');
        }

        $responseData = json_decode((string) ($response['body'] ?? ''), true);
        $queuedId = is_array($responseData) ? (string) ($responseData['id'] ?? '') : '';

        crm_log('crm_result', [
            'request_id' => $payload['request_id'] ?? '',
            'lead_ref' => $leadRef,
            'result' => 'success',
            'method' => 'queue_api',
            'module' => 'Leads',
            'http_status' => $response['status'] ?? 0,
            'lead_id' => $queuedId,
        ]);

        return [
            'success' => true,
            'mode' => crm_mode(),
            'target' => crm_target_label(),
            'lead_id' => $queuedId,
        ];
    }

    function create_crm_lead(array $payload): array
    {
        if (crm_mode() === 'queue_api') {
            return create_queue_api_lead($payload);
        }

        if (crm_mode() === 'suitecrm_legacy') {
            return create_suitecrm_legacy_lead($payload);
        }

        crm_log('crm_attempt', [
            'request_id' => $payload['request_id'] ?? '',
            'lead_ref' => crm_lead_ref($payload),
            'result' => 'failed',
            'module' => 'Leads',
            'reason' => 'Unsupported CRM mode.',
        ]);

        return crm_failure('Unsupported CRM mode.');
    }
}
