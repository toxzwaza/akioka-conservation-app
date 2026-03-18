<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsScheme
{
    /**
     * APP_URL が https の場合、リクエストを HTTPS として扱う。
     * プロキシが X-Forwarded-Proto を送らない環境でもページネーション等の URL を https で生成するため。
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = config('app.url');
        if ($appUrl && str_starts_with($appUrl, 'https://')) {
            $request->server->set('HTTPS', 'on');
        }

        return $next($request);
    }
}
