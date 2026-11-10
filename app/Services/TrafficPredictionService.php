<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Session;
use App\Models\TrafficHistory;
use Carbon\Carbon;
use DomainException;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class TrafficPredictionService
{
    private const ARTIFACT_SCHEMA = 'rfc-los-v2';

    public function status(): array
    {
        $metadata = $this->readMetadata();
        $availableRows = TrafficHistory::query()->count();
        $minimumRows = config('predictions.minimum_training_rows');

        return [
            'ready' => File::exists($this->modelPath())
                && ($metadata['algorithm'] ?? null) === 'RandomForestClassifier'
                && ($metadata['artifact_schema'] ?? null) === self::ARTIFACT_SCHEMA,
            'available_rows' => $availableRows,
            'minimum_rows' => $minimumRows,
            'recommended_rows' => config('predictions.recommended_training_rows'),
            'trained_at' => $metadata['trained_at'] ?? null,
            'training_rows' => $metadata['training_rows'] ?? 0,
            'model_version' => $metadata['model_version'] ?? null,
            'algorithm' => $metadata['algorithm'] ?? null,
            'los_classes' => $metadata['los_classes'] ?? [],
            'metrics' => $metadata['metrics'] ?? null,
        ];
    }

    public function train(): array
    {
        $rows = TrafficHistory::query()
            ->orderBy('hour_timestamp')
            ->get([
                'road_name',
                'direction',
                'hour_timestamp',
                'total_pcu',
                'vc_ratio',
                'los',
                'source',
            ]);

        $minimumRows = config('predictions.minimum_training_rows');

        if ($rows->count() < $minimumRows) {
            throw new DomainException(
                "At least {$minimumRows} traffic-history rows are required for training; {$rows->count()} are available."
            );
        }

        $losClasses = $rows
            ->map(fn (TrafficHistory $row) => strtoupper((string) $row->los))
            ->filter(fn (string $los) => in_array($los, ['A', 'B', 'C', 'D', 'E', 'F'], true))
            ->unique();

        if ($losClasses->count() < 2) {
            throw new DomainException('RandomForestClassifier training requires historical data from at least two LOS classes.');
        }

        $this->ensureArtifactDirectory();
        $trainingPath = $this->artifactDirectory().DIRECTORY_SEPARATOR.'training-'.Str::uuid().'.json';

        File::put($trainingPath, json_encode([
            'records' => $rows->map(fn (TrafficHistory $row) => [
                'road_name' => $row->road_name,
                'direction' => $row->direction,
                'hour_timestamp' => $row->hour_timestamp->toIso8601String(),
                'total_pcu' => (float) $row->total_pcu,
                'vc_ratio' => (float) $row->vc_ratio,
                'los' => $row->los,
                'source' => $row->source,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));

        try {
            $result = $this->runPython([
                base_path('ml/train_random_forest.py'),
                '--input', $trainingPath,
                '--model', $this->modelPath(),
                '--metadata', $this->metadataPath(),
            ]);
        } finally {
            File::delete($trainingPath);
        }

        return $this->decodeResult($result);
    }

    public function forecast(string $roadName, Carbon $date, int $hours): array
    {
        if (! $this->status()['ready']) {
            throw new DomainException('Train the RFC LOS model first. The previous hybrid model cannot be used for this forecast.');
        }

        $this->ensureArtifactDirectory();
        $requestPath = $this->artifactDirectory().DIRECTORY_SEPARATOR.'forecast-'.Str::uuid().'.json';
        $latestHistory = TrafficHistory::query()
            ->where('road_name', $roadName)
            ->latest('hour_timestamp')
            ->first();

        $manilaDate = $date->copy()->setTimezone('Asia/Manila');
        $startAt = $manilaDate->isToday()
            ? now('Asia/Manila')->startOfHour()->addHour()
            : $manilaDate->startOfDay();

        File::put($requestPath, json_encode([
            'road_name' => $roadName,
            'direction' => $latestHistory?->direction,
            'source' => $latestHistory?->source ?? 'manual',
            'start_at' => $startAt->toIso8601String(),
            'hours' => $hours,
            'capacity' => $this->capacityForRoad($roadName),
        ], JSON_THROW_ON_ERROR));

        try {
            $result = $this->runPython([
                base_path('ml/predict_random_forest.py'),
                '--model', $this->modelPath(),
                '--metadata', $this->metadataPath(),
                '--input', $requestPath,
            ]);
        } finally {
            File::delete($requestPath);
        }

        return $this->decodeResult($result);
    }

    private function capacityForRoad(string $roadName): int
    {
        $session = Session::query()
            ->where('road_name', $roadName)
            ->latest('start_time')
            ->first(['lanes', 'base_capacity_per_lane']);

        if ($session) {
            return max(1, (int) $session->lanes * (int) $session->base_capacity_per_lane);
        }

        $schedule = Schedule::query()
            ->where('road_name', $roadName)
            ->latest('created_at')
            ->first(['lanes', 'base_capacity_per_lane']);

        return max(1, (int) ($schedule?->lanes ?: 1) * (int) ($schedule?->base_capacity_per_lane ?: 720));
    }

    private function runPython(array $arguments): ProcessResult
    {
        $command = array_merge([config('predictions.python_binary')], $arguments);
        $result = Process::env([
            'PYTHONHASHSEED' => '0',
            'PYTHONUTF8' => '1',
            'JOBLIB_MULTIPROCESSING' => '0',
            'LOKY_MAX_CPU_COUNT' => '1',
            'VEHISIGHTS_ML_NO_ASYNCIO' => '1',
            'OMP_NUM_THREADS' => '1',
            'OPENBLAS_NUM_THREADS' => '1',
            'MKL_NUM_THREADS' => '1',
        ])->timeout(config('predictions.process_timeout'))->run($command);

        if ($result->failed()) {
            $message = trim($result->errorOutput()) ?: trim($result->output());
            throw new RuntimeException($message ?: 'The prediction process failed.');
        }

        return $result;
    }

    private function decodeResult(ProcessResult $result): array
    {
        $decoded = json_decode(trim($result->output()), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The prediction process returned an invalid response.');
        }

        return $decoded;
    }

    private function readMetadata(): ?array
    {
        if (! File::exists($this->metadataPath())) {
            return null;
        }

        $decoded = json_decode(File::get($this->metadataPath()), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function ensureArtifactDirectory(): void
    {
        File::ensureDirectoryExists($this->artifactDirectory());
    }

    private function artifactDirectory(): string
    {
        return storage_path('app/private/predictions');
    }

    private function modelPath(): string
    {
        return $this->artifactDirectory().DIRECTORY_SEPARATOR.'random-forest-classifier.joblib';
    }

    private function metadataPath(): string
    {
        return $this->artifactDirectory().DIRECTORY_SEPARATOR.'random-forest-classifier-metadata.json';
    }
}
