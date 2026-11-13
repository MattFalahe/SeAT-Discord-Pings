<?php

namespace DiscordPings\Services\PingRequests;

use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestAnswer;
use DiscordPings\Services\DiscordUserResolver;
use Illuminate\Support\Facades\Log;

/**
 * Which FCs answered a member's request. A request counts as answered once a
 * broadcast sent from it reaches Discord, whether the FC came from the link
 * in the request's message or from FC Opportunities. Opening the form alone
 * promises nothing.
 */
class RequestAnswers
{
    /**
     * After a broadcast has gone out. If it answered a request, that's
     * recorded and the request's Discord message says so. The broadcast is
     * already on Discord, so nothing here may get in its way.
     *
     * @param array $data what the broadcast was sent with, answers_request naming the request
     * @param object $user who sent it
     * @param object|null $broadcast its History row
     */
    public static function afterBroadcast(array $data, $user, $broadcast = null): void
    {
        $requestId = is_numeric($data['answers_request'] ?? null) ? (int) $data['answers_request'] : 0;

        if ($requestId <= 0) {
            return;
        }

        try {
            $request = PingHistory::find($requestId);

            if (! $request || ! $request->isMemberRequest() || $request->status !== 'sent') {
                return;
            }

            $userId = (int) ($user->id ?? 0);
            $name = trim((string) ($data['fc_name'] ?? ''));

            // A scheduled broadcast runs as "Scheduled Ping", so the account's main says who it was.
            if ($name === '') {
                $name = DiscordUserResolver::mainNameOfUser($userId) ?? trim((string) ($user->name ?? ''));
            }

            $broadcastId = isset($broadcast->id) ? (int) $broadcast->id : null;

            if (self::record($requestId, $userId, $name !== '' ? $name : 'An FC', $broadcastId)) {
                (new PingRequestSender())->markAnswered($request);
            }
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not record who answered a ping request: ' . ErrorText::safe($e->getMessage()), ['history_id' => $requestId]);
        }
    }

    /**
     * The answers to each of these requests, first one first.
     *
     * @param int[] $requestIds
     * @return array<int, array<int, array{name: string, at: int, broadcast_id: ?int}>>|null null when they couldn't be read
     */
    public static function forRequests(array $requestIds): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $requestIds))));

        if ($ids === []) {
            return [];
        }

        try {
            $rows = PingRequestAnswer::whereIn('request_history_id', $ids)->orderBy('created_at')->orderBy('id')->get()->all();
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not read who answered ping requests: ' . ErrorText::safe($e->getMessage()));

            return null;
        }

        return self::grouped($rows);
    }

    /**
     * @param object[] $rows answer rows in the order they came
     * @return array<int, array<int, array{name: string, at: int, broadcast_id: ?int}>>
     */
    public static function grouped(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->request_history_id][] = [
                'name' => (string) $row->fc_name,
                'at' => self::timestamp($row->created_at),
                'broadcast_id' => $row->broadcast_history_id ? (int) $row->broadcast_history_id : null,
            ];
        }

        return $grouped;
    }

    /**
     * For a page's script: the names, and when in a form the browser reads.
     *
     * @param array<int, array{name: string, at: int}> $answers
     * @return array<int, array{name: string, at: string}>
     */
    public static function forPage(array $answers): array
    {
        return array_map(fn ($answer) => [
            'name' => $answer['name'],
            'at' => gmdate('Y-m-d\TH:i:s\Z', $answer['at']),
        ], array_values($answers));
    }

    /**
     * One row per FC, so the same broadcast sent to several webhooks, or a
     * second broadcast from the same FC, doesn't add them again.
     *
     * @return bool whether this is the FC's first answer to it
     */
    private static function record(int $requestId, int $userId, string $name, ?int $broadcastId): bool
    {
        if (PingRequestAnswer::where('request_history_id', $requestId)->where('user_id', $userId)->exists()) {
            return false;
        }

        try {
            PingRequestAnswer::create([
                'request_history_id' => $requestId,
                'user_id' => $userId,
                'fc_name' => mb_substr($name, 0, 100),
                'broadcast_history_id' => $broadcastId,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Another webhook's copy of the same broadcast got there first.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    private static function timestamp($value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $parsed = is_string($value) ? strtotime($value) : false;

        return $parsed !== false ? $parsed : time();
    }
}
