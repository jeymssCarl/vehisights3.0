<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemActivityNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ActivityNotificationService
{
    public static function send(string $title, string $message, string $url, string $category, string $action, ?int $entityId = null): void
    {
        try {
            $actor = Auth::user();
            if (! $actor) {
                return;
            }

            $recipients = User::query()
                ->where('id', '!=', $actor->id)
                ->where('is_active', true)
                ->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new SystemActivityNotification([
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'category' => $category,
                'action' => $action,
                'entity_id' => $entityId,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name ?: $actor->email,
            ]));
        } catch (\Throwable $exception) {
            Log::warning('Activity notification could not be delivered.', [
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
