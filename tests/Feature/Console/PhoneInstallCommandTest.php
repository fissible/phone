<?php

declare(strict_types=1);

it('publishes config and migrations and prints next steps', function (): void {
    $this->artisan('phone:install')
        ->assertExitCode(0)
        ->expectsOutputToContain('Fissible Phone installed')
        ->expectsOutputToContain('TWILIO_ACCOUNT_SID');
});
