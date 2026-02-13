<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ApiTestController extends Controller
{
    /**
     * APIテスト画面を表示
     */
    public function index()
    {
        $baseUrl = config('services.conservation_api.base_url');

        return Inertia::render('ApiTest/Index', [
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * APIを実行してプロキシ、履歴に保存
     */
    public function execute(Request $request)
    {
        try {
            return $this->doExecute($request);
        } catch (\Throwable $e) {
            \Log::error('ApiTest execute error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました。',
            ], 500);
        }
    }

    /**
     * API実行の本体
     */
    private function doExecute(Request $request)
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'path' => ['required', 'string', 'max:2048'],
            'query' => ['nullable', 'array'],
            'body' => ['nullable', 'string'],
        ], [], [
            'method' => 'HTTPメソッド',
            'path' => 'パス',
        ]);

        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $path = ltrim($validated['path'], '/');
        $url = $baseUrl . '/' . $path;

        if (! empty($validated['query'])) {
            $url .= '?' . http_build_query($validated['query']);
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $http = Http::timeout(30)->withHeaders($headers);

        $start = microtime(true);

        try {
            $body = $validated['body'] ?? null;
            $decodedBody = null;
            if ($body !== null && $body !== '') {
                $decodedBody = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE && in_array($validated['method'], ['POST', 'PUT', 'PATCH'], true)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'リクエストBodyが正しいJSON形式ではありません。',
                    ], 422);
                }
            }

            $response = match (strtoupper($validated['method'])) {
                'GET' => $http->get($url),
                'POST' => $http->post($url, $decodedBody ?? []),
                'PUT' => $http->put($url, $decodedBody ?? []),
                'PATCH' => $http->patch($url, $decodedBody ?? []),
                'DELETE' => $http->delete($url),
                default => throw new \InvalidArgumentException('Invalid method'),
            };
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            ApiRequestLog::create([
                'user_id' => auth()->id(),
                'method' => $validated['method'],
                'url' => $url,
                'request_headers' => $headers,
                'request_body' => $body ?? null,
                'response_status' => null,
                'response_headers' => null,
                'response_body' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'response_status' => null,
                'response_headers' => [],
                'response_body' => null,
            ]);
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $responseBody = $response->body();
        $responseBodyTruncated = strlen($responseBody) > 50000
            ? substr($responseBody, 0, 50000) . "\n...(truncated)"
            : $responseBody;

        $responseHeaders = $response->headers();
        $responseHeadersArray = is_array($responseHeaders) ? $responseHeaders : $responseHeaders->all();

        ApiRequestLog::create([
            'user_id' => auth()->id(),
            'method' => $validated['method'],
            'url' => $url,
            'request_headers' => $headers,
            'request_body' => $validated['body'] ?? null,
            'response_status' => $response->status(),
            'response_headers' => $responseHeadersArray,
            'response_body' => $responseBodyTruncated,
            'duration_ms' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'response_status' => $response->status(),
            'response_headers' => $responseHeadersArray,
            'response_body' => $response->json() ?? $responseBody,
            'duration_ms' => $duration,
        ]);
    }

    /**
     * API実行履歴を取得（ページネーション）
     */
    public function history(Request $request)
    {
        $logs = ApiRequestLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $request->integer('page', 1));

        return response()->json($logs);
    }
}
