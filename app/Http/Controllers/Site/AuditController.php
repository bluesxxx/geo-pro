<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AuditRun;
use GeoPro\AuditEngine\AuditEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditEngine $engine,
    ) {}

    public function form(): View
    {
        return view('site.audit.index');
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $url = $this->normalizeUrl((string) $data['url']);
        if ($url === null) {
            throw ValidationException::withMessages([
                'url' => '请输入有效的网址（支持 http/https）。',
            ]);
        }

        $run = AuditRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'url' => $url,
            'status' => AuditRun::STATUS_PROCESSING,
        ]);

        $result = $this->engine->run($url);

        if ($result->error !== null) {
            $run->update([
                'status' => AuditRun::STATUS_FAILED,
                'error_message' => $result->error,
            ]);

            return redirect()->route('site.audit.show', $run)
                ->with('audit_error', $result->error);
        }

        $run->update([
            'status' => AuditRun::STATUS_COMPLETED,
            'score' => $result->score,
            'missing_faq' => $result->missingFaq,
            'missing_schema' => $result->missingSchema,
            'suggestions' => $result->suggestions,
            'raw_features' => $result->rawFeatures,
        ]);

        return redirect()->route('site.audit.show', $run);
    }

    public function show(AuditRun $auditRun): View
    {
        return view('site.audit.show', [
            'auditRun' => $auditRun,
        ]);
    }

    /**
     * 补全缺失的 scheme（体验友好），并校验协议与域名合法性。
     */
    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return null;
        }
        $host = rtrim(strtolower($host), '.');
        if (filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            return null;
        }

        return $scheme.'://'.$host.$this->portAndPath($parts);
    }

    /** @param array<string, mixed> $parts */
    private function portAndPath(array $parts): string
    {
        $result = '';
        if (isset($parts['port'])) {
            $result .= ':'.(int) $parts['port'];
        }
        $result .= (string) ($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $result .= '?'.(string) $parts['query'];
        }

        return $result;
    }
}
