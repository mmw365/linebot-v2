<?php

namespace App\Http\Middleware;

use App\Models\InboundMessageLog;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class MessageLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        InboundMessageLog::create([
            'message' => $request->getContent(),
            'created_at' => Carbon::now(),
        ]);
        return $next($request);
    }
}
