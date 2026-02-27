<?php

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

if ($username === '') {
    echo json_encode(['error' => 'Missing username parameter.']);
    exit;
}

function github_get(string $url): array|null {
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: github-email-finder\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }

    return json_decode($body, true);
}

// Step 1: Check the user profile for a public email.
$profile = github_get("https://api.github.com/users/{$username}");

if ($profile === null || isset($profile['message']) && $profile['message'] === 'Not Found') {
    echo json_encode(['error' => "User \"{$username}\" not found."]);
    exit;
}

if (!empty($profile['email'])) {
    echo json_encode(['username' => $username, 'emails' => [$profile['email']]]);
    exit;
}

// Step 2: Scan public events for commit emails.
$events = github_get("https://api.github.com/users/{$username}/events/public?per_page=100");

$emails = [];

if (is_array($events)) {
    foreach ($events as $event) {
        if (($event['type'] ?? '') !== 'PushEvent') {
            continue;
        }
        foreach ($event['payload']['commits'] ?? [] as $commit) {
            $email = $commit['author']['email'] ?? '';
            if ($email !== '' && !str_contains($email, 'noreply') && !str_contains($email, '@users.')) {
                $emails[$email] = true;
            }
        }
    }
}

echo json_encode(['username' => $username, 'emails' => array_keys($emails)]);
