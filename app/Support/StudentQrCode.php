<?php

namespace App\Support;

use App\Models\Student;
use Carbon\CarbonImmutable;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class StudentQrCode
{
    public const LIFETIME_SECONDS = 30;

    public function generate(Student $student, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::LIFETIME_SECONDS)->timestamp;
        $identity = filled($student->qr_token)
            ? 'token:'.$student->qr_token
            : (filled($student->student_id) ? 'student:'.$student->student_id : 'record:'.$student->getKey());
        $signature = $this->signature($identity, $expiresAt);
        $payload = json_encode([
            'type' => 'student_dynamic',
            'student_id' => $student->student_id,
            'student_qr_token' => $student->qr_token,
            'student_record_id' => $student->getKey(),
            'expires_at' => $expiresAt,
            'signature' => $signature,
        ], JSON_UNESCAPED_SLASHES);
        $imageUrl = (new PngWriter())->write(new QrCode(data: $payload, size: 320))->getDataUri();

        return [
            'payload' => $payload,
            'expires_at' => $expiresAt,
            'verification_code' => strtoupper(substr($signature, 0, 8)),
            'image_url' => $imageUrl,
        ];
    }

    public function valid(array $payload, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $studentId = trim((string) ($payload['student_id'] ?? ''));
        $studentQrToken = trim((string) ($payload['student_qr_token'] ?? ''));
        $studentRecordId = filter_var($payload['student_record_id'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($payload['expires_at'] ?? null, FILTER_VALIDATE_INT);
        $signature = (string) ($payload['signature'] ?? '');

        if (($studentQrToken === '' && $studentId === '' && ! $studentRecordId) || ! $expiresAt || $signature === '' || $expiresAt < $now->timestamp) {
            return false;
        }

        $identity = $studentQrToken !== ''
            ? 'token:'.$studentQrToken
            : ($studentId !== '' ? 'student:'.$studentId : 'record:'.$studentRecordId);

        return hash_equals($this->signature($identity, $expiresAt), $signature);
    }

    private function signature(string $studentId, int $expiresAt): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: $key;
        }

        return hash_hmac('sha256', $studentId.'|'.$expiresAt, $key);
    }
}
