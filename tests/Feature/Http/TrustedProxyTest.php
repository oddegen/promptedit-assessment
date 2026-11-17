<?php

test('links use https when the server proxy says the visitor came over https', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'promptedit.oddegen.et'])
        ->get('/studio');

    $response->assertRedirect('https://promptedit.oddegen.et/login');
});

test('forwarded headers from anywhere else are ignored', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->get('/studio');

    $response->assertRedirect(route('login'));
});
