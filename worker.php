#!/usr/bin/env php
<?php
declare(strict_types=1);

$jobDir = $argv[1] ?? __DIR__ . '/jobs';
$resultDir = $argv[2] ?? __DIR__ . '/results';
$ffmpeg = getenv('FFMPEG_BIN') ?: '/usr/bin/ffmpeg';
$ffprobe = getenv('FFPROBE_BIN') ?: '/usr/bin/ffprobe';

if (!is_dir($jobDir)) {
    mkdir($jobDir, 0755, true);
}
if (!is_dir($resultDir)) {
    mkdir($resultDir, 0755, true);
}

echo "Samplitter FFmpeg worker\n";
echo "Watching: {$jobDir}\n";

while (true) {
    $jobs = glob($jobDir . '/*.pending');
    foreach ($jobs as $jobFile) {
        $lockFile = sprintf('%s.lock', substr($jobFile, 0, -8));
        if (!@rename($jobFile, $lockFile)) {
            continue;
        }
        $payload = json_decode(file_get_contents($lockFile), true);
        if (!$payload) {
            unlink($lockFile);
            continue;
        }
        $result = processJob($payload, $ffmpeg, $ffprobe, $resultDir);
        $resultFile = sprintf('%s/%s.result.json', $resultDir, $payload['id'] ?? uniqid('job_', true));
        file_put_contents($resultFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        unlink($lockFile);
        echo sprintf("[%s] %s -> %s\n", date('c'), $payload['id'] ?? 'unset', $result['status'] ?? 'done');
    }
    sleep(3);
}

function processJob(array $job, string $ffmpeg, string $ffprobe, string $resultDir): array
{
    $result = [
        'id' => $job['id'] ?? uniqid('job_', true),
        'status' => 'failed',
        'detail' => null,
    ];

    $input = $job['input'] ?? '';
    $output = $job['output'] ?? '';
    $action = $job['action'] ?? 'convert';
    if (empty($input) || empty($output)) {
        $result['detail'] = 'missing input or output path';
        return $result;
    }

    try {
        switch ($action) {
            case 'convert':
                $format = $job['format'] ?? 'mp3';
                runCommand($ffmpeg, sprintf('-y -i %s %s', escapeshellarg($input), escapeshellarg($output)));
                break;
            case 'trim':
                $start = (int) ($job['start'] ?? 0);
                $end = (int) ($job['end'] ?? 0);
                runCommand($ffmpeg, sprintf('-y -i %s -ss %d -to %d -c copy %s', escapeshellarg($input), $start, $end, escapeshellarg($output)));
                break;
            case 'analyze':
                $output = $job['output'] ?? $resultDir . '/' . basename($input) . '.analysis.json';
                $analysis = runCommand($ffprobe, sprintf('-v quiet -print_format json -show_format -show_streams %s', escapeshellarg($input)));
                file_put_contents($output, $analysis);
                $result['analysis'] = json_decode($analysis, true);
                break;
            default:
                throw new RuntimeException('unknown action');
        }
        $result['status'] = 'completed';
        $result['detail'] = sprintf('%s done', $action);
    } catch (Throwable $t) {
        $result['detail'] = $t->getMessage();
    }

    return $result;
}

function runCommand(string $bin, string $args): string
{
    $command = sprintf('%s %s 2>&1', escapeshellcmd($bin), $args);
    $output = [];
    exec($command, $output, $exit);
    if ($exit !== 0) {
        throw new RuntimeException(sprintf('Command failed (%s): %s', $command, implode("\n", $output)));
    }
    return implode("\n", $output);
}
