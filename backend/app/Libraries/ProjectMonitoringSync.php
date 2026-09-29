<?php

namespace App\Libraries;

use Config\ProjectMonitoring;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class ProjectMonitoringSync
{
    private ProjectMonitoring $config;

    public function __construct(?ProjectMonitoring $config = null)
    {
        $this->config = $config ?? config(ProjectMonitoring::class);
    }

    public function sync(array $project): array
    {
        if (! $this->config->enabled) return ['status' => 'disabled', 'message' => 'Sinkronisasi portal belum diaktifkan.'];
        if (strlen($this->config->syncSecret) < 24) return ['status' => 'failed', 'message' => 'Kunci sinkronisasi portal belum dikonfigurasi.'];

        $data = json_decode((string) ($project['payload'] ?? ''), true);
        if (! is_array($data)) return ['status' => 'failed', 'message' => 'Payload Scheduler tidak valid.'];

        try {
            $projectPayload = $this->projectPayload($project, $data);
            $linked = $this->post('/api/project-monitoring/v1/projects/from-cost-estimator', $projectPayload);
            $schedulePayload = $this->schedulePayload($project, $data);
            $synced = $this->post('/api/project-monitoring/v1/schedule/sync', $schedulePayload);
            return ['status' => 'succeeded', 'projectId' => $linked['id'] ?? null, 'activityCount' => $synced['activityCount'] ?? count($schedulePayload['activities']), 'revision' => $schedulePayload['revision']];
        } catch (Throwable $error) {
            log_message('error', 'Project Monitoring sync failed for Scheduler project {id}: {message}', ['id' => $project['id'] ?? 'unknown', 'message' => $error->getMessage()]);
            return ['status' => 'failed', 'message' => $error->getMessage()];
        }
    }

    public function projectPayload(array $project, array $data): array
    {
        $id = (int) $project['id'];
        $startDate = $this->validDate($data['projectStartDate'] ?? null);
        $finishDates = [];
        foreach (($data['activities'] ?? []) as $activity) {
            $finish = is_array($activity) ? $this->activityFinish($activity, $data, $startDate !== null) : null;
            if ($finish !== null) $finishDates[] = $finish;
        }
        return [
            'costEstimatorProjectId' => 'scheduler-project-' . $id,
            'code' => sprintf('CE-SCH-%04d', $id),
            'name' => trim((string) ($project['name'] ?? $data['name'] ?? 'Scheduler Project')),
            'clientName' => trim((string) ($project['client_name'] ?? '')) ?: 'Client belum ditentukan',
            'startDate' => $startDate,
            'targetFinishDate' => $finishDates ? max($finishDates) : null,
        ];
    }

    public function schedulePayload(array $project, array $data): array
    {
        $activities = array_values(array_filter($data['activities'] ?? [], 'is_array'));
        $defaultWeight = $activities ? 100 / count($activities) : 0;
        $hasCalendarDates = $this->validDate($data['projectStartDate'] ?? null) !== null;
        $mapped = [];
        foreach ($activities as $index => $activity) {
            $sourceId = trim((string) ($activity['id'] ?? ''));
            if ($sourceId === '') continue;
            $weight = isset($activity['weight']) && is_numeric($activity['weight']) ? (float) $activity['weight'] : $defaultWeight;
            $mapped[] = [
                'id' => $sourceId,
                'name' => trim((string) ($activity['name'] ?? $sourceId)),
                'plannedStart' => $hasCalendarDates ? $this->validDate($activity['start'] ?? null) : null,
                'plannedFinish' => $this->activityFinish($activity, $data, $hasCalendarDates),
                'weight' => round(max(0, min(100, $weight)), 6),
                'targetQuantity' => isset($activity['targetQuantity']) && is_numeric($activity['targetQuantity']) ? max(0, (float) $activity['targetQuantity']) : null,
                'unit' => ($unit = trim((string) ($activity['unit'] ?? ''))) !== '' ? $unit : null,
                'sortOrder' => $index,
                'archived' => false,
                'dependencies' => array_values(array_filter(array_map(static function ($dependency): ?array {
                    if (! is_array($dependency) || empty($dependency['id'])) return null;
                    $type = strtoupper((string) ($dependency['type'] ?? 'FS'));
                    return ['predecessorSourceActivityId' => (string) $dependency['id'], 'type' => ['SS' => 'start_to_start', 'FF' => 'finish_to_finish', 'SF' => 'start_to_finish'][$type] ?? 'finish_to_start', 'lagDays' => max(-365, min(365, (float) ($dependency['lag'] ?? 0)))];
                }, $activity['predecessors'] ?? []))),
            ];
        }
        return ['projectId' => 'scheduler-project-' . (int) $project['id'], 'revision' => hash('sha256', (string) ($project['updated_at'] ?? '') . '|' . (string) ($project['payload'] ?? '')), 'activities' => $mapped];
    }

    private function activityFinish(array $activity, array $data, bool $hasCalendarDates): ?string
    {
        $start = $hasCalendarDates ? $this->validDate($activity['start'] ?? null) : null;
        if ($start === null) return null;
        $workingDays = array_map('intval', $data['calendar']['workingDays'] ?? [0, 1, 2, 3, 4, 5, 6]);
        if (! $workingDays) $workingDays = [0, 1, 2, 3, 4, 5, 6];
        $remaining = max(1, (int) ceil((float) ($activity['duration'] ?? 1))) - 1;
        $date = new DateTimeImmutable($start);
        while ($remaining > 0) {
            $date = $date->modify('+1 day');
            if (in_array((int) $date->format('w'), $workingDays, true)) $remaining--;
        }
        return $date->format('Y-m-d');
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function post(string $path, array $payload): array
    {
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Project-Monitoring-Key' => $this->config->syncSecret, 'X-Actor-Id' => 'costestimator-scheduler'];
        if ($this->config->siteBypassToken !== '') $headers['OAI-Sites-Authorization'] = 'Bearer ' . $this->config->siteBypassToken;
        $response = service('curlrequest')->request('POST', $this->config->baseUrl . $path, [
            'headers' => $headers,
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'http_errors' => false,
            'timeout' => $this->config->timeout,
            'connect_timeout' => min(5, $this->config->timeout),
        ]);
        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            $message = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
            throw new RuntimeException($message ?: 'Portal mengembalikan HTTP ' . $status . '.');
        }
        return $body;
    }
}
