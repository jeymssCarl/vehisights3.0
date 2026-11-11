<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueTrafficCounterToken extends Command
{
    protected $signature = 'traffic-counter:issue-token
                            {email : Active VehiSights user email}
                            {--device=TrafficCounter-Mac : Name of the Swift counting device}';

    protected $description = 'Issue a scoped Laravel Sanctum token for the Swift traffic counter';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $device = trim((string) $this->option('device'));

        if ($device === '') {
            $this->error('The device name cannot be empty.');

            return self::FAILURE;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            $this->error('No active VehiSights user was found for that email address.');

            return self::FAILURE;
        }

        $tokenName = 'TrafficCounter:'.$device;

        // One current credential per named Mac prevents forgotten duplicate
        // tokens while leaving other counting devices unaffected.
        $user->tokens()->where('name', $tokenName)->delete();

        $plainTextToken = $user->createToken($tokenName, [
            'camera:manage',
            'auto-session:write',
        ])->plainTextToken;

        $this->newLine();
        $this->info('TrafficCounter token created successfully.');
        $this->line('Device: '.$device);
        $this->line('User: '.$user->email);
        $this->newLine();
        $this->warn('Copy this token now. It will not be shown again:');
        $this->line($plainTextToken);
        $this->newLine();
        $this->comment('Store it only in the Mac Xcode Run scheme as VEHISIGHTS_API_TOKEN.');

        return self::SUCCESS;
    }
}
